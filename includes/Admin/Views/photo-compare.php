<?php
use HLCC\Data\Db;
use HLCC\Data\Repositories\TreatmentPhotoRepository;
use HLCC\Domain\CycleRules;

if (!defined('ABSPATH')) exit;

global $wpdb;

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$s = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '';
$search_columns = ['user_login', 'user_email'];
if ($s !== '' && ctype_digit($s)) {
    $search_columns[] = 'ID';
}
?>
<div class="wrap hlcc-admin">
  <?php if (!$user_id): ?>
  <h1>疗程图片对比</h1>
  <p>在这里可以统一管理所有客户的疗程照片，对比首张与最近一次的变化，并集中进入疗程管理。</p>
  <?php
    $q_args = [
      'number'  => 100,
      'orderby' => 'ID',
      'order'   => 'DESC',
    ];
    if ($s !== '') {
      $q_args['search'] = '*' . $s . '*';
      $q_args['search_columns'] = $search_columns;
    }
    $users_list = get_users($q_args);

    $photos_by_user = [];
    $active_by_user = [];

    if ($users_list) {
        $user_ids = [];
        foreach ($users_list as $u) {
            $id = (int)$u->ID;
            if ($id > 0) {
                $user_ids[$id] = $id;
            }
        }
        if ($user_ids) {
            $ids_sql = implode(',', array_map('intval', array_values($user_ids)));

            $table = Db::table('treatment_photos');
            $sql_p = "SELECT user_id, COUNT(*) AS total_photos, MIN(shot_at) AS first_at, MAX(shot_at) AS last_at FROM {$table} WHERE user_id IN ({$ids_sql}) GROUP BY user_id";
            $rows_p = $wpdb->get_results($sql_p, ARRAY_A) ?: [];
            foreach ($rows_p as $r) {
                $photos_by_user[(int)$r['user_id']] = $r;
            }

            $course_table = Db::table('courses');
            $sql_c = "SELECT * FROM {$course_table} WHERE user_id IN ({$ids_sql}) AND is_active=1";
            $courses = $wpdb->get_results($sql_c, ARRAY_A) ?: [];
            foreach ($courses as $c) {
                $active_by_user[(int)$c['user_id']] = $c;
            }
        }
    }
  ?>

  <form method="get" action="">
    <input type="hidden" name="page" value="hlcc-photo-compare" />
    <p class="search-box">
      <label class="screen-reader-text" for="hlcc-photo-search-input">搜索客户：</label>
      <input type="search" id="hlcc-photo-search-input" name="s" value="<?php echo esc_attr($s); ?>" />
      <input type="submit" class="button" value="搜索用户名 / 邮箱 / ID" />
    </p>
  </form>

  <?php if ($users_list): ?>
    <table class="widefat striped hlcc-table">
      <thead>
        <tr>
          <th>客户</th>
          <th>当前疗程</th>
          <th>图片数量</th>
          <th>首张时间</th>
          <th>最近时间</th>
          <th>操作</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users_list as $u):
        $uid = (int)$u->ID;
        $summary = $photos_by_user[$uid] ?? null;
        $course  = $active_by_user[$uid] ?? null;
        $total   = $summary ? (int)$summary['total_photos'] : 0;
        $first_t = $summary && !empty($summary['first_at']) ? mysql2date('Y-m-d H:i', $summary['first_at']) : '';
        $last_t  = $summary && !empty($summary['last_at']) ? mysql2date('Y-m-d H:i', $summary['last_at']) : '';
        $manage_url = admin_url('admin.php?page=hlcc-photo-compare&user_id=' . $uid);
        $course_url = admin_url('admin.php?page=hlcc-course-edit&user_id=' . $uid);
      ?>
        <tr>
          <td>
            <strong><?php echo esc_html($u->user_login); ?></strong><br />
            <span class="hlcc-muted">ID <?php echo $uid; ?> · <?php echo esc_html($u->user_email); ?></span>
          </td>
          <td>
            <?php if ($course): ?>
              <?php
                $pkey = (string)($course['project_key'] ?? 'tattoo');
                $label = CycleRules::project_label($pkey);
                $remark = (string)($course['note'] ?? '');
              ?>
              <?php echo esc_html($label); ?>
              <?php if ($remark !== ''): ?>
                <span class="hlcc-muted">（<?php echo esc_html($remark); ?>）</span>
              <?php endif; ?>
              <span class="hlcc-muted">#<?php echo (int)$course['id']; ?></span>
            <?php else: ?>
              <span class="hlcc-muted">暂无当前疗程</span>
            <?php endif; ?>
          </td>
          <td><?php echo $total; ?></td>
          <td><?php echo $first_t ? esc_html($first_t) : '—'; ?></td>
          <td><?php echo $last_t ? esc_html($last_t) : '—'; ?></td>
          <td>
            <a class="button button-primary" href="<?php echo esc_url($manage_url); ?>">管理图片对比</a>
            <a class="button" href="<?php echo esc_url($course_url); ?>">疗程管理</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p>尚未找到任何客户。</p>
  <?php endif; ?>

  <?php else: // detail for one user ?>
    <?php
      $user = get_userdata($user_id);
      if (!$user) {
          echo '<p>未找到该客户。</p></div>';
          return;
      }

      $course_rows = $wpdb->get_results(
          $wpdb->prepare("SELECT * FROM " . Db::table('courses') . " WHERE user_id=%d ORDER BY is_active DESC, id DESC", $user_id),
          ARRAY_A
      );

      $current_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
      $course_row = null;

      if ($course_rows) {
          foreach ($course_rows as $row) {
              if ($current_course_id && (int)$row['id'] === $current_course_id) {
                  $course_row = $row;
                  break;
              }
          }
          if (!$course_row) {
              foreach ($course_rows as $row) {
                  if (!empty($row['is_active'])) {
                      $course_row = $row;
                      break;
                  }
              }
              if (!$course_row) {
                  $course_row = $course_rows[0];
              }
              $current_course_id = (int)$course_row['id'];
          }
      }

      $photo_repo = new TreatmentPhotoRepository();
      $active_course_id = $course_row ? (int)$course_row['id'] : 0;
      $photos = $active_course_id ? $photo_repo->list_for_course($user_id, $active_course_id, 200) : [];
      $pair = $active_course_id ? $photo_repo->first_and_latest($user_id, $active_course_id) : [];
      $first_at = '';
      if ($pair && !empty($pair['first']['shot_at'])) {
          $first_at = mysql2date('Y-m-d H:i', $pair['first']['shot_at']);
      }
      $project_label = '';
      if ($course_row) {
          $pkey = (string)($course_row['project_key'] ?? 'tattoo');
          $project_label = CycleRules::project_label($pkey);
      }
      $back_url = admin_url('admin.php?page=hlcc-photo-compare');
    ?>
    <h1>疗程图片对比：<?php echo esc_html($user->user_login); ?>（ID <?php echo (int)$user_id; ?>）</h1>
    <p><a href="<?php echo esc_url($back_url); ?>">&larr; 返回图片对比总览</a></p>

    <?php if ($course_rows && count($course_rows) > 1): ?>
      <form method="get" action="" class="hlcc-inline" style="margin:8px 0 16px 0;">
        <input type="hidden" name="page" value="hlcc-photo-compare" />
        <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>" />
        <label>切换项目：
          <select name="course_id" onchange="this.form.submit();">
            <?php foreach ($course_rows as $row):
              $cid = (int)$row['id'];
              $pkey = (string)($row['project_key'] ?? 'tattoo');
              $remark = (string)($row['note'] ?? '');
              $label = $remark !== '' ? $remark : CycleRules::project_label($pkey);
              $is_active = !empty($row['is_active']);
            ?>
              <option value="<?php echo $cid; ?>" <?php selected($cid, $current_course_id); ?>>
                <?php echo esc_html($label); ?><?php echo $is_active ? '（当前进行中）' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>
    <?php endif; ?>

    <?php if (!$course_row): ?>
      <p>当前客户暂未设置「当前疗程」，请先在「疗程管理」中创建并激活一个疗程。</p>
      <p>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id)); ?>">前往疗程管理</a>
      </p>
    <?php else: ?>
      <div class="hlcc-admin-grid">
        <div class="hlcc-admin-card">
          <h2>当前疗程</h2>
          <p>
            <?php echo esc_html($project_label); ?>
            <span class="hlcc-muted">#<?php echo (int)$active_course_id; ?></span>
          </p>
          <?php if (!empty($course_row['note'])): ?>
            <p class="hlcc-muted">备注：<?php echo esc_html($course_row['note']); ?></p>
          <?php endif; ?>
          <?php if (!empty($course_row['procedure_date'])): ?>
            <p class="hlcc-muted">操作日期：<?php echo esc_html(mysql2date('Y-m-d', $course_row['procedure_date'])); ?></p>
          <?php endif; ?>
          <?php if ($first_at): ?>
            <p class="hlcc-muted">首张图片时间：<?php echo esc_html($first_at); ?></p>
          <?php else: ?>
            <p class="hlcc-muted">尚未有首张图片记录。</p>
          <?php endif; ?>
          <p>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hlcc-course-edit&user_id=' . $user_id)); ?>">打开疗程管理</a>
          </p>
        </div>

        <div class="hlcc-admin-card">
          <h2>上传疗程图片</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="hlcc-row">
            <?php wp_nonce_field('hlcc_upload_treatment_photo'); ?>
            <input type="hidden" name="action" value="hlcc_upload_treatment_photo" />
            <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>" />
            <input type="hidden" name="course_id" value="<?php echo (int)$active_course_id; ?>" />
            <label for="hlcc_photo">选择图片：</label>
            <input type="file" name="hlcc_photo" id="hlcc_photo" accept="image/*" />
            <button class="button button-primary">保存图片</button>
          </form>
          <p class="hlcc-muted">建议客户每次回访拍照后立即上传，系统会自动记录日期和顺序。</p>
        </div>
      </div>

      <div class="hlcc-admin-card">
        <h2>图片时间线（横向滚动）</h2>
        <?php if (!$photos): ?>
          <p>当前疗程暂未上传任何图片。</p>
        <?php else: ?>
          <div class="hlcc-photo-timeline-scroll" style="display:flex;overflow-x:auto;gap:12px;padding:8px 0;">
            <?php foreach ($photos as $idx => $p):
              $pid = (int)$p['id'];
              $shot_at = !empty($p['shot_at']) ? mysql2date('Y-m-d H:i', $p['shot_at']) : '';
              $shot_value = !empty($p['shot_at']) ? mysql2date('Y-m-d\TH:i', $p['shot_at']) : '';
              $shot_display = $shot_at !== '' ? $shot_at : '未记录时间';
            ?>
              <div class="hlcc-photo-item" style="min-width:220px;border:1px solid #ddd;border-radius:8px;padding:8px;">
                <div class="hlcc-photo-header" style="display:flex;justify-content:space-between;align-items:center;">
                  <strong>#<?php echo $idx + 1; ?></strong>
                  <label style="font-size:12px;">
                    <input type="checkbox" form="hlcc-photo-compare-form" name="photo_ids[]" value="<?php echo $pid; ?>" />
                    参与对比
                  </label>
                </div>
                <div class="hlcc-photo-meta" style="margin:4px 0;">
                  <span class="hlcc-muted"><?php echo esc_html($shot_display); ?></span>
                </div>
                <div class="hlcc-photo-thumb" style="margin:4px 0;">
                  <?php $thumb = wp_get_attachment_image((int)$p['attachment_id'], 'thumbnail'); ?>
                  <?php if ($thumb) { echo $thumb; } ?>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline" style="margin-top:4px;">
                  <?php wp_nonce_field('hlcc_update_treatment_photo'); ?>
                  <input type="hidden" name="action" value="hlcc_update_treatment_photo" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>" />
                  <input type="hidden" name="course_id" value="<?php echo (int)$active_course_id; ?>" />
                  <input type="hidden" name="photo_id" value="<?php echo $pid; ?>" />
                  <input type="datetime-local" name="shot_at" value="<?php echo $shot_value; ?>" />
                  <button class="button">保存时间</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline" style="margin-top:4px;">
                  <?php wp_nonce_field('hlcc_move_treatment_photo'); ?>
                  <input type="hidden" name="action" value="hlcc_move_treatment_photo" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>" />
                  <input type="hidden" name="course_id" value="<?php echo (int)$active_course_id; ?>" />
                  <input type="hidden" name="photo_id" value="<?php echo $pid; ?>" />
                  <button class="button" name="direction" value="up">上移</button>
                  <button class="button" name="direction" value="down">下移</button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline" style="margin-top:4px;" onsubmit="return confirm('确定要删除这张图片记录吗？');">
                  <?php wp_nonce_field('hlcc_delete_treatment_photo'); ?>
                  <input type="hidden" name="action" value="hlcc_delete_treatment_photo" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>" />
                  <input type="hidden" name="course_id" value="<?php echo (int)$active_course_id; ?>" />
                  <input type="hidden" name="photo_id" value="<?php echo $pid; ?>" />
                  <button class="button button-link-delete">删除</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($photos) >= 2): ?>
  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="hlcc-photo-compare-form" class="hlcc-inline" style="margin-top:12px;">
    <?php wp_nonce_field('hlcc_generate_treatment_compare'); ?>
    <input type="hidden" name="action" value="hlcc_generate_treatment_compare" />
    <input type="hidden" name="user_id" value="<?php echo (int)$user_id; ?>" />
    <input type="hidden" name="course_id" value="<?php echo (int)$active_course_id; ?>" />
    <label>选中多张图片，生成对比图：</label>
    <span class="hlcc-muted">若未选择图片，则默认使用首张与最新一张。</span>
    <p style="margin-top:8px;">
      <label for="hlcc_watermark_id">对比图水印（可选）：</label>
      <select name="hlcc_watermark_id" id="hlcc_watermark_id">
        <?php
        $wm_ids = get_option('hlcc_watermark_ids', []);
        if (!is_array($wm_ids)) {
          $decoded = json_decode((string)$wm_ids, true);
          $wm_ids = is_array($decoded) ? $decoded : [];
        }
        $wm_ids = array_values(array_unique(array_map('intval', $wm_ids)));

        // 读取当前已选择的水印 ID：
        // 1) 如果本次表单提交有传值，就用 POST 里的；
        // 2) 否则如果有可用水印，就默认选第一个；
        // 3) 否则为 0（不添加水印）。
        $selected_wm = 0;
        if (isset($_POST['hlcc_watermark_id']) && $_POST['hlcc_watermark_id'] !== '') {
          $selected_wm = (int)$_POST['hlcc_watermark_id'];
        } elseif (!empty($wm_ids)) {
          $selected_wm = (int)$wm_ids[0];
        }

        // 「不添加水印」始终保留为一个显式选项，但默认不再选中，只在 $selected_wm 为 0 时被选中。
        ?>
        <option value="" <?php selected($selected_wm, 0); ?>>不添加水印</option>
        <?php
        if ($wm_ids) {
          $wm_query = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image/png',
            'post__in'       => $wm_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => count($wm_ids),
          ]);
          if ($wm_query) {
            foreach ($wm_query as $wm_post) {
        ?>
              <option value="<?php echo (int)$wm_post->ID; ?>" <?php selected($selected_wm, (int)$wm_post->ID); ?>><?php echo esc_html($wm_post->post_title); ?>（ID <?php echo (int)$wm_post->ID; ?>）</option>
        <?php
            }
          }
        }
        ?>
      </select>
      <span class="hlcc-muted">可在「行楽护理中心 &gt; 水印管理」中管理可用水印列表。</span>
    </p>
/p>
    <p style="margin-top:8px;">
      <button class="button button-primary" name="orientation" value="horizontal">生成横向对比图</button>
      <button class="button" name="orientation" value="vertical">生成竖向对比图</button>
    </p>
  </form>
<?php else: ?>
  <p class="hlcc-muted">上传至少 2 张图片后，即可生成首张 vs 最新的对比图。</p>
<?php endif; ?>

        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
