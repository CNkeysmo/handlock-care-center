<?php
namespace HLCC\Core;


use HLCC\Support\Helpers;

if (!defined('ABSPATH'))
    exit;

require_once HLCC_PLUGIN_DIR . 'includes/Core/Activator.php';
require_once HLCC_PLUGIN_DIR . 'includes/Core/Capabilities.php';
require_once HLCC_PLUGIN_DIR . 'includes/Support/Security.php';
require_once HLCC_PLUGIN_DIR . 'includes/Support/Helpers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Domain/Phase.php';
require_once HLCC_PLUGIN_DIR . 'includes/Domain/PhaseRules.php';
require_once HLCC_PLUGIN_DIR . 'includes/Domain/CycleRules.php';
require_once HLCC_PLUGIN_DIR . 'includes/Domain/DayCalculator.php';
require_once HLCC_PLUGIN_DIR . 'includes/Domain/PhaseProgressCalculator.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Db.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Repositories/CourseRepository.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Repositories/CareContentRepository.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Repositories/TutorialRepository.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Repositories/TreatmentPhotoRepository.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Repositories/SettingsRepository.php';
require_once HLCC_PLUGIN_DIR . 'includes/App/Services/CourseService.php';
require_once HLCC_PLUGIN_DIR . 'includes/App/Services/CareService.php';
require_once HLCC_PLUGIN_DIR . 'includes/App/Services/TutorialService.php';
require_once HLCC_PLUGIN_DIR . 'includes/App/Services/BackupService.php';
require_once HLCC_PLUGIN_DIR . 'includes/Admin/Menu.php';
require_once HLCC_PLUGIN_DIR . 'includes/Admin/PhotoComparePage.php';
require_once HLCC_PLUGIN_DIR . 'includes/Frontend/Shortcodes.php';
require_once HLCC_PLUGIN_DIR . 'includes/Frontend/SelfcheckTips.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/HandlerHelpers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/CourseHandlers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/CustomerHandlers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/PhotoHandlers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/TutorialHandlers.php';
// require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/BackupHandlers.php'; // 已移除 - 使用外部备份
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/CareContentHandlers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Repositories/WikiRepository.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Migrations/Create_Wiki_Tables.php';
require_once HLCC_PLUGIN_DIR . 'includes/Data/Migrations/Create_Mobile_Sessions_Table.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/WikiHandlers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Actions/PostHandlers.php';
require_once HLCC_PLUGIN_DIR . 'includes/Http/Rest/MobileRoutes.php';

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (!self::$instance)
            self::$instance = new Plugin();
        return self::$instance;
    }

    public function boot(): void
    {
        // v8.8.1: Check and run migrations if needed
        add_action('init', function () {
            if (class_exists('HLCC\\Core\\Activator')) {
                Activator::check_and_run_migrations();
            }
        }, 5);

        // Optimization: Disable WP Emojis (SVG) to save traffic/rendering
        Helpers::disable_wp_emojis();

        Capabilities::register_roles();

        // For customer accounts, never show the WP admin bar on the frontend.
        add_filter('show_admin_bar', function ($show) {
            if (is_admin())
                return $show;
            if (!is_user_logged_in())
                return $show;
            $user = wp_get_current_user();
            if (in_array(Capabilities::ROLE_CUSTOMER, (array) $user->roles, true)) {
                return false;
            }
            return $show;
        }, 20);


        add_action('admin_menu', ['HLCC\\Admin\\Menu', 'register']);
        add_action('admin_menu', ['HLCC\\Admin\\PhotoComparePage', 'register']);
        add_action('admin_enqueue_scripts', function ($hook) {
            if (strpos($hook, 'hlcc') !== false) {
                wp_enqueue_style('hlcc-admin', HLCC_PLUGIN_URL . 'assets/admin/admin.css', [], HLCC_VERSION);
            }

            // Tutorial editor: enable WP media uploader so "插入图片" works in wp_editor.
            if (strpos((string) $hook, 'hlcc-tutorials') !== false) {
                wp_enqueue_media();
            }
        });

        // PWA Support: Add manifest and meta tags for mobile full-screen experience
        add_action('wp_head', function () {
            if (!is_singular())
                return;
            $post = get_post();
            if (!$post || !has_shortcode((string) $post->post_content, 'hlcc_care_center'))
                return;

            echo "\n<!-- PWA Configuration -->\n";
            // Viewport with safe area support for notched devices (iPhone X+)
            echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">' . "\n";

            // Web App Manifest
            echo '<link rel="manifest" href="' . HLCC_PLUGIN_URL . 'assets/manifest.json">' . "\n";

            // Theme color for Android (match app background)
            echo '<meta name="theme-color" content="#ffffff">' . "\n";

            // iOS Specific Meta Tags for full-screen mode
            echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
            echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
            echo '<meta name="apple-mobile-web-app-title" content="行楽护理中心">' . "\n";

            // iOS App Icons (will create these icons next)
            echo '<link rel="apple-touch-icon" sizes="180x180" href="' . HLCC_PLUGIN_URL . 'assets/icons/icon-180x180.png">' . "\n";
            echo '<link rel="apple-touch-icon" sizes="152x152" href="' . HLCC_PLUGIN_URL . 'assets/icons/icon-152x152.png">' . "\n";
            echo '<link rel="apple-touch-icon" sizes="120x120" href="' . HLCC_PLUGIN_URL . 'assets/icons/icon-120x120.png">' . "\n";
            echo "<!-- End PWA Configuration -->\n\n";
        }, 1);

        // Frontend assets: only load on pages that actually contain our shortcode.
        add_action('wp_enqueue_scripts', function () {
            if (!is_singular())
                return;
            $post = get_post();
            if (!$post || !has_shortcode((string) $post->post_content, 'hlcc_care_center'))
                return;

            // Preload critical CSS for faster rendering
            add_action('wp_head', function () {
                echo '<link rel="preload" href="' . HLCC_PLUGIN_URL . 'assets/frontend/frontend.css?ver=' . HLCC_VERSION . '" as="style">' . "\n";
            }, 5);

            wp_enqueue_style('hlcc-frontend', HLCC_PLUGIN_URL . 'assets/frontend/frontend.css', [], HLCC_VERSION);

            // Small, defensive JS enhancement (modal scroll lock etc). Main flow never depends on JS.
            wp_enqueue_script('hlcc-boot', HLCC_PLUGIN_URL . 'assets/frontend/boot.js', [], HLCC_VERSION, true);
            // Add defer attribute to non-critical JS
            add_filter('script_loader_tag', function ($tag, $handle) {
                if (in_array($handle, ['hlcc-boot', 'hlcc-phase-progress', 'hlcc-wiki'])) {
                    return str_replace(' src', ' defer src', $tag);
                }
                return $tag;
            }, 10, 2);

            // Phase progress realtime update (v7.8.2 - renamed file to bust cache)
            wp_enqueue_script('hlcc-phase-progress', HLCC_PLUGIN_URL . 'assets/frontend/phase-progress-core.js', ['hlcc-boot'], HLCC_BUILD_ID, true);

            // Wiki Encyclopedia (v9.0.0)
            wp_enqueue_style('hlcc-wiki', HLCC_PLUGIN_URL . 'assets/frontend/css/wiki.css', ['hlcc-frontend'], HLCC_VERSION);
            wp_enqueue_script('hlcc-wiki', HLCC_PLUGIN_URL . 'assets/frontend/js/wiki.js', ['hlcc-boot'], HLCC_VERSION, true);
            wp_localize_script('hlcc-wiki', 'hlccWiki', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hlcc_wiki_nonce'),
            ]);

            // Hide WP admin bar on the care center page to keep UI clean (stable CSS-only approach).
            add_action('wp_head', function () {
                echo "\n<style id=\"hlcc-hide-adminbar\">#wpadminbar{display:none !important;}html{margin-top:0 !important;}* html body{margin-top:0 !important;}</style>\n";
            }, 1);

            // Also disable admin bar rendering for this page.
            add_filter('show_admin_bar', '__return_false', 100);
        });

        // After logout, always return to the care center (login page).
        add_filter('logout_redirect', function ($redirect_to, $requested_redirect_to, $user) {
            $care = home_url('/care/');
            return wp_validate_redirect($care, $care);
        }, 20, 3);


        // Ensure customers always return to the care page after login (some sites/plugins may override redirects).
        add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {
            // 如果是我们前台客户登录表单（带有 hlcc_front_login 字段），无论成功与否都统一回到 /care/ 页面。
            if (!empty($_POST['hlcc_front_login'])) {
                $base = home_url('/care/');
                $isAppMode = !empty($_POST['hlcc_app_mode']);
                if ($isAppMode) {
                    $base = add_query_arg('hlcc_app', '1', $base);
                }
                // 登录失败时，附加一个简单的错误标记，方便在前台显示提示。
                if (is_wp_error($user)) {
                    $base = add_query_arg('hlcc_login', 'failed', $base);
                }
                return wp_validate_redirect($base, $base);
            }

            if (is_wp_error($user) || !$user) {
                return $redirect_to;
            }

            // Prefer explicit redirect_to when it points to our care page.
            if (!empty($requested_redirect_to) && strpos($requested_redirect_to, '/care') !== false) {
                return wp_validate_redirect($requested_redirect_to, home_url('/care/'));
            }

            // Otherwise, fall back to referer when user logged in from /care page.
            $ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
            if ($ref && strpos($ref, '/care') !== false) {
                return wp_validate_redirect($ref, home_url('/care/'));
            }

            return $redirect_to;
        }, 20, 3);

        // 登录失败（例如密码错误）时，如果来自前台客户登录表单，则重定向回 /care/ 并附带错误标记。
        add_action('wp_login_failed', function ($username) {
            if (!empty($_POST['hlcc_front_login'])) {
                $base = home_url('/care/');
                if (!empty($_POST['hlcc_app_mode'])) {
                    $base = add_query_arg('hlcc_app', '1', $base);
                }
                $base = add_query_arg('hlcc_login', 'failed', $base);
                wp_safe_redirect($base);
                exit;
            }
        }, 10, 1);


        // login_init: 如果来自 /care 的前台客户登录，而且用户名或密码为空，则在 WordPress 核心处理前直接回跳 /care/。
        add_action('login_init', function () {
            if (empty($_POST['hlcc_front_login'])) {
                return;
            }
            $user = isset($_POST['log']) ? trim((string) $_POST['log']) : '';
            $pwd = isset($_POST['pwd']) ? (string) $_POST['pwd'] : '';
            if ($user === '' || $pwd === '') {
                $base = home_url('/care/');
                if (!empty($_POST['hlcc_app_mode'])) {
                    $base = add_query_arg('hlcc_app', '1', $base);
                }
                $base = add_query_arg('hlcc_login', 'failed', $base);
                wp_safe_redirect($base);
                exit;
            }
        }, 9);
        \HLCC\Frontend\Shortcodes::register();
        \HLCC\Http\Actions\PostHandlers::register();
        \HLCC\Http\Rest\MobileRoutes::register();

    }
}
