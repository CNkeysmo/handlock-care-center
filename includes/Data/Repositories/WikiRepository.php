<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH'))
    exit;

/**
 * 百科全书数据仓库
 * 
 * 管理词条和客户请求的 CRUD 操作
 * @since 9.0.0
 */
final class WikiRepository
{
    /**
     * 获取词条表名
     */
    private function entries_table(): string
    {
        return Db::table('wiki_entries');
    }

    /**
     * 获取请求表名
     */
    private function requests_table(): string
    {
        return Db::table('wiki_requests');
    }

    // ==================== 词条搜索 ====================

    /**
     * 实时搜索词条（用于自动补全）
     * 
     * @param string $keyword 搜索关键词
     * @param int $limit 返回数量限制
     * @return array 匹配的词条列表
     */
    public function search(string $keyword, int $limit = 5): array
    {
        global $wpdb;
        $table = $this->entries_table();

        // 清理关键词
        $keyword = trim($keyword);
        if (empty($keyword)) {
            return [];
        }

        // 使用 LIKE 模糊匹配标题和关键词
        $like = '%' . $wpdb->esc_like($keyword) . '%';

        $sql = $wpdb->prepare(
            "SELECT id, title, keywords, category
             FROM {$table}
             WHERE status = 'published'
               AND (title LIKE %s OR keywords LIKE %s OR content LIKE %s)
             GROUP BY id
             ORDER BY view_count DESC, id DESC
             LIMIT %d",
            $like,
            $like,
            $like,
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        return $results ?: [];
    }

    /**
     * 获取单个词条详情
     */
    public function get(int $id): ?array
    {
        global $wpdb;
        $table = $this->entries_table();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * 增加词条浏览次数
     */
    public function increment_view(int $id): void
    {
        global $wpdb;
        $table = $this->entries_table();

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET view_count = view_count + 1 WHERE id = %d",
                $id
            )
        );
    }

    // ==================== 后台词条管理 ====================

    /**
     * 获取所有词条（后台列表）
     */
    public function list_all(int $limit = 50, int $offset = 0, string $status = ''): array
    {
        global $wpdb;
        $table = $this->entries_table();

        $where = '';
        if ($status !== '') {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        return $results ?: [];
    }

    /**
     * 获取词条总数
     */
    public function count(string $status = ''): int
    {
        global $wpdb;
        $table = $this->entries_table();

        $where = '';
        if ($status !== '') {
            $where = $wpdb->prepare(" WHERE status = %s", $status);
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");
    }

    /**
     * 创建词条
     */
    public function create(array $data): int
    {
        global $wpdb;
        $table = $this->entries_table();
        $now = current_time('mysql');

        $wpdb->insert($table, [
            'title' => sanitize_text_field($data['title'] ?? ''),
            'keywords' => sanitize_text_field($data['keywords'] ?? ''),
            'content' => wp_kses_post($data['content'] ?? ''),
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? 'general'),
            'status' => in_array($data['status'] ?? '', ['draft', 'published']) ? $data['status'] : 'published',
            'view_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * 更新词条
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;
        $table = $this->entries_table();

        $update_data = [
            'updated_at' => current_time('mysql'),
        ];

        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['keywords'])) {
            $update_data['keywords'] = sanitize_text_field($data['keywords']);
        }
        if (isset($data['content'])) {
            $update_data['content'] = wp_kses_post($data['content']);
        }
        if (isset($data['category'])) {
            $update_data['category'] = sanitize_text_field($data['category']);
        }
        if (isset($data['image_url'])) {
            $update_data['image_url'] = esc_url_raw($data['image_url']);
        }
        if (isset($data['status']) && in_array($data['status'], ['draft', 'published'])) {
            $update_data['status'] = $data['status'];
        }

        return $wpdb->update($table, $update_data, ['id' => $id]) !== false;
    }

    /**
     * 删除词条
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $table = $this->entries_table();

        return $wpdb->delete($table, ['id' => $id], ['%d']) !== false;
    }

    // ==================== 客户请求管理 ====================

    /**
     * 创建客户请求
     */
    public function create_request(string $question, int $user_id = 0): int
    {
        global $wpdb;
        $table = $this->requests_table();

        $wpdb->insert($table, [
            'question' => sanitize_text_field($question),
            'user_id' => $user_id,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
        ], ['%s', '%d', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    /**
     * 获取客户请求列表
     */
    public function list_requests(string $status = 'pending', int $limit = 50): array
    {
        global $wpdb;
        $table = $this->requests_table();

        $sql = $wpdb->prepare(
            "SELECT r.*, u.display_name as user_name 
             FROM {$table} r 
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID 
             WHERE r.status = %s 
             ORDER BY r.id DESC 
             LIMIT %d",
            $status,
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        return $results ?: [];
    }

    /**
     * 获取待处理请求数量
     */
    public function count_pending_requests(): int
    {
        global $wpdb;
        $table = $this->requests_table();

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending')
        );
    }

    /**
     * 审批请求（关联到词条）
     */
    public function approve_request(int $request_id, int $entry_id): bool
    {
        global $wpdb;
        $table = $this->requests_table();

        return $wpdb->update(
            $table,
            [
                'status' => 'approved',
                'linked_entry_id' => $entry_id,
            ],
            ['id' => $request_id],
            ['%s', '%d'],
            ['%d']
        ) !== false;
    }

    /**
     * 拒绝请求
     */
    public function reject_request(int $request_id): bool
    {
        global $wpdb;
        $table = $this->requests_table();

        return $wpdb->update(
            $table,
            ['status' => 'rejected'],
            ['id' => $request_id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * 获取单个请求详情
     */
    public function get_request(int $id): ?array
    {
        global $wpdb;
        $table = $this->requests_table();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    // ==================== 便捷方法 ====================

    /**
     * 获取所有词条（后台列表用）
     */
    public function get_all_entries(int $limit = 100, int $offset = 0): array
    {
        return $this->list_all($limit, $offset, '');
    }

    /**
     * 获取所有请求（后台列表用）
     */
    public function get_all_requests(string $status = 'pending', int $limit = 50, int $offset = 0): array
    {
        return $this->list_requests($status, $limit);
    }

    /**
     * 获取分类中文标签
     */
    public function get_category_label(string $category): string
    {
        $labels = [
            'general' => '基础知识',
            'symptom' => '症状判断',
            'care' => '护理方法',
            'faq' => '常见问题',
            'warning' => '注意事项',
        ];
        return $labels[$category] ?? '基础知识';
    }
}
