<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH')) exit;

class TreatmentPhotoRepository {
    private string $table;

    public function __construct() {
        $this->table = Db::table('treatment_photos');
    }

    public function add(int $user_id, int $course_id, int $attachment_id, string $shot_at): int {
        global $wpdb;
        if ($user_id <= 0 || $attachment_id <= 0) {
            return 0;
        }
        $course_id = $course_id > 0 ? $course_id : null;

        $max_index = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT MAX(shot_index) FROM {$this->table} WHERE user_id = %d AND (course_id = %d OR (%d IS NULL AND course_id IS NULL))",
            $user_id,
            $course_id,
            $course_id
        ));
        $next_index = $max_index + 1;

        $row = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'attachment_id' => $attachment_id,
            'shot_at' => $shot_at,
            'shot_index' => $next_index,
            'note' => '',
            'created_at' => $shot_at,
        ];
        $ok = $wpdb->insert($this->table, $row, [
            '%d','%d','%d','%s','%d','%s','%s'
        ]);
        return $ok ? (int)$wpdb->insert_id : 0;
    }

    public function list_for_course(int $user_id, int $course_id, int $limit = 50): array {
        global $wpdb;
        if ($user_id <= 0) return [];
        $course_id = $course_id > 0 ? $course_id : null;

        if ($course_id === null) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d AND course_id IS NULL ORDER BY shot_index ASC, shot_at ASC, id ASC LIMIT %d",
                $user_id,
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE user_id = %d AND course_id = %d ORDER BY shot_index ASC, shot_at ASC, id ASC LIMIT %d",
                $user_id,
                $course_id,
                $limit
            );
        }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function first_and_latest(int $user_id, int $course_id): array {
        $list = $this->list_for_course($user_id, $course_id, 200);
        if (count($list) < 2) return [];
        $first = $list[0];
        $last = $list[count($list)-1];
        if ((int)$first['id'] === (int)$last['id'] && count($list) >= 2) {
            $last = $list[1];
        }
        return [
            'first' => $first,
            'latest' => $last,
        ];
    }
public function get(int $id): ?array {
    global $wpdb;
    if ($id <= 0) return null;
    $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id);
    $row = $wpdb->get_row($sql, ARRAY_A);
    return $row ?: null;
}

public function update_shot_at(int $id, string $shot_at): void {
    global $wpdb;
    if ($id <= 0) return;
    $wpdb->update($this->table, ['shot_at' => $shot_at], ['id' => $id], ['%s'], ['%d']);
}

public function delete(int $id): void {
    global $wpdb;
    if ($id <= 0) return;
    $wpdb->delete($this->table, ['id' => $id], ['%d']);
}

public function move(int $id, string $direction): void {
    global $wpdb;
    $row = $this->get($id);
    if (!$row) return;

    $user_id = (int)$row['user_id'];
    $course_id = isset($row['course_id']) ? (int)$row['course_id'] : 0;
    $list = $this->list_for_course($user_id, $course_id, 200);
    if (!$list || count($list) < 2) return;

    $index = null;
    foreach ($list as $i => $item) {
        if ((int)$item['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) return;

    if ($direction === 'up') {
        if ($index === 0) return;
        $other = $list[$index - 1];
    } else {
        if ($index === count($list) - 1) return;
        $other = $list[$index + 1];
    }

    $id_other = (int)$other['id'];
    $idx_this = (int)$row['shot_index'];
    $idx_other = (int)$other['shot_index'];

    $wpdb->update($this->table, ['shot_index' => $idx_other], ['id' => $id], ['%d'], ['%d']);
    $wpdb->update($this->table, ['shot_index' => $idx_this], ['id' => $id_other], ['%d'], ['%d']);
}

}
