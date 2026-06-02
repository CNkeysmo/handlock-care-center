<?php
namespace HLCC\Frontend;

use HLCC\App\Services\CourseService;
use HLCC\App\Services\CareService;
use HLCC\App\Services\TutorialService;
use HLCC\Domain\DayCalculator;
use HLCC\Domain\CycleRules;
use HLCC\Domain\PhaseRules;
use HLCC\Domain\Phase;
use HLCC\Support\Helpers;
use HLCC\Data\Repositories\TreatmentPhotoRepository;

if (!defined('ABSPATH'))
    exit;

final class Shortcodes
{
    public static function register(): void
    {
        add_shortcode('hlcc_care_center', [self::class, 'render']);
        add_shortcode('hlcc_course_timeline', [self::class, 'render_timeline']);
    }

    public static function render($atts = []): string
    {
        // If user not logged in, redirect preview links to统一入口 /care，并在 /care 本页显示登录表单
        if (!is_user_logged_in()) {
            $current_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $current_host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9\-\.:]/', '', (string) $_SERVER['HTTP_HOST']) : '';
            $current_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
            $current_url = rtrim($current_scheme . '://' . $current_host . $current_uri, '/');

            $care_base = rtrim(Helpers::care_base_url(), '/');

            // 如果当前访问的不是标准 /care 入口（例如预览链接带参数），统一跳转去 /care
            if ($current_url !== $care_base) {
                wp_safe_redirect($care_base);
                exit;
            }

            // 已经在 /care，本页直接显示登录表单
            $redirect = esc_url_raw($current_url);

            ob_start();
            include HLCC_PLUGIN_DIR . 'includes/Frontend/Views/login.php';
            return (string) ob_get_clean();
        }

        $current_user = wp_get_current_user();
        $user_id = (int) $current_user->ID;

        // Admin preview
        $preview_user_id = isset($_GET['hlcc_preview_user']) ? (int) $_GET['hlcc_preview_user'] : 0;
        if ($preview_user_id && current_user_can('manage_options')) {
            $user_id = $preview_user_id;
        }

        $courseSvc = new CourseService();
        $course = $courseSvc->get_active_course_for_user($user_id);

        if (!$course) {
            if (current_user_can('manage_options')) {
                return '<div class="hlcc-wrap"><p>该用户暂无激活疗程。</p></div>';
            }
            return '<div class="hlcc-wrap"><p>暂无疗程，请联系诊所。</p></div>';
        }

        $project_key = $course['project_key'];
        $procedure_date = $course['procedure_date'];
        $procedure_datetime = $course['procedure_datetime'] ?? null; // 新增：精确时间（可为空）
        $note = $course['note'] ?? '';
        $custom_cycle_days = isset($course['custom_cycle_days']) && $course['custom_cycle_days'] > 0 ? (int) $course['custom_cycle_days'] : null;

        $day_index = DayCalculator::day_index($procedure_datetime, $procedure_date);
        if ($day_index < 0)
            $day_index = 0;

        $base_phase = PhaseRules::phase_by_day($day_index);
        $override_raw = $course['phase_override'] ?? null;
        $override_phase = PhaseRules::sanitize_override($base_phase, $override_raw ? (string) $override_raw : null);
        $phase_override_at = $course['phase_override_at'] ?? null;

        // 检查 advance override 是否被自然天数追上（自动解除）
        $override_resolved = PhaseRules::is_override_resolved(
            $base_phase, $override_phase, $phase_override_at, $procedure_datetime, $procedure_date
        );

        $effective_phase = $courseSvc->effective_phase($course, $day_index);
        $is_overridden = !empty($override_phase) && !$override_resolved;

        $phase_label = Phase::label($effective_phase);
        $effective_label = Phase::label($base_phase);
        $phase_range = PhaseRules::range_label($effective_phase);

        // Display mode (theme): care | minimal | detail
        $display_mode = (string) get_user_meta((int) $user_id, 'hlcc_display_mode', true);
        $display_mode = $display_mode ? sanitize_text_field($display_mode) : 'care';
        if (!in_array($display_mode, ['care', 'minimal', 'detail'], true))
            $display_mode = 'care';

        // Status language (stage-level template + day-based extra for inflammation)
        $status_short = '恢复中';
        $status_main = '';
        $status_extra = '';
        $hero_title = '';

        if ($effective_phase === Phase::INFLAMMATION) {
            $hero_title = '目前属于恢复初期，伤口正在稳定';
            $status_short = '伤口正在稳定';
            $status_main = '伤口正在稳定中，请继续按护理指引照顾。';
            if ($day_index <= 1) {
                $status_extra = '初期可能出现轻微红肿或灼热感，属于正常反应。';
            } elseif ($day_index <= 3) {
                $status_extra = '组织逐步稳定，保持清洁并避免刺激。';
            } else {
                $status_extra = '表皮开始形成，请避免拉扯或抓挠。';
            }
        } elseif ($effective_phase === Phase::SCAB) {
            $hero_title = '表皮正在修复中，恢复进展良好';
            $status_short = '表皮正在修复';
            $status_main = '表皮正在修复中，请避免外力刺激。';
            $status_extra = '结痂或自然脱落属于正常修复过程，请勿自行剥离。';
        } else { // recovery
            $hero_title = '皮肤逐步恢复中，整体情况稳定';
            $status_short = '皮肤逐步恢复';
            $status_main = '皮肤逐步恢复中，可按医护建议评估下一步。';
            $cycle_days = CycleRules::cycle_days($project_key, $custom_cycle_days);
            $progress = ($cycle_days > 0) ? ($day_index / $cycle_days) : 0;
            if ($progress < 1) {
                $status_extra = '目前仍建议以恢复为主，暂不安排下一次操作。';
            } else {
                $status_extra = '恢复情况允许评估下一次操作安排。';
            }
        }

        // Tag suffix per theme
        $status_tag_suffix = '（系统判断）';
        if ($display_mode === 'minimal')
            $status_tag_suffix = '';
        if ($display_mode === 'detail')
            $status_tag_suffix = '（按恢复阶段判断）';
        $status_tag = '当前状态：' . $status_short . $status_tag_suffix;


        $cycle = CycleRules::cycle_days($project_key, $custom_cycle_days);
        $remain = DayCalculator::remaining_days($project_key, $day_index, $custom_cycle_days);
        $next_date = DayCalculator::next_date($procedure_datetime, $procedure_date, $project_key, $custom_cycle_days);

        // Three-phase progress calculation (v7.3.0 - 兼容新旧模式)
        // $phase_override_at 已在上方声明
        $phase_progress = \HLCC\Domain\PhaseProgressCalculator::calculate($procedure_datetime, $procedure_date, $project_key, $override_phase, $custom_cycle_days, $phase_override_at);
        $realtime_base = \HLCC\Domain\PhaseProgressCalculator::get_realtime_base_data($procedure_datetime, $procedure_date, $project_key, $custom_cycle_days, $override_phase, $phase_override_at);

        // Online Stats (v7.5.3)
        $online_stats = Helpers::get_online_stats();

        $careSvc = new CareService();
        $care = $careSvc->get_today_content($project_key, $effective_phase, $day_index, $is_overridden);

        // Tutorial
        $tutorialSvc = new TutorialService();
        $tpack = $tutorialSvc->get_tutorial_with_steps($project_key, $effective_phase);

        // Treatment photos for current user + course (for前台 gallery)
        $treatment_photos = [];
        try {
            $photoRepo = new TreatmentPhotoRepository();
            $photo_list = $photoRepo->list_for_course((int) $user_id, (int) $course['id'], 50);
            if (is_array($photo_list) && $photo_list) {
                foreach ($photo_list as $p) {
                    $attachment_id = isset($p['attachment_id']) ? (int) $p['attachment_id'] : 0;
                    if ($attachment_id <= 0)
                        continue;
                    $url = wp_get_attachment_image_url($attachment_id, 'large');
                    if (!$url)
                        continue;
                    $shot_at_raw = isset($p['shot_at']) ? (string) $p['shot_at'] : '';
                    $shot_at = $shot_at_raw !== '' ? mysql2date('Y-m-d', $shot_at_raw) : '';
                    $label_index = isset($p['shot_index']) ? (int) $p['shot_index'] : 0;
                    if ($label_index <= 0)
                        $label_index = count($treatment_photos) + 1;

                    $treatment_photos[] = [
                        'id' => (int) $p['id'],
                        'attachment_id' => $attachment_id,
                        'url' => $url,
                        'shot_at' => $shot_at,
                        'label_index' => $label_index,
                    ];
                }
            }
        } catch (\Throwable $e) {
            // 前台不抛出错误，保底为空列表
            $treatment_photos = [];
        }


        // v8.3.57: Seed requested sensitive words directly to ensure filter works
        $provided_words = "💰,叼,他妈的,妈,钱,毛爷爷,人民币,米";
        if (get_option('hlcc_sensitive_words') !== $provided_words) {
            update_option('hlcc_sensitive_words', $provided_words);
        }

        ob_start();
        
        // v8.7.4: Styles moved directly into care-center.php for better compatibility
        include HLCC_PLUGIN_DIR . 'includes/Frontend/Views/care-center.php';

        return (string) ob_get_clean();
    }


    /**
     * 我的疗程时间线简码：[hlcc_course_timeline]
     *
     * 仅负责绘制时间线 UI，不影响主护理中心逻辑。
     */
    public static function render_timeline($atts = []): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $current_user = wp_get_current_user();
        $user_id = (int) $current_user->ID;

        // 仅在存取仓库可用时执行，防止旧环境报错
        $courses_for_timeline = [];
        try {
            if (class_exists('\\HLCC\\Data\\Repositories\\CourseRepository')) {
                $repo = new \HLCC\Data\Repositories\CourseRepository();
                $courses_for_timeline = $repo->list_by_user($user_id);
            }
        } catch (\Throwable $e) {
            $courses_for_timeline = [];
        }

        // 传入视图
        ob_start();
        include HLCC_PLUGIN_DIR . 'includes/Frontend/Views/course-timeline.php';
        return (string) ob_get_clean();
    }

}