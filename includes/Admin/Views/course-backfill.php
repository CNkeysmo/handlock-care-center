<?php
use HLCC\Core\Capabilities;
use HLCC\Domain\CycleRules;

if (!defined('ABSPATH')) exit;

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    wp_die('参数错误');
}

$u = get_user_by('id', $user_id);
if (!$u) {
    wp_die('客户不存在');
}

$today = current_time('Y-m-d');
$project_options = CycleRules::project_options();

?>

<div class="wrap hlcc-admin">
  <h1>补建疗程档案</h1>

  <p class="description">
    客户：<strong><?php echo esc_html($u->user_login); ?></strong>（ID: <?php echo (int)$u->ID; ?>）
  </p>

  <div class="hlcc-admin-card" style="max-width:760px;">
    <h2>创建首条疗程并设为当前</h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field('hlcc_backfill_course'); ?>
      <input type="hidden" name="action" value="hlcc_backfill_course" />
      <input type="hidden" name="user_id" value="<?php echo (int)$u->ID; ?>" />

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

      <p>
        <button class="button button-primary">补建并设为当前</button>
        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=hlcc-customers')); ?>">返回客户列表</a>
      </p>
    </form>
  </div>
</div>
