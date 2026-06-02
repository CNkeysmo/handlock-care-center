<?php
namespace HLCC\App\Services;

use HLCC\Data\Repositories\MobileSessionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class MobileSessionService
{
    private const ACCESS_TTL_SECONDS = 7200; // 2h
    private const REFRESH_TTL_SECONDS = 157680000; // ~5y
    public const ERROR_REVOKED = 'revoked';
    public const ERROR_EXPIRED = 'expired';
    public const ERROR_DEVICE_MISMATCH = 'device_mismatch';
    public const ERROR_USER_DISABLED = 'user_disabled';

    private MobileSessionRepository $repo;

    public function __construct()
    {
        $this->repo = new MobileSessionRepository();
    }

    public function issue_for_user(
        int $userId,
        string $deviceId,
        string $deviceName = '',
        string $appVersion = '',
        string $platform = 'android'
    ): array {
        $deviceId = $this->normalize_device_id($deviceId);
        if ($userId <= 0 || $deviceId === '') {
            return [];
        }

        if (!$this->is_user_active($userId)) {
            return [];
        }

        $accessToken = $this->generate_token();
        $refreshToken = $this->generate_token();
        $now = new \DateTimeImmutable(current_time('mysql'), wp_timezone());
        $accessExpiresAt = $now->modify('+' . self::ACCESS_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
        $refreshExpiresAt = $now->modify('+' . self::REFRESH_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');

        $this->repo->upsert_by_user_device($userId, $deviceId, [
            'device_name' => $this->normalize_device_name($deviceName),
            'platform' => sanitize_key($platform ?: 'android'),
            'app_version' => sanitize_text_field($appVersion),
            'access_token_hash' => $this->hash_token($accessToken),
            'refresh_token_hash' => $this->hash_token($refreshToken),
            'access_expires_at' => $accessExpiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'last_seen_at' => $now->format('Y-m-d H:i:s'),
            'revoked_at' => null,
        ]);

        return $this->build_token_response($userId, $accessToken, $refreshToken, $accessExpiresAt, $refreshExpiresAt, $deviceId);
    }

    public function refresh(string $refreshToken, string $deviceId): array
    {
        $result = $this->refresh_result($refreshToken, $deviceId);
        return !empty($result['ok']) ? (array) ($result['payload'] ?? []) : [];
    }

    public function refresh_result(string $refreshToken, string $deviceId): array
    {
        $deviceId = $this->normalize_device_id($deviceId);
        $refreshToken = trim($refreshToken);

        if ($deviceId === '' || $refreshToken === '') {
            return $this->fail_result(self::ERROR_EXPIRED);
        }

        $session = $this->repo->get_by_refresh_hash($this->hash_token($refreshToken));
        if (!$session) {
            return $this->fail_result(self::ERROR_EXPIRED);
        }

        if ((string) ($session->device_id ?? '') !== $deviceId) {
            return $this->fail_result(self::ERROR_DEVICE_MISMATCH);
        }

        $sessionValidation = $this->validate_refreshable_session($session);
        if (empty($sessionValidation['ok'])) {
            return $sessionValidation;
        }

        $userId = (int) ($session->user_id ?? 0);
        if (!$this->is_user_active($userId)) {
            return $this->fail_result(self::ERROR_USER_DISABLED);
        }

        $payload = $this->issue_tokens_for_session(
            $userId,
            $deviceId,
            (string) ($session->device_name ?? ''),
            (string) ($session->app_version ?? ''),
            (string) ($session->platform ?? 'android')
        );

        return [
            'ok' => true,
            'payload' => $payload,
            'error_code' => '',
        ];
    }

    public function web_bootstrap(string $refreshToken, string $deviceId): array
    {
        $result = $this->refresh_result($refreshToken, $deviceId);
        if (empty($result['ok'])) {
            return $result;
        }

        $payload = (array) ($result['payload'] ?? []);
        $userId = (int) (($payload['user']['id'] ?? 0));
        if ($userId <= 0) {
            return $this->fail_result(self::ERROR_USER_DISABLED);
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return $this->fail_result(self::ERROR_USER_DISABLED);
        }

        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true, is_ssl());
        do_action('wp_login', (string) $user->user_login, $user);

        $payload['web_authenticated'] = true;
        return [
            'ok' => true,
            'payload' => $payload,
            'error_code' => '',
        ];
    }

    public function authenticate_access_token(string $accessToken): ?array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return null;
        }

        $session = $this->repo->get_by_access_hash($this->hash_token($accessToken));
        if (!$session) {
            return null;
        }

        if (!$this->is_session_accessible($session)) {
            return null;
        }

        $userId = (int) ($session->user_id ?? 0);
        if (!$this->is_user_active($userId)) {
            return null;
        }

        $this->repo->touch_last_seen((int) ($session->id ?? 0));

        return [
            'user_id' => $userId,
            'device_id' => (string) ($session->device_id ?? ''),
            'session_id' => (int) ($session->id ?? 0),
        ];
    }

    public function revoke_by_access_token(string $accessToken): bool
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return false;
        }

        $session = $this->repo->get_by_access_hash($this->hash_token($accessToken));
        if (!$session) {
            return false;
        }

        return $this->repo->revoke_by_id((int) ($session->id ?? 0));
    }

    public function revoke_by_user_device(int $userId, string $deviceId): bool
    {
        $deviceId = $this->normalize_device_id($deviceId);
        if ($userId <= 0 || $deviceId === '') {
            return false;
        }

        return $this->repo->delete_by_user_device($userId, $deviceId);
    }

    private function is_user_active(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        if (empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        // Treat disabled-like accounts (no frontend read capability) as invalid.
        if (!user_can($user, 'read')) {
            return false;
        }

        return true;
    }

    private function is_session_accessible(object $session): bool
    {
        if (!empty($session->revoked_at)) {
            return false;
        }

        $nowTs = $this->now_timestamp();
        $accessTs = strtotime((string) ($session->access_expires_at ?? ''));
        if ($accessTs === false || $accessTs <= $nowTs) {
            return false;
        }

        return true;
    }

    private function is_session_refreshable(object $session): bool
    {
        if (!empty($session->revoked_at)) {
            return false;
        }

        $nowTs = $this->now_timestamp();
        $refreshTs = strtotime((string) ($session->refresh_expires_at ?? ''));
        if ($refreshTs === false || $refreshTs <= $nowTs) {
            return false;
        }

        return true;
    }

    private function validate_refreshable_session(object $session): array
    {
        if (!empty($session->revoked_at)) {
            return $this->fail_result(self::ERROR_REVOKED);
        }

        $nowTs = $this->now_timestamp();
        $refreshTs = strtotime((string) ($session->refresh_expires_at ?? ''));
        if ($refreshTs === false || $refreshTs <= $nowTs) {
            return $this->fail_result(self::ERROR_EXPIRED);
        }

        return ['ok' => true, 'payload' => [], 'error_code' => ''];
    }

    private function build_token_response(
        int $userId,
        string $accessToken,
        string $refreshToken,
        string $accessExpiresAt,
        string $refreshExpiresAt,
        string $deviceId
    ): array {
        $user = get_user_by('id', $userId);

        return [
            'access_token' => $accessToken,
            'access_expires_at' => $accessExpiresAt,
            'access_expires_in' => self::ACCESS_TTL_SECONDS,
            'refresh_token' => $refreshToken,
            'refresh_expires_at' => $refreshExpiresAt,
            'refresh_expires_in' => self::REFRESH_TTL_SECONDS,
            'device_id' => $deviceId,
            'user' => [
                'id' => $userId,
                'display_name' => $user ? (string) $user->display_name : '',
                'login' => $user ? (string) $user->user_login : '',
            ],
            'server_time' => current_time('mysql'),
            'timezone' => $this->resolve_timezone_for_client(),
        ];
    }

    private function issue_tokens_for_session(
        int $userId,
        string $deviceId,
        string $deviceName,
        string $appVersion,
        string $platform
    ): array {
        $accessToken = $this->generate_token();
        $newRefreshToken = $this->generate_token();
        $now = new \DateTimeImmutable(current_time('mysql'), wp_timezone());
        $accessExpiresAt = $now->modify('+' . self::ACCESS_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');
        $refreshExpiresAt = $now->modify('+' . self::REFRESH_TTL_SECONDS . ' seconds')->format('Y-m-d H:i:s');

        $this->repo->upsert_by_user_device($userId, $deviceId, [
            'device_name' => $this->normalize_device_name($deviceName),
            'platform' => sanitize_key($platform ?: 'android'),
            'app_version' => sanitize_text_field($appVersion),
            'access_token_hash' => $this->hash_token($accessToken),
            'refresh_token_hash' => $this->hash_token($newRefreshToken),
            'access_expires_at' => $accessExpiresAt,
            'refresh_expires_at' => $refreshExpiresAt,
            'last_seen_at' => $now->format('Y-m-d H:i:s'),
            'revoked_at' => null,
        ]);

        return $this->build_token_response(
            $userId,
            $accessToken,
            $newRefreshToken,
            $accessExpiresAt,
            $refreshExpiresAt,
            $deviceId
        );
    }

    private function fail_result(string $errorCode): array
    {
        return [
            'ok' => false,
            'payload' => [],
            'error_code' => $errorCode,
        ];
    }

    private function generate_token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    private function hash_token(string $token): string
    {
        return hash('sha256', $token);
    }

    private function normalize_device_id(string $deviceId): string
    {
        $deviceId = strtolower(trim($deviceId));
        $deviceId = preg_replace('/[^a-z0-9_-]/', '', $deviceId) ?: '';
        return substr($deviceId, 0, 128);
    }

    private function normalize_device_name(string $name): string
    {
        return substr(sanitize_text_field($name), 0, 191);
    }

    private function now_timestamp(): int
    {
        return (new \DateTimeImmutable(current_time('mysql'), wp_timezone()))->getTimestamp();
    }

    private function resolve_timezone_for_client(): string
    {
        if (function_exists('wp_timezone_string')) {
            $tz = (string) wp_timezone_string();
            if ($tz !== '') {
                return $tz;
            }
        }

        $tz = (string) get_option('timezone_string');
        if ($tz !== '') {
            return $tz;
        }

        $offset = (float) get_option('gmt_offset', 0);
        $sign = $offset >= 0 ? '+' : '-';
        $abs = abs($offset);
        $hours = (int) floor($abs);
        $minutes = (int) round(($abs - $hours) * 60);
        return sprintf('GMT%s%02d:%02d', $sign, $hours, $minutes);
    }
}
