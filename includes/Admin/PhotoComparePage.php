<?php
namespace HLCC\Admin;

use HLCC\Support\Security;

if (!defined('ABSPATH')) exit;

final class PhotoComparePage {
    public static function register(): void {
        add_submenu_page(
            'hlcc-customers',
            '疗程图片对比',
            '疗程图片对比',
            'edit_posts',
            'hlcc-photo-compare',
            [self::class, 'render']
        );
    }

    public static function render(): void {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/photo-compare.php';
    }
}
