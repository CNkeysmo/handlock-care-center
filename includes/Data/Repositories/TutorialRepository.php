<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH')) exit;

final class TutorialRepository {
    private string $tutorials;
    private string $steps;

    public function __construct() {
        $this->tutorials = Db::table('tutorials');
        $this->steps = Db::table('tutorial_steps');
    }

    public function get_tutorial(string $project_key, string $phase_key): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tutorials} WHERE project_key=%s AND phase_key=%s",
            $project_key, $phase_key
        ), ARRAY_A);
        return $row ?: null;
    }

    public function update_tutorial_title(int $tutorial_id, ?string $title): void {
        global $wpdb;
        $wpdb->update($this->tutorials, [
            'title' => $title,
            'updated_at' => current_time('mysql')
        ], ['id' => $tutorial_id], ['%s','%s'], ['%d']);
    }

    public function list_steps(int $tutorial_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->steps} WHERE tutorial_id=%d ORDER BY step_order ASC, id ASC",
            $tutorial_id
        ), ARRAY_A) ?: [];
    }

    public function insert_step(int $tutorial_id, int $step_order): int {
        global $wpdb;
        $wpdb->insert($this->steps, [
            'tutorial_id' => $tutorial_id,
            'step_order' => $step_order,
            'step_title' => '',
            'step_text' => '',
            'video_url' => '',
            'updated_at' => current_time('mysql'),
        ], ['%d','%d','%s','%s','%s','%s']);
        return (int)$wpdb->insert_id;
    }

    public function update_step(int $id, array $fields): void {
        global $wpdb;
        $fields['updated_at'] = current_time('mysql');
        $wpdb->update($this->steps, $fields, ['id' => $id]);
    }

    public function delete_step(int $id): void {
        global $wpdb;
        $wpdb->delete($this->steps, ['id' => $id], ['%d']);
    }

    public function move_step(int $tutorial_id, int $step_id, string $dir): void {
        $steps = $this->list_steps($tutorial_id);
        $idx = -1;
        foreach ($steps as $i => $s) {
            if ((int)$s['id'] === $step_id) { $idx = (int)$i; break; }
        }
        if ($idx < 0) return;
        if ($dir === 'up' && $idx === 0) return;
        if ($dir === 'down' && $idx === count($steps) - 1) return;

        $j = ($dir === 'up') ? $idx - 1 : $idx + 1;
        $a = $steps[$idx];
        $b = $steps[$j];

        global $wpdb;
        $now = current_time('mysql');
        $wpdb->update($this->steps, ['step_order' => (int)$b['step_order'], 'updated_at' => $now], ['id' => (int)$a['id']], ['%d','%s'], ['%d']);
        $wpdb->update($this->steps, ['step_order' => (int)$a['step_order'], 'updated_at' => $now], ['id' => (int)$b['id']], ['%d','%s'], ['%d']);
    }
}
