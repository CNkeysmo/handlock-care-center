<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH'))
    exit;

final class CourseRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = Db::table('courses');
    }

    public function list_by_user(int $user_id): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id=%d ORDER BY is_active DESC, id DESC",
            $user_id
        ), ARRAY_A) ?: [];
    }

    public function get_active(int $user_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id=%d AND is_active=1 ORDER BY id DESC LIMIT 1",
            $user_id
        ), ARRAY_A);
        return $row ?: null;
    }

    public function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id=%d",
            $id
        ), ARRAY_A);
        return $row ?: null;
    }

    /**
     * 获取全部活动疗程（含客户名称）
     */
    public function list_active_all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT c.*, u.display_name AS user_name
             FROM {$this->table} c
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
             WHERE c.is_active = 1
             ORDER BY c.updated_at DESC",
            ARRAY_A
        );

        return $rows ?: [];
    }

    public function set_active(int $user_id, int $course_id): void
    {
        global $wpdb;
        $wpdb->update($this->table, ['is_active' => 0], ['user_id' => $user_id], ['%d'], ['%d']);
        $wpdb->update($this->table, ['is_active' => 1, 'updated_at' => current_time('mysql')], ['id' => $course_id], ['%d', '%s'], ['%d']);
    }

    public function insert(array $data): int
    {
        global $wpdb;
        // IMPORTANT: Keep insertion order stable to match formats.
        $now = current_time('mysql');

        $row = [
            'user_id' => (int) ($data['user_id'] ?? 0),
            'project_key' => (string) ($data['project_key'] ?? 'tattoo'),
            'procedure_date' => (string) ($data['procedure_date'] ?? ''),
            'procedure_datetime' => $data['procedure_datetime'] ?? null, // Persist precise time
            'note' => array_key_exists('note', $data) ? $data['note'] : null,
            'is_active' => (int) ($data['is_active'] ?? 0),
            'phase_override' => $data['phase_override'] ?? null,
            'phase_override_by' => $data['phase_override_by'] ?? null,
            'phase_override_at' => $data['phase_override_at'] ?? null,
            'custom_cycle_days' => isset($data['custom_cycle_days']) && $data['custom_cycle_days'] > 0 ? (int) $data['custom_cycle_days'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // 防止同一客户、同一项目、同一操作日期被意外重复创建多条疗程记录：
        // 如果已经存在完全相同的记录，则直接返回该记录的 ID，而不是再插入一条。
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE user_id=%d AND project_key=%s AND procedure_date=%s AND IFNULL(note,'')=IFNULL(%s,'') LIMIT 1",
            $row['user_id'],
            $row['project_key'],
            $row['procedure_date'],
            $row['note']
        ));
        if ($existing_id) {
            return (int) $existing_id;
        }

        $wpdb->insert($this->table, $row, [
            '%d',
            '%s',
            '%s',
            '%s', // procedure_datetime
            '%s',
            '%d',
            '%s',
            '%d',
            '%s',
            '%d', // custom_cycle_days
            '%s',
            '%s'
        ]);
        return (int) $wpdb->insert_id;
    }


    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['id' => $id], ['%d']);
    }

    public function update(int $id, array $data): void
    {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        $wpdb->update($this->table, $data, ['id' => $id]);
    }
}
