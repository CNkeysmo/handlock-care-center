<?php
namespace HLCC\Core;

use HLCC\Data\Db;

if (!defined('ABSPATH'))
    exit;

final class Activator
{
    public static function activate(): void
    {
        // Roles first
        Capabilities::register_roles();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $courses = Db::table('courses');
        $treatment_photos = Db::table('treatment_photos');
        $care_day = Db::table('care_day_contents');
        $care_phase = Db::table('care_phase_contents');
        $tutorials = Db::table('tutorials');
        $steps = Db::table('tutorial_steps');
        $settings = Db::table('settings');

        $sql = [];

        $sql[] = "CREATE TABLE {$courses} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            project_key VARCHAR(50) NOT NULL,
            procedure_date DATE NULL,
            procedure_datetime DATETIME NULL,
            note VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            phase_override VARCHAR(20) NULL,
            phase_override_by BIGINT UNSIGNED NULL,
            phase_override_at DATETIME NULL,
            custom_cycle_days INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY user_active (user_id, is_active),
            KEY project_key (project_key)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$care_day} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_key VARCHAR(50) NOT NULL,
            day_index TINYINT UNSIGNED NOT NULL,
            title VARCHAR(120) NULL,
            body TEXT NULL,
            key_points TEXT NULL,
            taboo_title VARCHAR(60) NULL,
            taboo_body TEXT NULL,
            footer_note VARCHAR(255) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_project_day (project_key, day_index)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$care_phase} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            phase_key VARCHAR(20) NOT NULL,
            title VARCHAR(120) NULL,
            body TEXT NULL,
            key_points TEXT NULL,
            taboo_title VARCHAR(60) NULL,
            taboo_body TEXT NULL,
            footer_note VARCHAR(255) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_phase (phase_key)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$tutorials} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_key VARCHAR(50) NOT NULL,
            phase_key VARCHAR(20) NOT NULL,
            title VARCHAR(120) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_project_phase (project_key, phase_key)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$steps} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tutorial_id BIGINT UNSIGNED NOT NULL,
            step_order INT UNSIGNED NOT NULL,
            step_title VARCHAR(120) NULL,
            step_text TEXT NULL,
            video_url TEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY tutorial_order (tutorial_id, step_order)
        ) {$charset_collate};";


        $sql[] = "CREATE TABLE {$treatment_photos} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NULL,
    attachment_id BIGINT UNSIGNED NOT NULL,
    shot_at DATETIME NOT NULL,
    shot_index INT UNSIGNED NOT NULL DEFAULT 1,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY user_course (user_id, course_id),
    KEY user_shot (user_id, shot_at)
) {$charset_collate};";

        $sql[] = "CREATE TABLE {$settings} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(80) NOT NULL,
            setting_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_key (setting_key)
        ) {$charset_collate};";

        foreach ($sql as $q) {
            dbDelta($q);
        }

        // store schema/build
        $now = current_time('mysql');
        $wpdb->replace($settings, [
            'setting_key' => 'schema_version',
            'setting_value' => '1',
            'updated_at' => $now
        ], ['%s', '%s', '%s']);
        $wpdb->replace($settings, [
            'setting_key' => 'build_id',
            'setting_value' => HLCC_BUILD_ID,
            'updated_at' => $now
        ], ['%s', '%s', '%s']);

        // v8.8.1: Run database migrations
        self::run_migrations();

        // Seed phase templates rows if missing
        $phases = ['scab', 'recovery'];
        foreach ($phases as $pk) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$care_phase} (phase_key, updated_at) VALUES (%s, %s)",
                $pk,
                $now
            ));
        }

        // Seed day contents rows for default projects day0-5
        $projects = ['tattoo', 'brow', 'scar'];
        for ($d = 0; $d <= 5; $d++) {
            foreach ($projects as $proj) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$care_day} (project_key, day_index, updated_at) VALUES (%s, %d, %s)",
                    $proj,
                    $d,
                    $now
                ));
            }
        }

        // Seed tutorial shells (project x phase)
        $tutorial_phases = ['inflammation', 'scab', 'recovery'];
        foreach ($projects as $proj) {
            foreach ($tutorial_phases as $ph) {
                $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$tutorials} (project_key, phase_key, updated_at) VALUES (%s, %s, %s)",
                    $proj,
                    $ph,
                    $now
                ));
            }
        }
        // v8.3.54: Migrate sensitive_words from table to wp_options if needed
        $old_words = $wpdb->get_var("SELECT setting_value FROM {$settings} WHERE setting_key = 'sensitive_words'");
        if (!empty($old_words) && empty(get_option('hlcc_sensitive_words'))) {
            update_option('hlcc_sensitive_words', $old_words);
        }

        // v8.8.1: Run database migrations
        self::run_migrations();
    }

    /**
     * Run database migrations
     */
    private static function run_migrations(): void
    {
        // Nested reply support migration
        if (class_exists('HLCC\Data\Migrations\Add_Nested_Reply_Support')) {
            $migration = new \HLCC\Data\Migrations\Add_Nested_Reply_Support();
            $migration->up();
        }

        // Read status migration
        if (class_exists('HLCC\Data\Migrations\Add_Read_Status')) {
            $migration = new \HLCC\Data\Migrations\Add_Read_Status();
            $migration->up();
        }

        // Wiki tables migration (v9.0.0)
        if (class_exists('HLCC\\Data\\Migrations\\Create_Wiki_Tables')) {
            $migration = new \HLCC\Data\Migrations\Create_Wiki_Tables();
            $migration->up();
        }

        // Mobile sessions table migration
        if (class_exists('HLCC\\Data\\Migrations\\Create_Mobile_Sessions_Table')) {
            $migration = new \HLCC\Data\Migrations\Create_Mobile_Sessions_Table();
            $migration->up();
        }

    }

    /**
     * Check and run migrations (called on init hook)
     * This ensures migrations run even if plugin was already activated
     */
    public static function check_and_run_migrations(): void
    {
        // Check if migrations have already run
        $migrations_run = get_option('hlcc_migrations_run', []);
        $current_migrations = [
            'create_mobile_sessions_table' => '9.2.48',
        ];

        foreach ($current_migrations as $migration_key => $version) {
            if (isset($migrations_run[$migration_key])) {
                continue; // Already run
            }

            // Run migration
            $success = false;
            if ($migration_key === 'create_mobile_sessions_table' && class_exists('HLCC\\Data\\Migrations\\Create_Mobile_Sessions_Table')) {
                $migration = new \HLCC\Data\Migrations\Create_Mobile_Sessions_Table();
                $success = $migration->up();
            }

            // Mark as run
            if ($success) {
                $migrations_run[$migration_key] = $version;
                update_option('hlcc_migrations_run', $migrations_run);
            }
        }
    }
}
