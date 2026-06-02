<?php
namespace HLCC\App\Services;

use HLCC\Data\Db;

if (!defined('ABSPATH')) exit;

/**
 * Plugin-scoped backup & restore for HLCC.
 *
 * Design goals:
 * - Only touches HLCC tables + HLCC settings/options we own.
 * - Defensive validation; restore requires explicit confirmation.
 * - No frontend impact.
 */
final class BackupService {
    /** Tables (without wp prefix) that belong to this plugin. */
    private const TABLES = [
        'courses',
        'care_day_contents',
        'care_phase_contents',
        'tutorials',
        'tutorial_steps',
        'treatment_photos',
        'settings',
    ];

    private static function now_id(): string {
        return gmdate('Ymd_His');
    }

    private static function uploads_dir(): string {
        $u = wp_upload_dir();
        $dir = rtrim((string)($u['basedir'] ?? ''), '/');
        if (!$dir) {
            $dir = WP_CONTENT_DIR . '/uploads';
        }
        $dir .= '/hlcc-backups';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }

    private static function tmp_dir(): string {
        $dir = self::uploads_dir() . '/tmp';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        return $dir;
    }


    /**
     * Collect media file paths for treatment photos (unique attachment_ids).
     *
     * @return array<int,string> [attachment_id => absolute_path]
     */
    private static function collect_media_paths(): array {
        global $wpdb;

        $paths = [];

        $table = Db::table('treatment_photos');

        // Ensure table exists (for safety on legacy installs)
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT attachment_id FROM %i WHERE attachment_id IS NOT NULL",
            $table
        ), ARRAY_A) ?: [];
        $ids = [];
        foreach ($rows as $row) {
            $aid = isset($row['attachment_id']) ? (int)$row['attachment_id'] : 0;
            if ($aid > 0) {
                $ids[$aid] = true;
            }
        }

        if (!$ids) {
            return [];
        }

        foreach (array_keys($ids) as $aid) {
            $file = get_attached_file($aid, true);
            if (!$file || !is_string($file)) {
                continue;
            }
            if (!file_exists($file)) {
                continue;
            }
            $paths[$aid] = $file;
        }

        return $paths;
    }

    public static function make_backup_zip(string $purpose = 'manual'): array {
        global $wpdb;

        $stamp = self::now_id();
        $backup_id = $purpose . '_' . $stamp;
        $tmp = self::tmp_dir() . '/' . $backup_id;
        if (!is_dir($tmp)) {
            wp_mkdir_p($tmp);
        }

        // 1) schema.sql
        $schema_sql = "";
        $table_map = [];
        foreach (self::TABLES as $t) {
            $full = Db::table($t);
            $table_map[$t] = $full;
            $row = $wpdb->get_row($wpdb->prepare("SHOW CREATE TABLE %i", $full), ARRAY_A);
            if (!empty($row['Create Table'])) {
                $schema_sql .= $row['Create Table'] . ";\n\n";
            }
        }
        file_put_contents($tmp . '/schema.sql', $schema_sql);

        // 2) data.json
        $data = [
            'tables' => [],
            'options' => [],
            'usermeta' => [],
        ];
        foreach (self::TABLES as $t) {
            $full = $table_map[$t];
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i", $full), ARRAY_A) ?: [];
            $data['tables'][$t] = $rows;
        }

        // Options we own (reserved for future; keep prefix strict)
        $opts = $wpdb->get_results(
            "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'hlcc\_%'",
            ARRAY_A
        ) ?: [];
        $data['options'] = $opts;

        // User meta we own (reserved for future; keep prefix strict)
        $um = $wpdb->get_results(
            "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE 'hlcc\_%'",
            ARRAY_A
        ) ?: [];
        $data['usermeta'] = $um;
        file_put_contents($tmp . '/data.json', wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 2.5) media files used by HLCC (treatment photos)
        $media_manifest = [];
        $media_paths = self::collect_media_paths();
        if (!empty($media_paths)) {
            $media_root = $tmp . '/media/originals';
            $uploads = wp_get_upload_dir();
            $base_dir = trailingslashit($uploads['basedir']);

            foreach ($media_paths as $attachment_id => $full_path) {
                if (strpos($full_path, $base_dir) === 0) {
                    $upload_rel = ltrim(substr($full_path, strlen($base_dir)), '/');
                } else {
                    $upload_rel = basename($full_path);
                }

                $target = $media_root . '/' . $upload_rel;
                $target_dir = dirname($target);
                if (!is_dir($target_dir)) {
                    wp_mkdir_p($target_dir);
                }

                if (@copy($full_path, $target)) {
                    $media_manifest[] = [
                        'attachment_id' => (int)$attachment_id,
                        'upload_rel'    => $upload_rel,
                        'relative_path' => 'media/originals/' . $upload_rel,
                    ];
                }
            }
        }


        // 3) manifest.json
        $manifest = [
            'plugin' => 'handlock-care-center',
            'hlcc_version' => defined('HLCC_VERSION') ? HLCC_VERSION : null,
            'hlcc_build_id' => defined('HLCC_BUILD_ID') ? HLCC_BUILD_ID : null,
            'site_url' => site_url(),
            'generated_at_gmt' => gmdate('c'),
            'purpose' => $purpose,
            'tables' => self::TABLES,
            'wp_prefix' => $wpdb->prefix,
            'media' => $media_manifest,
        ];
        file_put_contents($tmp . '/manifest.json', wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 4) checksums.json
        $checksums = [];
        foreach (['manifest.json','schema.sql','data.json'] as $fn) {
            $checksums[$fn] = hash_file('sha256', $tmp . '/' . $fn);
        }
        file_put_contents($tmp . '/checksums.json', wp_json_encode($checksums, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 5) zip
        $zip_name = 'handlock-backup_' . $backup_id . '.zip';
        $zip_path = self::uploads_dir() . '/' . $zip_name;
        $zip = new \ZipArchive();
        if ($zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('无法创建备份文件');
        }

        // Add all files under temp dir (schema/data/manifest/checksums + media/)
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $filePath = $fileInfo->getPathname();
            if (!is_file($filePath)) {
                continue;
            }
            $localName = ltrim(str_replace($tmp . '/', '', $filePath), '/');
            $zip->addFile($filePath, $localName);
        }
        $zip->close();

        // Cleanup temp dir (keep zip only)
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getPathname());
            } else {
                @unlink($fileInfo->getPathname());
            }
        }
        @rmdir($tmp);

        return [
            'zip_path' => $zip_path,
            'zip_name' => $zip_name,
            'backup_id' => $backup_id,
        ];
    }

    public static function verify_backup_zip(string $zip_path): array {
        if (!file_exists($zip_path)) {
            return ['ok' => false, 'error' => '备份文件不存在'];
        }
        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return ['ok' => false, 'error' => '无法打开备份文件'];
        }
        $need = ['manifest.json','checksums.json','data.json','schema.sql'];
        foreach ($need as $n) {
            if ($zip->locateName($n) === false) {
                $zip->close();
                return ['ok' => false, 'error' => '备份文件缺少：' . $n];
            }
        }
        $manifest = json_decode($zip->getFromName('manifest.json') ?: 'null', true);
        $checksums = json_decode($zip->getFromName('checksums.json') ?: 'null', true);
        if (!is_array($manifest) || !is_array($checksums)) {
            $zip->close();
            return ['ok' => false, 'error' => '备份文件格式不正确'];
        }
        if (($manifest['plugin'] ?? '') !== 'handlock-care-center') {
            $zip->close();
            return ['ok' => false, 'error' => '备份文件不属于本插件'];
        }
        // checksum verify
        foreach (['manifest.json','schema.sql','data.json'] as $fn) {
            $content = $zip->getFromName($fn);
            if ($content === false) {
                $zip->close();
                return ['ok' => false, 'error' => '无法读取：' . $fn];
            }
            $sum = hash('sha256', $content);
            if (!isset($checksums[$fn]) || $checksums[$fn] !== $sum) {
                $zip->close();
                return ['ok' => false, 'error' => '校验失败：' . $fn];
            }
        }
        $zip->close();
        return ['ok' => true, 'manifest' => $manifest];
    }

    public static function restore_from_zip(string $zip_path): array {
        global $wpdb;

        $v = self::verify_backup_zip($zip_path);
        if (!$v['ok']) return $v;

        // Create pre-restore snapshot (server-side) for rollback.
        $snapshot = self::make_backup_zip('pre_restore');

        $zip = new \ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return ['ok' => false, 'error' => '无法打开备份文件'];
        }
        $data = json_decode($zip->getFromName('data.json') ?: 'null', true);
        if (!is_array($data) || !isset($data['tables'])) {
            $zip->close();
            return ['ok' => false, 'error' => '备份数据损坏'];
        }

        // Only restore our scope.
        $restored = [
            'tables' => [],
            'options' => 0,
            'usermeta' => 0,
        ];

        // Restore tables
        foreach (self::TABLES as $t) {
            $full = Db::table($t);
            $wpdb->query($wpdb->prepare("TRUNCATE TABLE %i", $full));
            $rows = $data['tables'][$t] ?? [];
            $count = 0;
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    // Use replace to be tolerant.
                    $wpdb->replace($full, $row);
                    $count++;
                }
            }
            $restored['tables'][$t] = $count;
        }

        // Restore options (hlcc_*)
        $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE option_name LIKE %s", $wpdb->options, 'hlcc\_%'));
        $opt_rows = $data['options'] ?? [];
        if (is_array($opt_rows)) {
            foreach ($opt_rows as $o) {
                if (!is_array($o)) continue;
                $name = (string)($o['option_name'] ?? '');
                if (!$name || strpos($name, 'hlcc_') !== 0) continue;
                $wpdb->insert($wpdb->options, [
                    'option_name' => $name,
                    'option_value' => (string)($o['option_value'] ?? ''),
                    'autoload' => (string)($o['autoload'] ?? 'no'),
                ], ['%s','%s','%s']);
                $restored['options']++;
            }
        }

        // Restore user meta (hlcc_*)
        $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE meta_key LIKE %s", $wpdb->usermeta, 'hlcc\_%'));
        $um_rows = $data['usermeta'] ?? [];
        if (is_array($um_rows)) {
            foreach ($um_rows as $m) {
                if (!is_array($m)) continue;
                $key = (string)($m['meta_key'] ?? '');
                if (!$key || strpos($key, 'hlcc_') !== 0) continue;
                $wpdb->insert($wpdb->usermeta, [
                    'user_id' => (int)($m['user_id'] ?? 0),
                    'meta_key' => $key,
                    'meta_value' => (string)($m['meta_value'] ?? ''),
                ], ['%d','%s','%s']);
                $restored['usermeta']++;
            }
        }


        // Restore media files used by treatment photos (if present in manifest)
        $manifest = $v['manifest'] ?? null;
        if (is_array($manifest) && !empty($manifest['media']) && is_array($manifest['media'])) {
            $uploads = wp_get_upload_dir();
            $base_dir = trailingslashit($uploads['basedir']);

            foreach ($manifest['media'] as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $rel = isset($m['relative_path']) ? (string)$m['relative_path'] : '';
                $upload_rel = isset($m['upload_rel']) ? (string)$m['upload_rel'] : '';
                $attachment_id = isset($m['attachment_id']) ? (int)$m['attachment_id'] : 0;
                if (!$rel || !$upload_rel || $attachment_id <= 0) {
                    continue;
                }

                $content = $zip->getFromName($rel);
                if ($content === false) {
                    continue;
                }

                $target = $base_dir . $upload_rel;
                $target_dir = dirname($target);
                if (!is_dir($target_dir)) {
                    wp_mkdir_p($target_dir);
                }

                if (file_put_contents($target, $content) !== false) {
                    // Sync attachment meta to point to restored file.
                    update_attached_file($attachment_id, $target);
                }
            }
        }

        $zip->close();

        return [
            'ok' => true,
            'restored' => $restored,
            'snapshot' => $snapshot,
            'manifest' => $v['manifest'] ?? null,
        ];
    }
}
