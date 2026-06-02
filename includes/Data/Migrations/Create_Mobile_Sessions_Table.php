<?php
namespace HLCC\Data\Migrations;

use HLCC\Data\Db;

if (!defined('ABSPATH')) {
    exit;
}

class Create_Mobile_Sessions_Table
{
    public function up(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;

        $charset = $wpdb->get_charset_collate();
        $table = Db::table('mobile_sessions');

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(128) NOT NULL,
            device_name VARCHAR(191) NULL,
            platform VARCHAR(20) NOT NULL DEFAULT 'android',
            app_version VARCHAR(50) NULL,
            access_token_hash CHAR(64) NOT NULL,
            refresh_token_hash CHAR(64) NOT NULL,
            access_expires_at DATETIME NOT NULL,
            refresh_expires_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_device (user_id, device_id),
            KEY idx_access_hash (access_token_hash),
            KEY idx_refresh_hash (refresh_token_hash),
            KEY idx_refresh_expires (refresh_expires_at),
            KEY idx_revoked (revoked_at)
        ) {$charset};";

        dbDelta($sql);

        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    public function down(): void
    {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS ' . Db::table('mobile_sessions'));
    }
}
