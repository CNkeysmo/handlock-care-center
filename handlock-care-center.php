<?php
/**
 * Plugin Name: 行楽客户护理中心
 * Description: HANDLock X 客户护理系统
 * Version:           9.3.9
 * Author:            GINO
 * License:           GPL-2.0+
 * Text Domain:       handlock-care-center
 * Domain Path:       /languages
 *
 * @package           HandlockCareCenter
 */

// 禁止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
// 插件版本和构建标识
// Define plugin version
if (!defined('HLCC_VERSION')) {
    define('HLCC_VERSION', '9.3.9');
}

// Define build ID (timestamp for cache busting)
// Build ID: 2026.03.04.939 - push path cleanup
if (!defined('HLCC_BUILD_ID')) {
    define('HLCC_BUILD_ID', '2026.03.04.939');
}
define('HLCC_PLUGIN_FILE', __FILE__);
define('HLCC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HLCC_PLUGIN_URL', plugin_dir_url(__FILE__));

// 加载核心模块
require_once HLCC_PLUGIN_DIR . 'includes/Core/Plugin.php';

// 插件激活钩子
register_activation_hook(__FILE__, ['HLCC\\Core\\Activator', 'activate']);
register_deactivation_hook(__FILE__, '__return_null');

// 插件初始化
add_action('plugins_loaded', function () {
    // 自动数据库升级校验
    if (get_option('hlcc_version') !== HLCC_VERSION) {
        \HLCC\Core\Activator::activate();
        update_option('hlcc_version', HLCC_VERSION);
    }
    HLCC\Core\Plugin::instance()->boot();
});
