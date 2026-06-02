<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Security;
use HLCC\App\Services\BackupService;

if (!defined('ABSPATH'))
    exit;

/**
 * 备份恢复相关的 HTTP 处理器
 * 
 * 从 PostHandlers.php 拆分，包含备份生成、验证、恢复等
 * @since 8.9.0
 */
final class BackupHandlers
{
    /**
     * 注册备份相关的 Hooks
     */
    public static function register(): void
    {
        add_action('admin_post_hlcc_backup_generate', [self::class, 'generate']);
        add_action('admin_post_hlcc_backup_verify', [self::class, 'verify']);
        add_action('admin_post_hlcc_backup_restore', [self::class, 'restore']);
    }

    /**
     * 生成备份
     */
    public static function generate(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_backup_generate');
        $result = BackupService::make_backup_zip('manual');
        $path = $result['zip_path'];
        if (!file_exists($path))
            wp_die('备份文件生成失败');
        // 流式下载
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . $result['zip_name']);
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    /**
     * 验证备份文件
     */
    public static function verify(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_backup_verify');
        if (empty($_FILES['backup_zip']['tmp_name'])) {
            HandlerHelpers::back(admin_url('admin.php?page=hlcc-backup-restore&err=1'));
        }
        $tmp_name = (string) $_FILES['backup_zip']['tmp_name'];
        $name = sanitize_file_name((string) ($_FILES['backup_zip']['name'] ?? 'backup.zip'));
        $upload_dir = wp_upload_dir();
        $dir = rtrim((string) $upload_dir['basedir'], '/') . '/hlcc-backups/uploads';
        if (!is_dir($dir))
            wp_mkdir_p($dir);
        $token = wp_generate_password(12, false, false);
        $dest = $dir . '/' . $token . '_' . $name;
        if (!@move_uploaded_file($tmp_name, $dest)) {
            HandlerHelpers::back(admin_url('admin.php?page=hlcc-backup-restore&err=2'));
        }
        $v = BackupService::verify_backup_zip($dest);
        set_transient('hlcc_restore_' . $token, ['file' => $dest, 'verify' => $v], 30 * MINUTE_IN_SECONDS);
        HandlerHelpers::back(admin_url('admin.php?page=hlcc-backup-restore&token=' . $token));
    }

    /**
     * 恢复备份
     */
    public static function restore(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_backup_restore');
        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        $confirm = sanitize_text_field(wp_unslash($_POST['confirm'] ?? ''));
        if (!$token)
            wp_die('参数错误');
        $payload = get_transient('hlcc_restore_' . $token);
        if (!is_array($payload) || empty($payload['file'])) {
            wp_die('还原会话已过期，请重新上传备份文件');
        }
        if (strtoupper($confirm) !== 'RESTORE') {
            HandlerHelpers::back(admin_url('admin.php?page=hlcc-backup-restore&token=' . $token . '&need=1'));
        }
        $file = (string) $payload['file'];
        $r = BackupService::restore_from_zip($file);
        // 保留 transient 用于显示恢复信息
        set_transient('hlcc_restore_done_' . $token, $r, 30 * MINUTE_IN_SECONDS);
        delete_transient('hlcc_restore_' . $token);
        HandlerHelpers::back(admin_url('admin.php?page=hlcc-backup-restore&done=' . $token));
    }
}
