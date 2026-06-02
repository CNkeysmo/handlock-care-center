<?php
namespace HLCC\Support;

if (!defined('ABSPATH'))
    exit;

final class Security
{
    public static function require_cap(string $cap): void
    {
        if (!current_user_can($cap)) {
            wp_die('权限不足');
        }
    }

    public static function verify_nonce(string $action, string $field = '_wpnonce', bool $is_get = false): void
    {
        $source = $is_get ? $_GET : $_POST;
        $nonce = isset($source[$field]) ? sanitize_text_field(wp_unslash($source[$field])) : '';
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die('安全校验失败，请刷新后重试。');
        }
    }

    public static function require_get(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
        if ($method !== 'GET') {
            wp_die('无效请求');
        }
    }

    public static function require_post(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : '';
        if ($method !== 'POST') {
            wp_die('无效请求');
        }
    }

}
