<?php
namespace HLCC\Admin;

use HLCC\Support\Security;

if (!defined('ABSPATH'))
    exit;

final class Menu
{
    public static function register(): void
    {
        add_menu_page(
            '行楽护理中心',
            '行楽护理中心',
            'edit_posts',
            'hlcc-customers',
            [self::class, 'render_customers'],
            'dashicons-heart',
            58
        );

        add_submenu_page(
            'hlcc-customers',
            '客户档案',
            '客户档案',
            'edit_posts',
            'hlcc-customers',
            [self::class, 'render_customers']
        );

        add_submenu_page(
            'hlcc-customers',
            '今日护理内容',
            '今日护理内容',
            'edit_posts',
            'hlcc-care-content',
            [self::class, 'render_care_content']
        );

        add_submenu_page(
            'hlcc-customers',
            '换药教程',
            '换药教程',
            'edit_posts',
            'hlcc-tutorials',
            [self::class, 'render_tutorials']
        );

        // 备份功能已移除 - 使用外部备份方案

        add_submenu_page(
            'hlcc-customers',
            '水印管理',
            '水印管理',
            'edit_posts',
            'hlcc-watermarks',
            [self::class, 'render_watermarks']
        );

        // 百科全书管理 (v9.0.0)
        add_submenu_page(
            'hlcc-customers',
            '自检百科',
            '自检百科',
            'edit_posts',
            'hlcc-wiki',
            [self::class, 'render_wiki']
        );

        // Hidden page for course edit
        add_submenu_page(
            null,
            '疗程管理',
            '疗程管理',
            'edit_posts',
            'hlcc-course-edit',
            [self::class, 'render_course_edit']
        );

        // Hidden page for course backfill (user has no courses)
        add_submenu_page(
            null,
            '补建疗程档案',
            '补建疗程档案',
            'edit_posts',
            'hlcc-course-backfill',
            [self::class, 'render_course_backfill']
        );
    }

    public static function render_customers(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/customers.php';
    }

    public static function render_course_edit(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/course-edit.php';
    }

    public static function render_care_content(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/care-content.php';
    }

    public static function render_tutorials(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/tutorial-editor.php';
    }

    // render_backup_restore() 已移除 - 使用外部备份方案

    public static function render_course_backfill(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/course-backfill.php';
    }

    public static function render_watermarks(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/watermarks.php';
    }

    public static function render_wiki(): void
    {
        Security::require_cap('edit_posts');
        require HLCC_PLUGIN_DIR . 'includes/Admin/Views/wiki.php';
    }
}
