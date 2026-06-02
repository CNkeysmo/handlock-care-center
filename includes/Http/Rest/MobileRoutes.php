<?php
namespace HLCC\Http\Rest;

use HLCC\App\Services\MobilePlanService;
use HLCC\App\Services\MobileSessionService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class MobileRoutes
{
    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('hlcc-mobile/v1', '/session/exchange', [
            'methods' => 'POST',
            'callback' => [self::class, 'exchange'],
            'permission_callback' => static function (): bool {
                return is_user_logged_in();
            },
        ]);

        register_rest_route('hlcc-mobile/v1', '/session/refresh', [
            'methods' => 'POST',
            'callback' => [self::class, 'refresh'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hlcc-mobile/v1', '/session/web-bootstrap', [
            'methods' => 'POST',
            'callback' => [self::class, 'web_bootstrap'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hlcc-mobile/v1', '/session/revoke', [
            'methods' => 'POST',
            'callback' => [self::class, 'revoke'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('hlcc-mobile/v1', '/session/admin-revoke', [
            'methods' => 'POST',
            'callback' => [self::class, 'admin_revoke'],
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('hlcc-mobile/v1', '/plan', [
            'methods' => 'GET',
            'callback' => [self::class, 'plan'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function exchange(WP_REST_Request $request)
    {
        if (!is_user_logged_in()) {
            return new WP_Error('hlcc_mobile_unauthorized', '请先登录', ['status' => 401]);
        }

        $deviceId = self::sanitize_device_id((string) $request->get_param('device_id'));
        if ($deviceId === '') {
            return new WP_Error('hlcc_mobile_bad_request', 'device_id 必填', ['status' => 400]);
        }

        $service = new MobileSessionService();
        $payload = $service->issue_for_user(
            get_current_user_id(),
            $deviceId,
            (string) $request->get_param('device_name'),
            (string) $request->get_param('app_version'),
            (string) ($request->get_param('platform') ?: 'android')
        );

        if (empty($payload)) {
            return new WP_Error('hlcc_mobile_issue_failed', '会话创建失败', ['status' => 500]);
        }

        return new WP_REST_Response($payload, 200);
    }

    public static function refresh(WP_REST_Request $request)
    {
        $refreshToken = trim((string) $request->get_param('refresh_token'));
        $deviceId = self::sanitize_device_id((string) $request->get_param('device_id'));

        if ($refreshToken === '' || $deviceId === '') {
            return new WP_Error('hlcc_mobile_bad_request', 'refresh_token 与 device_id 必填', ['status' => 400]);
        }

        $service = new MobileSessionService();
        $result = $service->refresh_result($refreshToken, $deviceId);
        if (empty($result['ok'])) {
            $errorCode = (string) ($result['error_code'] ?? MobileSessionService::ERROR_EXPIRED);
            return new WP_REST_Response([
                'error_code' => $errorCode,
                'message' => self::error_message_for($errorCode),
                'server_time' => current_time('mysql'),
            ], 401);
        }

        return new WP_REST_Response((array) ($result['payload'] ?? []), 200);
    }

    public static function web_bootstrap(WP_REST_Request $request)
    {
        $refreshToken = trim((string) $request->get_param('refresh_token'));
        $deviceId = self::sanitize_device_id((string) $request->get_param('device_id'));

        if ($refreshToken === '' || $deviceId === '') {
            return new WP_Error('hlcc_mobile_bad_request', 'refresh_token 与 device_id 必填', ['status' => 400]);
        }

        $service = new MobileSessionService();
        $result = $service->web_bootstrap($refreshToken, $deviceId);
        if (empty($result['ok'])) {
            $errorCode = (string) ($result['error_code'] ?? MobileSessionService::ERROR_EXPIRED);
            return new WP_REST_Response([
                'error_code' => $errorCode,
                'message' => self::error_message_for($errorCode),
                'web_authenticated' => false,
                'server_time' => current_time('mysql'),
            ], 401);
        }

        return new WP_REST_Response((array) ($result['payload'] ?? []), 200);
    }

    public static function revoke(WP_REST_Request $request)
    {
        $token = self::extract_bearer_token($request);
        if ($token === '') {
            return new WP_Error('hlcc_mobile_unauthorized', '缺少访问令牌', ['status' => 401]);
        }

        $service = new MobileSessionService();
        $ok = $service->revoke_by_access_token($token);

        if (!$ok) {
            return new WP_Error('hlcc_mobile_revoke_failed', '会话注销失败', ['status' => 400]);
        }

        return new WP_REST_Response([
            'revoked' => true,
            'server_time' => current_time('mysql'),
        ], 200);
    }

    public static function admin_revoke(WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error('hlcc_mobile_forbidden', '权限不足', ['status' => 403]);
        }

        $userId = (int) $request->get_param('user_id');
        $deviceId = self::sanitize_device_id((string) $request->get_param('device_id'));
        if ($userId <= 0 || $deviceId === '') {
            return new WP_Error('hlcc_mobile_bad_request', 'user_id 与 device_id 必填', ['status' => 400]);
        }

        $ok = (new MobileSessionService())->revoke_by_user_device($userId, $deviceId);

        return new WP_REST_Response([
            'revoked' => (bool) $ok,
            'user_id' => $userId,
            'device_id' => $deviceId,
            'server_time' => current_time('mysql'),
        ], $ok ? 200 : 400);
    }

    public static function plan(WP_REST_Request $request)
    {
        $token = self::extract_bearer_token($request);
        if ($token === '') {
            return new WP_Error('hlcc_mobile_unauthorized', '缺少访问令牌', ['status' => 401]);
        }

        $sessionService = new MobileSessionService();
        $auth = $sessionService->authenticate_access_token($token);
        if (!$auth) {
            return new WP_Error('hlcc_mobile_invalid_access', '访问令牌无效或已过期', ['status' => 401]);
        }

        $days = (int) $request->get_param('days');
        if ($days <= 0) {
            $days = 2;
        }

        $plan = (new MobilePlanService())->build_user_plan((int) $auth['user_id'], $days);

        return new WP_REST_Response($plan, 200);
    }

    private static function sanitize_device_id(string $deviceId): string
    {
        $deviceId = strtolower(trim($deviceId));
        $deviceId = preg_replace('/[^a-z0-9_-]/', '', $deviceId) ?: '';
        return substr($deviceId, 0, 128);
    }

    private static function extract_bearer_token(WP_REST_Request $request): string
    {
        $raw = (string) $request->get_header('authorization');
        if ($raw === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $raw = (string) wp_unslash($_SERVER['HTTP_AUTHORIZATION']);
        }

        if (stripos($raw, 'Bearer ') !== 0) {
            return '';
        }

        return trim(substr($raw, 7));
    }

    private static function error_message_for(string $errorCode): string
    {
        switch ($errorCode) {
            case MobileSessionService::ERROR_REVOKED:
                return '会话已撤销，请重新登录';
            case MobileSessionService::ERROR_DEVICE_MISMATCH:
                return '设备校验失败，请重新登录';
            case MobileSessionService::ERROR_USER_DISABLED:
                return '账号不可用，请联系管理员';
            case MobileSessionService::ERROR_EXPIRED:
            default:
                return '会话已过期，请重新登录';
        }
    }
}
