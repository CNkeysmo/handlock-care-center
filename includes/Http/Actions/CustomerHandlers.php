<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Security;
use HLCC\Core\Capabilities;
use HLCC\App\Services\CourseService;
use HLCC\Data\Repositories\CourseRepository;
use HLCC\Data\Repositories\SettingsRepository;
use HLCC\Domain\CycleRules;

if (!defined('ABSPATH'))
    exit;

/**
 * 客户管理相关的 HTTP 处理器
 * 
 * 从 PostHandlers.php 拆分，包含客户 CRUD 和账户操作
 * @since 8.9.0
 */
final class CustomerHandlers
{
    /**
     * 注册客户管理相关的 Hooks
     */
    public static function register(): void
    {
        // 后台管理操作
        add_action('admin_post_hlcc_create_customer', [self::class, 'create_customer']);
        add_action('admin_post_hlcc_delete_customer', [self::class, 'delete_customer']);
        add_action('admin_post_hlcc_backfill_course', [self::class, 'backfill_course']);
        add_action('admin_post_hlcc_fix_customer_adminbar', [self::class, 'fix_customer_adminbar']);
        add_action('admin_post_hlcc_save_android_apk_url', [self::class, 'save_android_apk_url']);

        // 前台客户操作已移除（密码修改和显示模式）- 由后台管理员统一管理
    }

    /**
     * 后台创建客户
     */
    public static function create_customer(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_create_customer');

        $username = sanitize_user(wp_unslash($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        // 同时创建首个疗程
        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $allowed = array_keys(CycleRules::project_options());
        if (!in_array($project_key, $allowed, true)) {
            $project_key = 'tattoo';
        }
        $procedure_date = sanitize_text_field(wp_unslash($_POST['procedure_date'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));

        if (!$username || !$password) {
            wp_die('用户名与密码不能为空');
        }
        if (!$procedure_date) {
            wp_die('请填写操作日期');
        }
        if (strtotime($procedure_date) > strtotime(current_time('Y-m-d'))) {
            wp_die('操作日期不能选择未来日期');
        }
        if (!$email) {
            $email = $username . '@example.local';
        }

        $user_id = wp_create_user($username, $password, $email);
        if (is_wp_error($user_id)) {
            wp_die($user_id->get_error_message());
        }
        wp_update_user(['ID' => $user_id, 'role' => Capabilities::ROLE_CUSTOMER]);

        // 确保客户在前台不显示 WP 管理栏
        update_user_meta((int) $user_id, 'show_admin_bar_front', 'false');

        // 创建首个疗程并设为活动状态
        try {
            $courseSvc = new CourseService();
            $created_course_id = $courseSvc->create_course((int) $user_id, $project_key, $procedure_date, $note, true);
            // 保险起见：创建客户后，确保该客户目前只保留这一次首次疗程记录
            $repo = new CourseRepository();
            $allCourses = $repo->list_by_user((int) $user_id);
            foreach ($allCourses as $c) {
                if (!empty($c['id']) && (int) $c['id'] !== (int) $created_course_id) {
                    $repo->delete((int) $c['id']);
                }
            }
        } catch (\Throwable $e) {
            // 回滚用户
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user((int) $user_id);
            wp_die('客户已创建但疗程创建失败：' . esc_html($e->getMessage()));
        }

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-customers&created=1'));
    }

    /**
     * 后台删除客户
     */
    public static function delete_customer(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_delete_customer');
        $user_id = (int) ($_POST['user_id'] ?? 0);
        if (!$user_id)
            wp_die('参数错误');

        // 先删除相关疗程
        $svc = new CourseService();
        $svc->delete_all_courses_for_user($user_id);

        // 删除 WP 用户
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-customers&deleted=1'));
    }

    /**
     * 后台补录疗程
     */
    public static function backfill_course(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_backfill_course');
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $project_key = sanitize_text_field(wp_unslash($_POST['project_key'] ?? 'tattoo'));
        $allowed = array_keys(CycleRules::project_options());
        if (!in_array($project_key, $allowed, true)) {
            $project_key = 'tattoo';
        }
        $procedure_date = sanitize_text_field(wp_unslash($_POST['procedure_date'] ?? ''));
        $note = sanitize_text_field(wp_unslash($_POST['note'] ?? ''));
        if (!$user_id || !$procedure_date)
            wp_die('参数错误');
        if (strtotime($procedure_date) > strtotime(current_time('Y-m-d')))
            wp_die('操作日期不能选择未来日期');
        (new CourseService())->create_course($user_id, $project_key, $procedure_date, $note, true);
        HandlerHelpers::back(admin_url('admin.php?page=hlcc-customers&backfilled=1'));
    }

    /**
     * 修复客户管理栏设置
     */
    public static function fix_customer_adminbar(): void
    {
        Security::require_cap('manage_options');
        Security::verify_nonce('hlcc_fix_customer_adminbar');

        $users = get_users([
            'role' => Capabilities::ROLE_CUSTOMER,
            'fields' => ['ID'],
            'number' => 0,
        ]);

        $count = 0;
        foreach ($users as $u) {
            $uid = (int) $u->ID;
            update_user_meta($uid, 'show_admin_bar_front', 'false');
            $count++;
        }

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-customers&adminbar_fixed=' . (int) $count));
    }

    /**
     * 保存安卓 APK 下载链接
     */
    public static function save_android_apk_url(): void
    {
        Security::require_cap('manage_options');
        Security::require_post();
        Security::verify_nonce('hlcc_save_android_apk_url');

        $apk_url = esc_url_raw(trim((string) wp_unslash($_POST['android_apk_url'] ?? '')));
        $repo = new SettingsRepository();
        $repo->update('android_apk_url', $apk_url);

        HandlerHelpers::back(admin_url('admin.php?page=hlcc-customers&apk_saved=1'));
    }
}
