<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Security;
use HLCC\Support\Helpers;
use HLCC\Core\Capabilities;
use HLCC\App\Services\CourseService;
use HLCC\Data\Repositories\CourseRepository;
use HLCC\Domain\CycleRules;
use HLCC\Domain\DayCalculator;
use HLCC\Domain\PhaseRules;

if (!defined('ABSPATH'))
    exit;

/**
 * 疗程管理相关的 HTTP 处理器
 * 
 * 从 PostHandlers.php 拆分，包含所有疗程 CRUD 操作
 * @since 8.9.0
 */
final class CourseHandlers
{
    /**
     * 注册疗程相关的 Hooks
     */
    public static function register(): void
    {
        // 后台管理操作
        add_action('admin_post_hlcc_create_course', [self::class, 'create_course']);
        add_action('admin_post_hlcc_update_course', [self::class, 'update_course']);
        add_action('admin_post_hlcc_delete_course', [self::class, 'delete_course']);
        add_action('admin_post_hlcc_set_active_course', [self::class, 'set_active_course']);
        add_action('admin_post_hlcc_set_phase_override', [self::class, 'set_phase_override']);
        add_action('admin_post_hlcc_switch_project', [self::class, 'switch_project']);

        // 前台客户操作
        add_action('admin_post_hlcc_front_create_course', [self::class, 'front_create_course']);
        add_action('admin_post_hlcc_front_delete_course', [self::class, 'front_delete_course']);
        add_action('admin_post_hlcc_front_set_active_course', [self::class, 'front_set_active_course']);
        add_action('admin_post_hlcc_front_update_course', [self::class, 'front_update_course']);
        add_action('admin_post_hlcc_front_set_phase_override', [self::class, 'front_set_phase_override']);
        add_action('admin_post_hlcc_front_switch_project', [self::class, 'front_switch_project']);
        add_action('admin_post_hlcc_front_update_custom_cycle', [self::class, 'front_update_custom_cycle']);
    }

    // ==================== 后台管理操作 ====================

    /**
     * 后台创建疗程
     */
    public static function create_course(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_create_course');

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        // 防御：如果 project_key 被篡改或缺失，回退到已知的 key
        $allowed = array_keys(CycleRules::project_options());
        if (!in_array($project_key, $allowed, true)) {
            $project_key = 'tattoo';
        }
        $procedure_date = sanitize_text_field(wp_unslash($_POST['procedure_date'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        $make_active = isset($_POST['make_active']) ? 1 : 0;
        $custom_cycle_days = isset($_POST['custom_cycle_days']) ? (int) $_POST['custom_cycle_days'] : null;
        if ($custom_cycle_days <= 0)
            $custom_cycle_days = null;

        if (!$user_id || !$procedure_date)
            wp_die('参数错误');
        // 禁止选择未来日期
        if (strtotime($procedure_date) > strtotime(current_time('Y-m-d')))
            wp_die('操作日期不能选择未来日期');

        $svc = new CourseService();
        $svc->create_course($user_id, $project_key, $procedure_date, $note, (bool) $make_active, $custom_cycle_days);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id . '&saved=1'));
    }

    /**
     * 后台更新疗程
     */
    public static function update_course(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_update_course');

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $procedure_date = sanitize_text_field(wp_unslash($_POST['procedure_date'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        $custom_cycle_days = isset($_POST['custom_cycle_days']) && trim($_POST['custom_cycle_days']) !== '' ? (int) $_POST['custom_cycle_days'] : null;
        if ($custom_cycle_days !== null && $custom_cycle_days <= 0)
            $custom_cycle_days = null;

        if (!$course_id || !$user_id || !$procedure_date)
            wp_die('参数错误');
        if (strtotime($procedure_date) > strtotime(current_time('Y-m-d')))
            wp_die('操作日期不能选择未来日期');

        $svc = new CourseService();
        $svc->update_course_basic($course_id, $procedure_date, $note, $custom_cycle_days);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id . '&saved=1'));
    }

    /**
     * 后台删除疗程
     */
    public static function delete_course(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_delete_course');
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$course_id || !$user_id)
            wp_die('参数错误');

        $svc = new CourseService();
        $svc->delete_course($course_id, $user_id);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id . '&deleted=1'));
    }

    /**
     * 后台设置活动疗程
     */
    public static function set_active_course(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_set_active_course');

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$course_id || !$user_id)
            wp_die('参数错误');

        (new CourseService())->set_active($user_id, $course_id);
        HandlerHelpers::back(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id . '&saved=1'));
    }

    /**
     * 后台设置阶段覆盖
     */
    public static function set_phase_override(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_set_phase_override');

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $phase = sanitize_text_field(wp_unslash($_POST['phase_override'] ?? ''));
        $phase = $phase === '' ? null : $phase;

        if (!$course_id || !$user_id)
            wp_die('参数错误');
        (new CourseService())->set_phase_override($course_id, $phase, get_current_user_id());

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id . '&saved=1'));
    }

    /**
     * 后台管理员切换疗程项目类型
     */
    public static function switch_project(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_switch_project');

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $new_project_key = sanitize_text_field(wp_unslash($_POST['new_project_key'] ?? ''));

        if (!$course_id || !$user_id || !$new_project_key) {
            wp_die('参数错误');
        }

        $svc = new CourseService();
        $success = $svc->switch_project($course_id, $new_project_key);

        if (!$success) {
            wp_die('切换失败：无效的项目类型');
        }

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id . '&saved=1'));
    }

    // ==================== 前台客户操作 ====================

    /**
     * 前台创建疗程
     */
    public static function front_create_course(): void
    {
        Security::verify_nonce('hlcc_front_create_course');
        $user_id = (int) ($_POST['user_id'] ?? 0);
        HandlerHelpers::require_front_owner($user_id);

        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $allowed = array_keys(CycleRules::project_options());
        if (!in_array($project_key, $allowed, true)) {
            $project_key = 'tattoo';
        }
        $procedure_date = sanitize_text_field(wp_unslash($_POST['procedure_date'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        $make_active = isset($_POST['make_active']) ? 1 : 0;

        if (!$user_id || !$procedure_date)
            wp_die('参数错误');
        if (strtotime($procedure_date) > strtotime(current_time('Y-m-d')))
            wp_die('操作日期不能选择未来日期');

        (new CourseService())->create_course($user_id, $project_key, $procedure_date, $note, (bool) $make_active);
        HandlerHelpers::front_back_for_user($user_id);
    }

    /**
     * 前台删除疗程
     */
    public static function front_delete_course(): void
    {
        Security::verify_nonce('hlcc_front_delete_course');
        $course_id = (int) ($_POST['course_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        HandlerHelpers::require_front_owner($user_id);
        if (!$course_id || !$user_id)
            wp_die('参数错误');

        (new CourseService())->delete_course($course_id, $user_id);
        HandlerHelpers::front_back_for_user($user_id);
    }

    /**
     * 前台设置活动疗程
     */
    public static function front_set_active_course(): void
    {
        if (!is_user_logged_in())
            wp_die('请先登录');
        Security::verify_nonce('hlcc_front_set_active_course');

        $course_id = (int) ($_POST['course_id'] ?? 0);

        // 安全：管理员可以指定 user_id，普通用户只能操作自己的数据
        if (current_user_can('manage_options') && isset($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        } else {
            $user_id = get_current_user_id();
        }

        if (!$course_id || !$user_id)
            wp_die('参数错误');

        $repo = new CourseRepository();
        $c = $repo->get($course_id);
        if (!$c || (int) $c['user_id'] !== $user_id)
            wp_die('疗程不存在');

        $svc = new CourseService();
        $svc->set_active($user_id, $course_id);

        HandlerHelpers::front_back_for_user($user_id);
    }

    /**
     * 前台更新疗程
     */
    public static function front_update_course(): void
    {
        if (!is_user_logged_in())
            wp_die('请先登录');
        Security::verify_nonce('hlcc_front_update_course');

        $course_id = (int) ($_POST['course_id'] ?? 0);

        // 安全：管理员可以指定 user_id，普通用户只能操作自己的数据
        if (current_user_can('manage_options') && isset($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        } else {
            $user_id = get_current_user_id();
        }

        $procedure_date = sanitize_text_field(wp_unslash($_POST['procedure_date'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));

        if (!$course_id || !$user_id)
            wp_die('参数错误');

        $repo = new CourseRepository();
        $c = $repo->get($course_id);
        if (!$c || (int) $c['user_id'] !== $user_id)
            wp_die('疗程不存在');

        // 日期格式校验：必须是 YYYY-MM-DD
        if ($procedure_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $procedure_date)) {
            wp_die('日期格式错误');
        }

        // 禁止选择未来日期
        if ($procedure_date) {
            $today = current_time('Y-m-d');
            if ($procedure_date > $today) {
                wp_die('操作日期不能选择未来日期');
            }
        }
        $svc = new CourseService();
        $svc->update_course_basic($course_id, $procedure_date, $note);

        HandlerHelpers::front_back_for_user($user_id);
    }

    /**
     * 前台设置阶段覆盖
     * 基于 BASE 阶段计算，允许的操作：auto（清除）、advance +1、delay -1
     * 不会基于之前的覆盖进行链式操作，防止跨级别跳跃
     */
    public static function front_set_phase_override(): void
    {
        if (!is_user_logged_in())
            wp_die('请先登录');
        Security::verify_nonce('hlcc_front_phase_override');

        $course_id = (int) ($_POST['course_id'] ?? 0);

        // 安全：管理员可以指定 user_id，普通用户只能操作自己的数据
        if (current_user_can('manage_options') && isset($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
        } else {
            $user_id = get_current_user_id();
        }

        $mode = sanitize_text_field(wp_unslash($_POST['phase_mode'] ?? 'auto'));

        if (!$course_id || !$user_id)
            wp_die('参数错误');

        $repo = new CourseRepository();
        $c = $repo->get($course_id);
        if (!$c || (int) $c['user_id'] !== $user_id)
            wp_die('疗程不存在');

        $procedure_date = (string) ($c['procedure_date'] ?? '');
        $procedure_datetime = isset($c['procedure_datetime']) ? (string) $c['procedure_datetime'] : null;
        if (!$procedure_date && !$procedure_datetime)
            wp_die('疗程数据异常');

        $day = DayCalculator::day_index($procedure_datetime, $procedure_date);
        if ($day < 0)
            $day = 0;
        $base = PhaseRules::phase_by_day($day);
        $opt = PhaseRules::override_options($base);

        $desired = null;
        if ($mode === 'auto') {
            $desired = null; // 清除覆盖
        } elseif ($mode === 'advance') {
            $desired = $opt['advance'] ?: $base;
        } elseif ($mode === 'delay') {
            $desired = $opt['delay'] ?: $base;
        } else {
            $desired = null;
        }

        // 如果目标与基础相同，视为自动
        if ($desired === $base) {
            $desired = null;
        }

        (new CourseService())->set_phase_override($course_id, $desired ? (string) $desired : null, get_current_user_id());

        HandlerHelpers::back(remove_query_arg(['saved'], wp_get_referer() ?: home_url('/care/')));
    }

    /**
     * 前台管理员预览模式下切换疗程项目类型
     * 只有管理员可以操作
     */
    public static function front_switch_project(): void
    {
        Security::require_post();

        if (!is_user_logged_in()) {
            wp_die('请先登录');
        }

        // 只有管理员可以通过前台切换项目
        if (!current_user_can('manage_options')) {
            wp_die('只有管理员可以切换项目');
        }

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $new_project_key = sanitize_text_field(wp_unslash($_POST['new_project_key'] ?? ''));

        if (!$course_id || !$new_project_key) {
            wp_die('参数错误');
        }

        // 验证 nonce
        if (!wp_verify_nonce((string) ($_POST['_wpnonce'] ?? ''), 'hlcc_front_switch_project')) {
            wp_die('安全校验失败');
        }

        $svc = new CourseService();
        $success = $svc->switch_project($course_id, $new_project_key);

        if (!$success) {
            wp_die('切换失败：无效的项目类型');
        }

        // 返回到前台护理中心页面
        $referer = wp_get_referer();
        wp_safe_redirect($referer ?: home_url('/care/'));
        exit;
    }

    /**
     * 前台管理员为疗程设置自定义周期
     */
    public static function front_update_custom_cycle(): void
    {
        Security::require_post();
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('权限不足');
        }

        Security::verify_nonce('hlcc_front_custom_cycle');

        $course_id = (int) ($_POST['course_id'] ?? 0);
        // 如果输入为空，则视为 null（重置）
        $val = trim($_POST['custom_cycle_days'] ?? '');
        $days = ($val === '') ? null : (int) $val;
        if ($days !== null && $days <= 0)
            $days = null;

        if (!$course_id)
            wp_die('参数错误');

        (new CourseService())->update_custom_cycle($course_id, $days);

        $referer = wp_get_referer();
        wp_safe_redirect($referer ?: home_url('/care/'));
        exit;
    }
}
