<?php
namespace HLCC\Http\Actions;

use HLCC\Data\Repositories\WikiRepository;
use HLCC\Support\Security;

if (!defined('ABSPATH'))
    exit;

/**
 * 百科全书 HTTP 处理器
 * 
 * 处理前台搜索、后台管理等 AJAX 请求
 * @since 9.0.0
 */
final class WikiHandlers
{
    /**
     * 注册所有 Wiki 相关的 action
     */
    public static function register(): void
    {
        // 前台 AJAX（已登录用户）
        add_action('wp_ajax_hlcc_wiki_search', [self::class, 'ajax_search']);
        add_action('wp_ajax_hlcc_wiki_get_entry', [self::class, 'ajax_get_entry']);
        add_action('wp_ajax_hlcc_wiki_submit_request', [self::class, 'ajax_submit_request']);

        // 后台管理（需要权限）
        add_action('admin_post_hlcc_wiki_save_entry', [self::class, 'save_entry']);
        add_action('admin_post_hlcc_wiki_delete_entry', [self::class, 'delete_entry']);
        add_action('admin_post_hlcc_wiki_approve_request', [self::class, 'approve_request']);
        add_action('admin_post_hlcc_wiki_reject_request', [self::class, 'reject_request']);

        // 后台 AJAX
        add_action('wp_ajax_hlcc_wiki_admin_list', [self::class, 'ajax_admin_list']);
        add_action('wp_ajax_hlcc_wiki_admin_get', [self::class, 'ajax_admin_get']);
        add_action('wp_ajax_hlcc_wiki_admin_get_entry', [self::class, 'ajax_admin_get_entry']);
        add_action('wp_ajax_hlcc_wiki_admin_save_entry', [self::class, 'ajax_admin_save_entry']);
        add_action('wp_ajax_hlcc_wiki_admin_delete_entry', [self::class, 'ajax_admin_delete_entry']);
        add_action('wp_ajax_hlcc_wiki_admin_dismiss_request', [self::class, 'ajax_admin_dismiss_request']);
    }

    // ==================== 前台搜索 ====================

    /**
     * 实时搜索词条（自动补全）
     */
    public static function ajax_search(): void
    {
        // 验证 nonce
        if (!check_ajax_referer('hlcc_wiki_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败'], 403);
        }

        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        if (mb_strlen($keyword) < 1) {
            wp_send_json_success(['entries' => []]);
        }

        $repo = new WikiRepository();
        $entries = $repo->search($keyword, 5);

        wp_send_json_success(['entries' => $entries]);
    }

    /**
     * 获取词条详情
     */
    public static function ajax_get_entry(): void
    {
        if (!check_ajax_referer('hlcc_wiki_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败'], 403);
        }

        $id = (int) ($_POST['entry_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => '无效的词条ID'], 400);
        }

        $repo = new WikiRepository();
        $entry = $repo->get($id);

        if (!$entry || $entry['status'] !== 'published') {
            wp_send_json_error(['message' => '词条不存在'], 404);
        }

        // 增加浏览次数
        $repo->increment_view($id);

        wp_send_json_success(['entry' => $entry]);
    }

    /**
     * 提交新词条请求
     */
    public static function ajax_submit_request(): void
    {
        if (!check_ajax_referer('hlcc_wiki_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败'], 403);
        }

        $question = sanitize_text_field($_POST['question'] ?? '');
        if (mb_strlen($question) < 5) {
            wp_send_json_error(['message' => '问题至少需要 5 个字符'], 400);
        }

        if (mb_strlen($question) > 500) {
            wp_send_json_error(['message' => '问题不能超过 500 个字符'], 400);
        }

        $user_id = get_current_user_id();

        $repo = new WikiRepository();
        $request_id = $repo->create_request($question, $user_id);

        if ($request_id > 0) {
            wp_send_json_success([
                'message' => '感谢您的提问！我们会尽快补充相关内容。',
                'request_id' => $request_id,
            ]);
        } else {
            wp_send_json_error(['message' => '提交失败，请稍后重试'], 500);
        }
    }

    // ==================== 后台管理 ====================

    /**
     * 保存词条（新增或更新）
     */
    public static function save_entry(): void
    {
        Security::verify_nonce('hlcc_wiki_admin');
        Security::require_cap('manage_options');

        $id = (int) ($_POST['entry_id'] ?? 0);
        $data = [
            'title' => $_POST['title'] ?? '',
            'keywords' => $_POST['keywords'] ?? '',
            'content' => $_POST['content'] ?? '',
            'category' => $_POST['category'] ?? 'general',
            'image_url' => $_POST['image_url'] ?? '',
            'status' => $_POST['status'] ?? 'published',
        ];

        $repo = new WikiRepository();

        if ($id > 0) {
            // 更新
            $success = $repo->update($id, $data);
            $message = $success ? '词条已更新' : '词条更新失败（内容可能未变动）';
        } else {
            // 新增
            $id = $repo->create($data);
            $message = $id > 0 ? '词条已创建' : '词条创建失败';
        }

        // 如果是从请求转化而来，更新请求状态
        $from_request = (int) ($_POST['from_request_id'] ?? 0);
        if ($from_request > 0) {
            $repo->approve_request($from_request, $id);
        }

        HandlerHelpers::back($message);
    }

    /**
     * 删除词条
     */
    public static function delete_entry(): void
    {
        Security::verify_nonce('hlcc_wiki_admin');
        Security::require_cap('manage_options');

        $id = (int) ($_POST['entry_id'] ?? 0);
        if ($id <= 0) {
            wp_die('无效的词条ID');
        }

        $repo = new WikiRepository();
        $repo->delete($id);

        HandlerHelpers::back('词条已删除');
    }

    /**
     * 审批客户请求
     */
    public static function approve_request(): void
    {
        Security::verify_nonce('hlcc_wiki_admin');
        Security::require_cap('manage_options');

        $request_id = (int) ($_POST['request_id'] ?? 0);
        $entry_id = (int) ($_POST['entry_id'] ?? 0);

        if ($request_id <= 0) {
            wp_die('无效的请求ID');
        }

        $repo = new WikiRepository();
        $repo->approve_request($request_id, $entry_id);

        HandlerHelpers::back('请求已审批');
    }

    /**
     * 拒绝客户请求
     */
    public static function reject_request(): void
    {
        Security::verify_nonce('hlcc_wiki_admin');
        Security::require_cap('manage_options');

        $request_id = (int) ($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_die('无效的请求ID');
        }

        $repo = new WikiRepository();
        $repo->reject_request($request_id);

        HandlerHelpers::back('请求已拒绝');
    }

    // ==================== 后台 AJAX ====================

    /**
     * 后台获取词条列表
     */
    public static function ajax_admin_list(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '权限不足'], 403);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $status = sanitize_text_field($_GET['status'] ?? '');

        $repo = new WikiRepository();
        $entries = $repo->list_all($per_page, $offset, $status);
        $total = $repo->count($status);

        wp_send_json_success([
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }

    /**
     * 后台获取单个词条
     */
    public static function ajax_admin_get(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => '权限不足'], 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => '无效的词条ID'], 400);
        }

        $repo = new WikiRepository();
        $entry = $repo->get($id);

        if (!$entry) {
            wp_send_json_error(['message' => '词条不存在'], 404);
        }

        wp_send_json_success(['entry' => $entry]);
    }

    /**
     * 后台获取单个词条 (POST 版本，用于编辑)
     */
    public static function ajax_admin_get_entry(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '权限不足'], 403);
        }

        $id = (int) ($_POST['entry_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => '无效的词条ID'], 400);
        }

        $repo = new WikiRepository();
        $entry = $repo->get($id);

        if (!$entry) {
            wp_send_json_error(['message' => '词条不存在'], 404);
        }

        wp_send_json_success(['entry' => $entry]);
    }

    /**
     * 后台保存词条 (AJAX)
     */
    public static function ajax_admin_save_entry(): void
    {
        if (!check_ajax_referer('hlcc_wiki_admin', 'hlcc_wiki_nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败'], 403);
        }
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '权限不足'], 403);
        }

        $id = (int) ($_POST['entry_id'] ?? 0);
        $data = [
            'title' => $_POST['title'] ?? '',
            'keywords' => $_POST['keywords'] ?? '',
            'content' => $_POST['content'] ?? '',
            'category' => $_POST['category'] ?? 'general',
            'image_url' => $_POST['image_url'] ?? '',
            'status' => $_POST['status'] ?? 'published',
        ];

        if (empty($data['title']) || empty($data['content'])) {
            wp_send_json_error(['message' => '标题和内容不能为空'], 400);
        }

        $repo = new WikiRepository();

        if ($id > 0) {
            $success = $repo->update($id, $data);
            if (!$success) {
                global $wpdb;
                wp_send_json_error(['message' => '保存失败：' . ($wpdb->last_error ?: '数据库更新未生效')]);
            }
            $message = '词条已更新';
        } else {
            $id = $repo->create($data);
            if ($id <= 0) {
                global $wpdb;
                wp_send_json_error(['message' => '创建失败：' . ($wpdb->last_error ?: '无法写入数据库')]);
            }
            $message = '词条已创建';
        }

        // 处理关联请求
        $from_request = (int) ($_POST['from_request_id'] ?? 0);
        if ($from_request > 0) {
            $repo->approve_request($from_request, $id);
        }

        wp_send_json_success(['message' => $message, 'entry_id' => $id]);
    }

    /**
     * 后台删除词条 (AJAX)
     */
    public static function ajax_admin_delete_entry(): void
    {
        if (!check_ajax_referer('hlcc_wiki_admin', 'nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败'], 403);
        }
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '权限不足'], 403);
        }

        $id = (int) ($_POST['entry_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => '无效的词条ID'], 400);
        }

        $repo = new WikiRepository();
        $repo->delete($id);

        wp_send_json_success(['message' => '词条已删除']);
    }

    /**
     * 后台忽略请求 (AJAX)
     */
    public static function ajax_admin_dismiss_request(): void
    {
        if (!check_ajax_referer('hlcc_wiki_admin', 'nonce', false)) {
            wp_send_json_error(['message' => '安全验证失败'], 403);
        }
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => '权限不足'], 403);
        }

        $id = (int) ($_POST['request_id'] ?? 0);
        if ($id <= 0) {
            wp_send_json_error(['message' => '无效的请求ID'], 400);
        }

        $repo = new WikiRepository();
        $repo->reject_request($id);

        wp_send_json_success(['message' => '请求已忽略']);
    }
}
