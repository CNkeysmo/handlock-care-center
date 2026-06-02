<?php
if (!defined('ABSPATH')) exit;

$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$done = isset($_GET['done']) ? sanitize_text_field(wp_unslash($_GET['done'])) : '';
$need = isset($_GET['need']);
$err = isset($_GET['err']) ? sanitize_text_field(wp_unslash($_GET['err'])) : '';

$payload = $token ? get_transient('hlcc_restore_' . $token) : null;
$verify = is_array($payload) ? ($payload['verify'] ?? null) : null;
$done_payload = $done ? get_transient('hlcc_restore_done_' . $done) : null;

?>

<div class="wrap hlcc-admin">
  <h1>备份与还原 <span class="hlcc-badge"><?php echo esc_html(HLCC_BUILD_ID); ?></span></h1>

  <?php if ($err): ?>
    <div class="notice notice-error"><p>上传/校验失败（错误码：<?php echo esc_html($err); ?>）。请重试。</p></div>
  <?php endif; ?>

  <?php if (is_array($done_payload) && !empty($done_payload['ok'])): ?>
    <?php $snap = $done_payload['snapshot'] ?? null; ?>
    <div class="notice notice-success">
      <p>还原已完成。</p>
      <?php if (is_array($snap) && !empty($snap['zip_name'])): ?>
        <p class="description">系统已自动生成「还原前快照」：<code><?php echo esc_html($snap['zip_name']); ?></code>（存放于 uploads/hlcc-backups）。</p>
      <?php endif; ?>
    </div>
  <?php elseif (is_array($done_payload) && isset($done_payload['ok']) && !$done_payload['ok']): ?>
    <div class="notice notice-error"><p>还原失败：<?php echo esc_html($done_payload['error'] ?? '未知错误'); ?></p></div>
  <?php endif; ?>

  <div class="hlcc-admin-grid">
    <div class="hlcc-admin-card">
      <h2>一键备份</h2>
      <p class="description">只备份本插件的数据（HLCC 表 + hlcc_ 前缀 options/usermeta）。</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_backup_generate'); ?>
        <input type="hidden" name="action" value="hlcc_backup_generate" />
        <button class="button button-primary" type="submit">生成备份并下载 zip</button>
      </form>
    </div>

    <div class="hlcc-admin-card">
      <h2>还原</h2>
      <p class="description">还原会覆盖现有 HLCC 数据。建议先备份。</p>

      <h3 style="margin-top:12px;">1) 上传并校验备份文件</h3>
      <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_backup_verify'); ?>
        <input type="hidden" name="action" value="hlcc_backup_verify" />
        <input type="file" name="backup_zip" accept=".zip" required />
        <button class="button" type="submit">上传并校验</button>
      </form>

      <?php if ($token && is_array($verify)): ?>
        <hr/>
        <h3>2) 确认还原</h3>
        <?php if (!empty($verify['ok'])): ?>
          <?php $m = $verify['manifest'] ?? []; ?>
          <div class="notice notice-success" style="margin:12px 0 0;">
            <p>校验通过。备份来源：<code><?php echo esc_html($m['site_url'] ?? ''); ?></code>；生成时间：<code><?php echo esc_html($m['generated_at_gmt'] ?? ''); ?></code></p>
          </div>

          <?php if ($need): ?>
            <div class="notice notice-warning" style="margin:12px 0 0;">
              <p>为避免误操作，请在下方输入 <code>RESTORE</code> 后再点“开始还原”。</p>
            </div>
          <?php endif; ?>

          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
            <?php wp_nonce_field('hlcc_backup_restore'); ?>
            <input type="hidden" name="action" value="hlcc_backup_restore" />
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>" />
            <input name="confirm" placeholder="输入 RESTORE" style="width:180px;" />
            <button class="button button-primary" type="submit" onclick="return confirm('确认还原？将覆盖现有 HLCC 数据。');">开始还原</button>
          </form>
        <?php else: ?>
          <div class="notice notice-error" style="margin:12px 0 0;">
            <p>校验失败：<?php echo esc_html($verify['error'] ?? '未知错误'); ?></p>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
