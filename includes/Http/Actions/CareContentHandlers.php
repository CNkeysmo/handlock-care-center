<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Security;
use HLCC\Data\Repositories\CareContentRepository;

if (!defined('ABSPATH'))
    exit;

/**
 * 护理内容管理相关的 HTTP 处理器
 * 
 * 从 PostHandlers.php 拆分，包含按天/按阶段的护理内容保存
 * @since 8.9.0
 */
final class CareContentHandlers
{
    /**
     * 注册护理内容相关的 Hooks
     */
    public static function register(): void
    {
        add_action('admin_post_hlcc_save_care_day', [self::class, 'save_care_day']);
        add_action('admin_post_hlcc_save_care_phase', [self::class, 'save_care_phase']);
    }

    /**
     * 保存按天护理内容
     */
    public static function save_care_day(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_save_care_day');

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $day_index = (int) ($_POST['day_index'] ?? 0);

        $repo = new CareContentRepository();
        $repo->upsert_day($project_key, $day_index, [
            'title' => wp_kses_post(wp_unslash($_POST['title'] ?? '')),
            'body' => wp_kses_post(wp_unslash($_POST['body'] ?? '')),
            'key_points' => wp_kses_post(wp_unslash($_POST['key_points'] ?? '')),
            'taboo_title' => wp_kses_post(wp_unslash($_POST['taboo_title'] ?? '')),
            'taboo_body' => wp_kses_post(wp_unslash($_POST['taboo_body'] ?? '')),
            'footer_note' => sanitize_text_field(wp_unslash($_POST['footer_note'] ?? '')),
        ]);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-care-content&project_key=' . $project_key . '&day_index=' . $day_index . '&saved=1'));
    }

    /**
     * 保存按阶段护理内容
     */
    public static function save_care_phase(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_save_care_phase');

        $phase_key = sanitize_text_field(wp_unslash($_POST['phase_key'] ?? 'scab'));
        $repo = new CareContentRepository();
        $repo->upsert_phase($phase_key, [
            'title' => wp_kses_post(wp_unslash($_POST['title'] ?? '')),
            'body' => wp_kses_post(wp_unslash($_POST['body'] ?? '')),
            'key_points' => wp_kses_post(wp_unslash($_POST['key_points'] ?? '')),
            'taboo_title' => wp_kses_post(wp_unslash($_POST['taboo_title'] ?? '')),
            'taboo_body' => wp_kses_post(wp_unslash($_POST['taboo_body'] ?? '')),
            'footer_note' => sanitize_text_field(wp_unslash($_POST['footer_note'] ?? '')),
        ]);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-care-content&phase_key=' . $phase_key . '&saved=1'));
    }
}
