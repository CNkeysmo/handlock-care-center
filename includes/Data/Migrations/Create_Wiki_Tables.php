<?php
namespace HLCC\Data\Migrations;

use HLCC\Data\Db;

if (!defined('ABSPATH'))
    exit;

/**
 * 创建百科全书相关表
 * 
 * @since 9.0.0
 */
final class Create_Wiki_Tables
{
    /**
     * 执行迁移
     */
    public function up(): bool
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $entries = Db::table('wiki_entries');
        $requests = Db::table('wiki_requests');

        // 检查表是否已存在
        $entries_exists = $wpdb->get_var("SHOW TABLES LIKE '{$entries}'") === $entries;
        $requests_exists = $wpdb->get_var("SHOW TABLES LIKE '{$requests}'") === $requests;

        if ($entries_exists && $requests_exists) {
            return true; // 已存在，跳过
        }

        $sql = [];

        // 词条主表
        if (!$entries_exists) {
            $sql[] = "CREATE TABLE {$entries} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                keywords TEXT NULL,
                content LONGTEXT NULL,
                category VARCHAR(50) NOT NULL DEFAULT 'general',
                status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
                view_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_status (status),
                KEY idx_category (category),
                KEY idx_view_count (view_count)
            ) {$charset_collate};";
        }

        // 客户请求表
        if (!$requests_exists) {
            $sql[] = "CREATE TABLE {$requests} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                question VARCHAR(500) NOT NULL,
                user_id BIGINT UNSIGNED NULL DEFAULT 0,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                linked_entry_id BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_status (status),
                KEY idx_user (user_id)
            ) {$charset_collate};";
        }

        foreach ($sql as $q) {
            dbDelta($q);
        }

        // 检查并添加 image_url 字段（v9.1.0 新增）
        $this->maybe_add_image_url_column($entries);

        return true;
    }

    /**
     * 检查并添加 image_url 列（兼容已存在的表）
     */
    private function maybe_add_image_url_column(string $table): void
    {
        global $wpdb;

        // 检查列是否已存在
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM {$table} LIKE %s",
                'image_url'
            )
        );

        if (empty($column_exists)) {
            // 添加 image_url 列
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN image_url VARCHAR(500) NULL AFTER content");
        }
    }

    /**
     * 回滚迁移（谨慎使用）
     */
    public function down(): bool
    {
        global $wpdb;

        $entries = Db::table('wiki_entries');
        $requests = Db::table('wiki_requests');

        $wpdb->query("DROP TABLE IF EXISTS {$entries}");
        $wpdb->query("DROP TABLE IF EXISTS {$requests}");

        return true;
    }
}
