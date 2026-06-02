<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH')) exit;

final class CareContentRepository {
    private string $day_table;
    private string $phase_table;

    public function __construct() {
        $this->day_table = Db::table('care_day_contents');
        $this->phase_table = Db::table('care_phase_contents');
    }

    public function get_day(string $project_key, int $day_index): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->day_table} WHERE project_key=%s AND day_index=%d",
            $project_key, $day_index
        ), ARRAY_A);
        return $row ?: null;
    }

    public function upsert_day(string $project_key, int $day_index, array $fields): void {
        global $wpdb;
        $now = current_time('mysql');
        $fields['updated_at'] = $now;
        $existing = $this->get_day($project_key, $day_index);
        if ($existing) {
            $wpdb->update($this->day_table, $fields, ['id' => (int)$existing['id']]);
        } else {
            $fields['project_key'] = $project_key;
            $fields['day_index'] = $day_index;
            $wpdb->insert($this->day_table, $fields);
        }
    }

    public function get_phase(string $phase_key): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->phase_table} WHERE phase_key=%s",
            $phase_key
        ), ARRAY_A);
        return $row ?: null;
    }

    public function upsert_phase(string $phase_key, array $fields): void {
        global $wpdb;
        $now = current_time('mysql');
        $fields['updated_at'] = $now;
        $existing = $this->get_phase($phase_key);
        if ($existing) {
            $wpdb->update($this->phase_table, $fields, ['id' => (int)$existing['id']]);
        } else {
            $fields['phase_key'] = $phase_key;
            $wpdb->insert($this->phase_table, $fields);
        }
    }
}
