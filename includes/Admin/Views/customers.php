<?php
use HLCC\Core\Capabilities;
use HLCC\Data\Db;
use HLCC\Data\Repositories\SettingsRepository;
use HLCC\Domain\CycleRules;
use HLCC\Domain\DayCalculator;
use HLCC\Support\Helpers;

if (!defined('ABSPATH'))
  exit;

global $wpdb;


$created = isset($_GET['created']);
$deleted = isset($_GET['deleted']);
$backfilled = isset($_GET['backfilled']);
$apk_saved = isset($_GET['apk_saved']);

$adminbar_fixed = isset($_GET['adminbar_fixed']) ? (int) $_GET['adminbar_fixed'] : 0;


$sort_remain = isset($_GET['sort_remain']) ? (string) $_GET['sort_remain'] : '';
$sort_remain = in_array($sort_remain, ['asc', 'desc'], true) ? $sort_remain : '';
$remain0 = isset($_GET['remain0']);


$s = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '';
$has_course = isset($_GET['has_course']) ? sanitize_text_field(wp_unslash($_GET['has_course'])) : '';
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
$per_page = $per_page <= 0 ? 20 : min(200, $per_page);
$paged = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
$paged = $paged <= 0 ? 1 : $paged;
$offset = ($paged - 1) * $per_page;
$search_columns = ['user_login', 'user_email'];
if ($s !== '' && ctype_digit($s)) {
  $search_columns[] = 'ID';
}

$project_options = CycleRules::project_options();
$today = current_time('Y-m-d');
$settings_repo = new SettingsRepository();
$android_apk_url = (string) $settings_repo->get('android_apk_url', '');

// Build include/exclude sets for course filter (scales OK for clinic use)
$course_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM " . Db::table('courses')) ?: [];
$course_user_ids = array_map('intval', $course_user_ids);


// Optional: filter users who are due (remaining days <= 0) based on ACTIVE course
$due_user_ids = [];
if ($remain0) {
  $rows = $wpdb->get_results("SELECT user_id, project_key, procedure_date, procedure_datetime FROM " . Db::table('courses') . " WHERE is_active=1", ARRAY_A) ?: [];
  foreach ($rows as $r) {
    $uid = (int) ($r['user_id'] ?? 0);
    if ($uid <= 0)
      continue;
    $pkey = CycleRules::normalize_project_key((string) ($r['project_key'] ?? 'tattoo'));
    $pdate = (string) ($r['procedure_date'] ?? '');
    $pdatetime = isset($r['procedure_datetime']) ? (string) $r['procedure_datetime'] : null;
    if (!$pdate && !$pdatetime)
      continue;
    $day = DayCalculator::day_index($pdatetime, $pdate);
    $remain = DayCalculator::remaining_days($pkey, $day);
    if ($remain <= 0)
      $due_user_ids[$uid] = true;
  }
  $due_user_ids = array_keys($due_user_ids);
}

$q_base = [
  'role' => Capabilities::ROLE_CUSTOMER,
  'orderby' => 'ID',
  'order' => 'DESC',
];

$q_args = array_merge($q_base, [
  'number' => $per_page,
  'offset' => $offset,
  'fields' => ['ID', 'user_login', 'user_email'],
]);
if ($s !== '') {
  $q_args['search'] = '*' . $s . '*';
  $q_args['search_columns'] = $search_columns;
}
if ($has_course === '1') {
  $q_args['include'] = $course_user_ids ?: [0];
} elseif ($has_course === '0') {
  $q_args['exclude'] = $course_user_ids;
}


// Apply due filter (remain0) by overriding include list
if ($remain0) {
  $q_args['include'] = $due_user_ids ?: [0];
  // When filtering, show all due users (still paginated)
}

// If sorting by remaining days, we need a deterministic order across pages.
// Clinic scale is small enough: we compute remain for matched users, sort IDs, then paginate.
if ($sort_remain) {
  $q_all = array_merge($q_base, [
    'fields' => 'ID',
    'number' => 999999,
    'offset' => 0,
  ]);
  if ($s !== '') {
    $q_all['search'] = '*' . $s . '*';
    $q_all['search_columns'] = $search_columns;
  }
  if ($has_course === '1') {
    $q_all['include'] = $course_user_ids ?: [0];
  } elseif ($has_course === '0') {
    $q_all['exclude'] = $course_user_ids;
  }
  if ($remain0) {
    $q_all['include'] = $due_user_ids ?: [0];
  }

  $all_ids = (new WP_User_Query($q_all))->get_results();
  $all_ids = array_map('intval', is_array($all_ids) ? $all_ids : []);
  $all_ids = array_values(array_filter($all_ids));

  // Fetch active courses for all matched IDs in one query
  $active_all = [];
  if ($all_ids) {
    $in = implode(',', array_fill(0, count($all_ids), '%d'));
    $sql = $wpdb->prepare(
      "SELECT user_id, project_key, procedure_date, procedure_datetime FROM " . Db::table('courses') . " WHERE is_active=1 AND user_id IN ($in)",
      ...$all_ids
    );
    $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
    foreach ($rows as $r) {
      $active_all[(int) $r['user_id']] = $r;
    }
  }

  $remain_by_user = [];
  foreach ($all_ids as $uid) {
    $c = $active_all[$uid] ?? null;
    if (!$c) {
      $remain_by_user[$uid] = PHP_INT_MAX;
      continue;
    }
    $pkey = CycleRules::normalize_project_key((string) ($c['project_key'] ?? 'tattoo'));
    $pdate = (string) ($c['procedure_date'] ?? '');
    $pdatetime = isset($c['procedure_datetime']) ? (string) $c['procedure_datetime'] : null;
    if (!$pdate && !$pdatetime) {
      $remain_by_user[$uid] = PHP_INT_MAX;
      continue;
    }
    $day = DayCalculator::day_index($pdatetime, $pdate);
    $remain_by_user[$uid] = (int) DayCalculator::remaining_days($pkey, $day);
  }

  usort($all_ids, function ($a, $b) use ($remain_by_user, $sort_remain) {
    $ra = $remain_by_user[(int) $a] ?? PHP_INT_MAX;
    $rb = $remain_by_user[(int) $b] ?? PHP_INT_MAX;
    if ($ra === $rb)
      return 0;
    return ($sort_remain === 'asc') ? (($ra < $rb) ? -1 : 1) : (($ra > $rb) ? -1 : 1);
  });

  $total = count($all_ids);
  $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
  $page_ids = array_slice($all_ids, $offset, $per_page);

  $q_args = array_merge($q_base, [
    'include' => $page_ids ?: [0],
    'orderby' => 'include',
    'fields' => ['ID', 'user_login', 'user_email'],
    'number' => $per_page,
    'offset' => 0,
  ]);
}

$user_query = new WP_User_Query($q_args);
$users = $user_query->get_results();
if (!isset($total)) {
  $total = (int) $user_query->get_total();
  $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;
}

// Fetch active courses for users on this page
$active_by_user = [];
if ($users) {
  $ids = array_map(fn($u) => (int) $u->ID, $users);
  $ids = array_filter($ids);
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '%d'));
    $sql = $wpdb->prepare(
      "SELECT * FROM " . Db::table('courses') . " WHERE is_active=1 AND user_id IN ($in)",
      ...$ids
    );
    $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
    foreach ($rows as $r) {
      $active_by_user[(int) $r['user_id']] = $r;
    }
  }
}

function hlcc_admin_url_with(array $params): string
{
  $base = admin_url('admin.php?page=hlcc-customers');
  $q = array_merge([
    's' => isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '',
    'has_course' => isset($_GET['has_course']) ? sanitize_text_field(wp_unslash($_GET['has_course'])) : '',
    'sort_remain' => isset($_GET['sort_remain']) ? sanitize_text_field(wp_unslash($_GET['sort_remain'])) : '',
    'remain0' => isset($_GET['remain0']) ? 1 : '',
    'per_page' => isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20,
    'paged' => isset($_GET['paged']) ? (int) $_GET['paged'] : 1,
  ], $params);
  return add_query_arg(array_filter($q, fn($v) => $v !== '' && $v !== null), $base);
}

?>

<div class="wrap hlcc-admin">
  <h1>客户档案 <span class="hlcc-badge"><?php echo esc_html(HLCC_BUILD_ID); ?></span></h1>
  <div style="margin:12px 0 16px 0;">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
      <?php wp_nonce_field('hlcc_fix_customer_adminbar'); ?>
      <input type="hidden" name="action" value="hlcc_fix_customer_adminbar" />
      <button type="submit" class="button">一键关闭已有客户前台工具栏</button>
    </form>
  </div>


  <?php if ($created): ?>
    <div class="notice notice-success">
      <p>客户已创建，并已同步创建首条疗程档案。</p>
    </div>
  <?php endif; ?>
  <?php if ($backfilled): ?>
    <div class="notice notice-success">
      <p>疗程档案已补建，并已设为当前疗程。</p>
    </div>
  <?php endif; ?>
  <?php if ($deleted): ?>
    <div class="notice notice-success">
      <p>客户已删除。</p>
    </div>
  <?php endif; ?>
  <?php if ($adminbar_fixed > 0): ?>
    <div class="notice notice-success">
      <p>已为 <?php echo (int) $adminbar_fixed; ?> 个客户关闭前台工具栏显示。</p>
    </div>
  <?php endif; ?>
  <?php if ($apk_saved): ?>
    <div class="notice notice-success">
      <p>安卓 APK 下载链接已保存。</p>
    </div>
  <?php endif; ?>


  <div class="hlcc-admin-grid">
    <div class="hlcc-admin-card">
      <h2>创建客户（同时创建疗程档案）</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:12px 0 18px 0; padding:12px; background:#f6f7f7; border:1px solid #dcdcde; border-radius:8px;">
        <?php wp_nonce_field('hlcc_save_android_apk_url'); ?>
        <input type="hidden" name="action" value="hlcc_save_android_apk_url" />
        <p style="margin:0 0 8px 0; font-weight:600;">安卓 APK 下载链接</p>
        <input
          class="large-text"
          type="url"
          name="android_apk_url"
          value="<?php echo esc_attr($android_apk_url); ?>"
          placeholder="https://example.com/app.apk" />
        <p class="description" style="margin:8px 0 12px 0;">登录页底部的“下载安卓版本”按钮会使用这里的链接。留空则前台不显示该按钮。</p>
        <p style="margin:0;"><button class="button">保存安卓链接</button></p>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_create_customer'); ?>
        <input type="hidden" name="action" value="hlcc_create_customer" />

        <table class="form-table">
          <tr>
            <th><label>用户名</label></th>
            <td><input class="regular-text" name="username" required /></td>
          </tr>
          <tr>
            <th><label>密码</label></th>
            <td><input class="regular-text" name="password" type="text" required /></td>
          </tr>
          <tr>
            <th><label>Email（可选）</label></th>
            <td><input class="regular-text" name="email" type="email" placeholder="留空会自动生成占位邮箱" /></td>
          </tr>
        </table>

        <h3 style="margin-top:16px;">首条疗程档案</h3>
        <table class="form-table">
          <tr>
            <th><label>项目</label></th>
            <td>
              <select name="project_key" required>
                <?php foreach ($project_options as $k => $label): ?>
                  <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th><label>操作日期</label></th>
            <td><input name="procedure_date" type="date" max="<?php echo esc_attr($today); ?>" required /></td>
          </tr>
          <tr>
            <th><label>备注（可选）</label></th>
            <td><input class="regular-text" name="note" placeholder="例如：部位/颜色/特殊注意" /></td>
          </tr>
        </table>

        <p><button class="button button-primary">创建</button></p>
      </form>
      <hr />
      <p class="description">
        前台页面：请创建一个页面并插入短码 <code>[hlcc_care_center]</code>。<br>
        管理员预览：在该页面 URL 后加 <code>?hlcc_preview_user=客户ID</code>。
      </p>
    </div>

    <div class="hlcc-admin-card">
      <h2>客户列表</h2>

      <form method="get" style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <input type="hidden" name="page" value="hlcc-customers" />
        <?php if (!empty($sort_remain)): ?>
          <input type="hidden" name="sort_remain" value="<?php echo esc_attr($sort_remain); ?>" />
        <?php endif; ?>
        <?php if ($remain0): ?>
          <input type="hidden" name="remain0" value="1" />
        <?php endif; ?>
        <input class="regular-text" name="s" value="<?php echo esc_attr($s); ?>" placeholder="搜索：用户名 / Email / ID" />
        <select name="has_course">
          <option value="" <?php selected($has_course, ''); ?>>全部</option>
          <option value="1" <?php selected($has_course, '1'); ?>>已建档</option>
          <option value="0" <?php selected($has_course, '0'); ?>>未建档</option>
        </select>
        <select name="per_page">
          <?php foreach ([20, 50, 100, 200] as $n): ?>
            <option value="<?php echo (int) $n; ?>" <?php selected($per_page, $n); ?>><?php echo (int) $n; ?>/页</option>
          <?php endforeach; ?>
        </select>

        <?php
        $due_url = add_query_arg(['remain0' => 1, 'paged' => 1]);
        $all_url = remove_query_arg(['remain0', 'paged']);
        ?>
        <a class="button <?php echo $remain0 ? 'button-primary' : ''; ?>"
          href="<?php echo esc_url($due_url); ?>">只看0天</a>
        <?php if ($remain0): ?>
          <a class="button" href="<?php echo esc_url($all_url); ?>">显示全部</a>
        <?php endif; ?>

        <button class="button">查询</button>
      </form>

      <?php if (!$users): ?>
        <p>暂无客户。</p>
      <?php else: ?>
        <table class="widefat striped">
          <thead>
            <tr>
              <th style="width:70px;">ID</th>
              <th>用户名</th>
              <th>Email</th>
              <th>当前项目</th>
              <th style="width:110px;">操作日期</th>
              <th style="width:90px;">当前天数</th>
              <th style="width:110px;">
                <?php
                $next_sort = ($sort_remain === 'asc') ? 'desc' : 'asc';
                $sort_url = add_query_arg(['sort_remain' => $next_sort, 'paged' => 1]);
                ?>
                <a href="<?php echo esc_url($sort_url); ?>" style="text-decoration:none;">
                  距下次清洗
                  <?php if ($sort_remain === 'asc'): ?>▲<?php elseif ($sort_remain === 'desc'): ?>▼<?php endif; ?>
                </a>
              </th>
              <th style="width:220px;">操作</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $uid = (int) $u->ID;
              $c = $active_by_user[$uid] ?? null; ?>
              <tr>
                <td><?php echo $uid; ?></td>
                <td><?php echo esc_html($u->user_login); ?></td>
                <td><?php echo esc_html($u->user_email); ?></td>

                <?php if (!$c): ?>
                  <td><span class="hlcc-badge" style="background:#eee;color:#444;">未建档</span></td>
                  <td>—</td>
                  <td>—</td>
                  <td>—</td>
                <?php else:
                  $pkey = (string) ($c['project_key'] ?? 'tattoo');
                  $pkey = CycleRules::normalize_project_key($pkey);
                  $pname = $project_options[$pkey] ?? $pkey;
                  $pdate = (string) ($c['procedure_date'] ?? '');
                  $pdatetime = isset($c['procedure_datetime']) ? (string) $c['procedure_datetime'] : null;
                  $day = ($pdate || $pdatetime) ? DayCalculator::day_index($pdatetime, $pdate) : 0;
                  $remain = ($pdate || $pdatetime) ? DayCalculator::remaining_days($pkey, $day) : 0;
                  ?>
                  <td><?php echo esc_html($pname); ?></td>
                  <td><?php echo esc_html($pdate); ?></td>
                  <td><?php echo (int) $day; ?></td>
                  <td><?php echo (int) $remain; ?> 天</td>
                <?php endif; ?>

                <td>
                  <?php if (!$c): ?>
                    <a class="button"
                      href="<?php echo esc_url(admin_url('admin.php?page=hlcc-course-backfill&user_id=' . $uid)); ?>">补建疗程</a>
                  <?php else: ?>
                    <a class="button"
                      href="<?php echo esc_url(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $uid)); ?>">疗程管理</a>
                  <?php endif; ?>

                  <a class="button" target="_blank" style="margin-left:6px;"
                    href="<?php echo esc_url(Helpers::preview_url($uid)); ?>">预览前台</a>

                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="display:inline-block;margin-left:6px;" onsubmit="return confirm('确认删除该客户？此操作不可恢复。');">
                    <?php wp_nonce_field('hlcc_delete_customer'); ?>
                    <input type="hidden" name="action" value="hlcc_delete_customer" />
                    <input type="hidden" name="user_id" value="<?php echo $uid; ?>" />
                    <button class="button button-link-delete" type="submit">删除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
          <div style="margin-top:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <span class="description">共 <?php echo (int) $total; ?> 位客户，<?php echo (int) $total_pages; ?> 页</span>
            <div>
              <?php
              $prev = max(1, $paged - 1);
              $next = min($total_pages, $paged + 1);
              ?>
              <a class="button" <?php echo $paged <= 1 ? 'disabled' : ''; ?>
                href="<?php echo esc_url(hlcc_admin_url_with(['paged' => $prev])); ?>">上一页</a>
              <a class="button" <?php echo $paged >= $total_pages ? 'disabled' : ''; ?>
                href="<?php echo esc_url(hlcc_admin_url_with(['paged' => $next])); ?>">下一页</a>
            </div>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>
