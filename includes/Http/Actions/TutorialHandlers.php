<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Security;
use HLCC\Data\Repositories\TutorialRepository;

if (!defined('ABSPATH'))
    exit;

/**
 * 教程管理相关的 HTTP 处理器
 * 
 * 从 PostHandlers.php 拆分，包含教程标题、步骤的 CRUD 操作
 * @since 8.9.0
 */
final class TutorialHandlers
{
    /**
     * 注册教程管理相关的 Hooks
     */
    public static function register(): void
    {
        add_action('admin_post_hlcc_tutorial_save_title', [self::class, 'save_title']);
        add_action('admin_post_hlcc_tutorial_add_step', [self::class, 'add_step']);
        add_action('admin_post_hlcc_tutorial_save_step', [self::class, 'save_step']);
        add_action('admin_post_hlcc_tutorial_delete_step', [self::class, 'delete_step']);
        add_action('admin_post_hlcc_tutorial_move_step', [self::class, 'move_step']);
    }

    /**
     * 保存教程标题
     */
    public static function save_title(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_tutorial_save_title');

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $phase_key = sanitize_text_field(wp_unslash($_POST['phase_key'] ?? 'inflammation'));
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));

        $repo = new TutorialRepository();
        $t = $repo->get_tutorial($project_key, $phase_key);
        if (!$t)
            wp_die('教程不存在（请重新激活插件或检查数据）');
        $repo->update_tutorial_title((int) $t['id'], $title);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-tutorials&project_key=' . $project_key . '&phase_key=' . $phase_key . '&saved=1'));
    }

    /**
     * 添加教程步骤
     */
    public static function add_step(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_tutorial_add_step');

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $phase_key = sanitize_text_field(wp_unslash($_POST['phase_key'] ?? 'inflammation'));

        $repo = new TutorialRepository();
        $t = $repo->get_tutorial($project_key, $phase_key);
        if (!$t)
            wp_die('教程不存在');
        $steps = $repo->list_steps((int) $t['id']);
        $next_order = count($steps) ? ((int) end($steps)['step_order'] + 10) : 10;
        $repo->insert_step((int) $t['id'], $next_order);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-tutorials&project_key=' . $project_key . '&phase_key=' . $phase_key . '&saved=1'));
    }

    /**
     * 保存教程步骤
     */
    public static function save_step(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_tutorial_save_step');

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $phase_key = sanitize_text_field(wp_unslash($_POST['phase_key'] ?? 'inflammation'));
        $step_id = (int) ($_POST['step_id'] ?? 0);

        $repo = new TutorialRepository();
        $repo->update_step($step_id, [
            'step_title' => wp_kses_post(wp_unslash($_POST['step_title'] ?? '')),
            'step_text' => wp_kses_post(wp_unslash($_POST['step_text'] ?? '')),
            'video_url' => esc_url_raw(wp_unslash($_POST['video_url'] ?? '')),
        ]);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-tutorials&project_key=' . $project_key . '&phase_key=' . $phase_key . '&step_id=' . $step_id . '&saved=1'));
    }

    /**
     * 删除教程步骤
     */
    public static function delete_step(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_tutorial_delete_step');

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $phase_key = sanitize_text_field(wp_unslash($_POST['phase_key'] ?? 'inflammation'));
        $step_id = (int) ($_POST['step_id'] ?? 0);

        (new TutorialRepository())->delete_step($step_id);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-tutorials&project_key=' . $project_key . '&phase_key=' . $phase_key . '&saved=1'));
    }

    /**
     * 移动教程步骤顺序
     */
    public static function move_step(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_tutorial_move_step');

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $phase_key = sanitize_text_field(wp_unslash($_POST['phase_key'] ?? 'inflammation'));
        $step_id = (int) ($_POST['step_id'] ?? 0);
        $dir = sanitize_text_field(wp_unslash($_POST['dir'] ?? 'up'));

        $repo = new TutorialRepository();
        $t = $repo->get_tutorial($project_key, $phase_key);
        if ($t) {
            $repo->move_step((int) $t['id'], $step_id, $dir);
        }

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-tutorials&project_key=' . $project_key . '&phase_key=' . $phase_key . '&saved=1'));
    }
}
