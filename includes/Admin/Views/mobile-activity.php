<?php
use HLCC\Data\Repositories\MobileSessionRepository;

if (!defined('ABSPATH')) {
    exit;
}

$search = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash((string) $_GET['s']))) : '';
$perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
$perPage = in_array($perPage, [20, 50, 100], true) ? $perPage : 20;
$paged = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
$paged = max(1, $paged);
$expandUserId = isset($_GET['expand_user_id']) ? (int) $_GET['expand_user_id'] : 0;
$expandUserId = max(0, $expandUserId);

$repo = new MobileSessionRepository();
$result = $repo->list_android_user_activity_paginated($paged, $perPage, $search);
$items = (array) ($result['items'] ?? []);
$pagination = (array) ($result['pagination'] ?? []);

$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$total = max(0, (int) ($pagination['total'] ?? 0));

if ($page > $totalPages) {
    $page = $totalPages;
    $result = $repo->list_android_user_activity_paginated($page, $perPage, $search);
    $items = (array) ($result['items'] ?? []);
    $pagination = (array) ($result['pagination'] ?? []);
    $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
    $total = max(0, (int) ($pagination['total'] ?? 0));
}

$visibleUserIds = [];
foreach ($items as $row) {
    $visibleUserIds[] = (int) ($row['user_id'] ?? 0);
}

$expandedDevices = [];
if ($expandUserId > 0 && in_array($expandUserId, $visibleUserIds, true)) {
    $expandedDevices = $repo->list_android_devices_by_user($expandUserId);
}

function hlcc_mobile_activity_admin_url(array $params = []): string
{
    $base = admin_url('admin.php?page=hlcc-mobile-activity');
    $query = [
        's' => isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash((string) $_GET['s']))) : '',
        'per_page' => isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20,
        'paged' => isset($_GET['paged']) ? (int) $_GET['paged'] : 1,
        'expand_user_id' => isset($_GET['expand_user_id']) ? (int) $_GET['expand_user_id'] : 0,
    ];

    $query = array_merge($query, $params);
    $query = array_filter($query, static function ($value) {
        return $value !== '' && $value !== null && $value !== 0;
    });

    return add_query_arg($query, $base);
}

function hlcc_mobile_activity_time(?string $raw, string $fallback = '从未活跃'): string
{
    if (!$raw) {
        return $fallback;
    }

    try {
        $ts = (new DateTimeImmutable($raw, wp_timezone()))->getTimestamp();
    } catch (Throwable $e) {
        return $fallback;
    }

    return wp_date('Y-m-d H:i:s', $ts, wp_timezone());
}
?>
<div class="wrap hlcc-admin">
    <h1>安卓活跃用户</h1>
    <p class="description">仅统计 Android App 会话。活跃判定口径：最近 24 小时内有活跃上报。</p>

    <form method="get" style="margin:12px 0; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="hlcc-mobile-activity" />
        <input class="regular-text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索用户名 / Email / ID" />
        <select name="per_page">
            <?php foreach ([20, 50, 100] as $n): ?>
                <option value="<?php echo (int) $n; ?>" <?php selected($perPage, $n); ?>><?php echo (int) $n; ?>/页</option>
            <?php endforeach; ?>
        </select>
        <button class="button">查询</button>
        <?php if ($search !== ''): ?>
            <a class="button" href="<?php echo esc_url(hlcc_mobile_activity_admin_url(['s' => '', 'paged' => 1, 'expand_user_id' => 0])); ?>">清空搜索</a>
        <?php endif; ?>
    </form>

    <?php if (!$items): ?>
        <div class="notice notice-info"><p>暂无安卓活跃数据。</p></div>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:80px;">用户ID</th>
                    <th>用户名</th>
                    <th>Email</th>
                    <th style="width:100px;">安卓设备数</th>
                    <th style="width:180px;">最近活跃时间</th>
                    <th style="width:110px;">状态</th>
                    <th style="width:110px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row):
                    $userId = (int) ($row['user_id'] ?? 0);
                    $isExpanded = ($expandUserId > 0 && $expandUserId === $userId);
                    $isActive24h = !empty($row['is_active_24h']);
                    ?>
                    <tr>
                        <td><?php echo (int) $userId; ?></td>
                        <td><?php echo esc_html((string) ($row['user_name'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['user_email'] ?? '')); ?></td>
                        <td><?php echo (int) ($row['device_count'] ?? 0); ?></td>
                        <td><?php echo esc_html(hlcc_mobile_activity_time($row['last_seen_at'] ?? null)); ?></td>
                        <td>
                            <?php if ($isActive24h): ?>
                                <span class="hlcc-badge" style="background:#dcfce7;color:#166534;">活跃</span>
                            <?php else: ?>
                                <span class="hlcc-badge" style="background:#fee2e2;color:#991b1b;">离线</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isExpanded): ?>
                                <a class="button" href="<?php echo esc_url(hlcc_mobile_activity_admin_url(['expand_user_id' => 0])); ?>">收起设备</a>
                            <?php else: ?>
                                <a class="button" href="<?php echo esc_url(hlcc_mobile_activity_admin_url(['expand_user_id' => $userId])); ?>">展开设备</a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <?php if ($isExpanded): ?>
                        <tr>
                            <td colspan="7" style="background:#f8fafc;">
                                <strong>用户 #<?php echo (int) $userId; ?> 安卓设备明细</strong>
                                <?php if (!$expandedDevices): ?>
                                    <p style="margin:8px 0 0;color:#64748b;">暂无设备明细。</p>
                                <?php else: ?>
                                    <table class="widefat striped" style="margin-top:8px;">
                                        <thead>
                                            <tr>
                                                <th style="width:70px;">会话ID</th>
                                                <th>设备ID</th>
                                                <th>设备名</th>
                                                <th style="width:100px;">版本</th>
                                                <th style="width:170px;">最近活跃</th>
                                                <th style="width:170px;">Access 到期</th>
                                                <th style="width:170px;">Refresh 到期</th>
                                                <th style="width:120px;">会话状态</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expandedDevices as $device): ?>
                                                <tr>
                                                    <td><?php echo (int) ($device['id'] ?? 0); ?></td>
                                                    <td><code><?php echo esc_html((string) ($device['device_id'] ?? '')); ?></code></td>
                                                    <td><?php echo esc_html((string) ($device['device_name'] ?: '—')); ?></td>
                                                    <td><?php echo esc_html((string) ($device['app_version'] ?: '—')); ?></td>
                                                    <td><?php echo esc_html(hlcc_mobile_activity_time($device['last_seen_at'] ?? null)); ?></td>
                                                    <td><?php echo esc_html(hlcc_mobile_activity_time($device['access_expires_at'] ?? null, '—')); ?></td>
                                                    <td><?php echo esc_html(hlcc_mobile_activity_time($device['refresh_expires_at'] ?? null, '—')); ?></td>
                                                    <td>
                                                        <?php if (!empty($device['revoked_at'])): ?>
                                                            <span class="hlcc-badge" style="background:#fee2e2;color:#991b1b;">已撤销</span>
                                                        <?php else: ?>
                                                            <span class="hlcc-badge" style="background:#e0f2fe;color:#0c4a6e;">有效</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div style="margin-top:12px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span class="description">共 <?php echo (int) $total; ?> 位用户，<?php echo (int) $totalPages; ?> 页</span>
                <?php $prev = max(1, $page - 1); ?>
                <?php $next = min($totalPages, $page + 1); ?>
                <a class="button" <?php echo $page <= 1 ? 'disabled' : ''; ?>
                   href="<?php echo esc_url(hlcc_mobile_activity_admin_url(['paged' => $prev])); ?>">上一页</a>
                <a class="button" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>
                   href="<?php echo esc_url(hlcc_mobile_activity_admin_url(['paged' => $next])); ?>">下一页</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
