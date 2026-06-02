<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH')) {
    exit;
}

final class MobileSessionRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = Db::table('mobile_sessions');
    }

    public function upsert_by_user_device(int $userId, string $deviceId, array $data): int
    {
        global $wpdb;

        $existing = $this->get_by_user_device($userId, $deviceId);
        $now = current_time('mysql');

        $row = [
            'user_id' => $userId,
            'device_id' => $deviceId,
            'device_name' => (string) ($data['device_name'] ?? ''),
            'platform' => (string) ($data['platform'] ?? 'android'),
            'app_version' => (string) ($data['app_version'] ?? ''),
            'access_token_hash' => (string) ($data['access_token_hash'] ?? ''),
            'refresh_token_hash' => (string) ($data['refresh_token_hash'] ?? ''),
            'access_expires_at' => (string) ($data['access_expires_at'] ?? $now),
            'refresh_expires_at' => (string) ($data['refresh_expires_at'] ?? $now),
            'last_seen_at' => (string) ($data['last_seen_at'] ?? $now),
            'revoked_at' => $data['revoked_at'] ?? null,
            'updated_at' => $now,
        ];

        if ($existing) {
            $wpdb->update(
                $this->table,
                $row,
                ['id' => (int) $existing->id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
            return (int) $existing->id;
        }

        $row['created_at'] = $now;
        $wpdb->insert(
            $this->table,
            $row,
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    public function get_by_user_device(int $userId, string $deviceId): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND device_id = %s LIMIT 1",
            $userId,
            $deviceId
        ));

        return $row ?: null;
    }

    public function get_by_access_hash(string $accessHash): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE access_token_hash = %s LIMIT 1",
            $accessHash
        ));

        return $row ?: null;
    }

    public function get_by_refresh_hash(string $refreshHash): ?object
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE refresh_token_hash = %s LIMIT 1",
            $refreshHash
        ));

        return $row ?: null;
    }

    public function revoke_by_id(int $id): bool
    {
        global $wpdb;
        $result = $wpdb->update(
            $this->table,
            [
                'revoked_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    public function delete_by_user_device(int $userId, string $deviceId): bool
    {
        global $wpdb;
        $result = $wpdb->delete($this->table, ['user_id' => $userId, 'device_id' => $deviceId], ['%d', '%s']);
        return $result !== false;
    }

    public function touch_last_seen(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $this->table,
            [
                'last_seen_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function list_android_user_activity_paginated(int $page = 1, int $perPage = 20, string $search = ''): array
    {
        global $wpdb;

        $page = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset = ($page - 1) * $perPage;
        $search = trim($search);

        $where = ['ms.platform = %s'];
        $params = ['android'];

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            if (ctype_digit($search)) {
                $where[] = '(u.ID = %d OR u.user_login LIKE %s OR u.user_email LIKE %s)';
                $params[] = (int) $search;
                $params[] = $like;
                $params[] = $like;
            } else {
                $where[] = '(u.user_login LIKE %s OR u.user_email LIKE %s)';
                $params[] = $like;
                $params[] = $like;
            }
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $totalSql = "
            SELECT COUNT(DISTINCT ms.user_id)
            FROM {$this->table} ms
            INNER JOIN {$wpdb->users} u ON u.ID = ms.user_id
            {$whereSql}
        ";
        $total = (int) $wpdb->get_var($wpdb->prepare($totalSql, ...$params));

        $listSql = "
            SELECT
                ms.user_id,
                u.user_login AS user_name,
                u.user_email,
                COUNT(*) AS device_count,
                MAX(ms.last_seen_at) AS last_seen_at
            FROM {$this->table} ms
            INNER JOIN {$wpdb->users} u ON u.ID = ms.user_id
            {$whereSql}
            GROUP BY ms.user_id, u.user_login, u.user_email
            ORDER BY COALESCE(MAX(ms.last_seen_at), '1970-01-01 00:00:00') DESC, ms.user_id DESC
            LIMIT %d OFFSET %d
        ";
        $listParams = array_merge($params, [$perPage, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($listSql, ...$listParams), ARRAY_A) ?: [];

        $tz = wp_timezone();
        $nowTs = (new \DateTimeImmutable(current_time('mysql'), $tz))->getTimestamp();
        $items = [];
        foreach ($rows as $row) {
            $lastSeenRaw = isset($row['last_seen_at']) ? (string) $row['last_seen_at'] : '';
            $lastSeenTs = false;
            if ($lastSeenRaw !== '') {
                try {
                    $lastSeenTs = (new \DateTimeImmutable($lastSeenRaw, $tz))->getTimestamp();
                } catch (\Throwable $e) {
                    $lastSeenTs = false;
                }
            }

            $items[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => (string) ($row['user_name'] ?? ''),
                'user_email' => (string) ($row['user_email'] ?? ''),
                'device_count' => (int) ($row['device_count'] ?? 0),
                'last_seen_at' => $lastSeenRaw !== '' ? $lastSeenRaw : null,
                'is_active_24h' => ($lastSeenTs !== false) && (($nowTs - $lastSeenTs) <= 86400),
            ];
        }

        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        $totalPages = max(1, $totalPages);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    public function list_android_devices_by_user(int $userId): array
    {
        global $wpdb;

        if ($userId <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT
                    id,
                    device_id,
                    device_name,
                    app_version,
                    last_seen_at,
                    access_expires_at,
                    refresh_expires_at,
                    revoked_at,
                    created_at,
                    updated_at
                FROM {$this->table}
                WHERE user_id = %d
                  AND platform = %s
                ORDER BY COALESCE(last_seen_at, '1970-01-01 00:00:00') DESC, id DESC
                ",
                $userId,
                'android'
            ),
            ARRAY_A
        ) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'device_id' => (string) ($row['device_id'] ?? ''),
                'device_name' => (string) ($row['device_name'] ?? ''),
                'app_version' => (string) ($row['app_version'] ?? ''),
                'last_seen_at' => !empty($row['last_seen_at']) ? (string) $row['last_seen_at'] : null,
                'access_expires_at' => !empty($row['access_expires_at']) ? (string) $row['access_expires_at'] : null,
                'refresh_expires_at' => !empty($row['refresh_expires_at']) ? (string) $row['refresh_expires_at'] : null,
                'revoked_at' => !empty($row['revoked_at']) ? (string) $row['revoked_at'] : null,
            ];
        }

        return $items;
    }
}
