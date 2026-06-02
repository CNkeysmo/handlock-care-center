<?php
namespace HLCC\Http\Actions;

use HLCC\Support\Helpers;

if (!defined('ABSPATH'))
    exit;

/**
 * Handler 公共辅助方法
 * 
 * 提供给所有 Handler 类使用的通用方法
 * @since 8.9.0
 */
final class HandlerHelpers
{
    /**
     * 重定向并退出
     */
    public static function back(string $url): void
    {
        wp_safe_redirect($url);
        exit;
    }

    /**
     * 前台用户操作后的重定向
     * 普通客户操作后统一回到自己的 /care/
     * 管理员在预览模式下则回到该客户的预览入口
     */
    public static function front_back_for_user(int $user_id): void
    {
        if (current_user_can('manage_options') && $user_id > 0) {
            $url = Helpers::preview_url($user_id);
        } else {
            $url = home_url('/care/');
        }
        self::back($url);
    }

    /**
     * 验证前台操作权限
     * 只允许用户操作自己的资源，或管理员操作任何资源
     */
    public static function require_front_owner(int $user_id): void
    {
        if (!is_user_logged_in()) {
            wp_die('请先登录');
        }
        $me = (int) get_current_user_id();
        if ($me !== $user_id && !current_user_can('manage_options')) {
            wp_die('权限不足');
        }
    }
}
