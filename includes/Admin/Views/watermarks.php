<?php
use HLCC\Support\Security;

if (!defined('ABSPATH')) exit;

Security::require_cap('manage_options');

$selected_ids = get_option('hlcc_watermark_ids', []);
if (!is_array($selected_ids)) {
    $decoded = json_decode((string)$selected_ids, true);
    $selected_ids = is_array($decoded) ? $decoded : [];
}
$selected_ids = array_values(array_unique(array_map('intval', $selected_ids)));

$png_attachments = get_posts([
    'post_type'      => 'attachment',
    'post_mime_type' => 'image/png',
    'posts_per_page' => 200,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

$action_url = admin_url('admin-post.php');
?>
<div class="wrap hlcc-admin">
  <h1>水印管理</h1>
  <p>这里可以专门为「疗程图片对比」设置可用的水印图片。建议上传带透明背景的 PNG。</p>
  <p>步骤：</p>
  <ol>
    <li>先在「媒体库 &gt; 添加」上传一张或多张 PNG 水印图片（例如店铺 Logo、英文文字）。</li>
    <li>回到本页面，在下方列表中勾选「作为水印使用」。</li>
    <li>保存设置后，在「疗程图片对比」页面中就可以通过下拉菜单选择这些水印。</li>
  </ol>

  <form method="post" action="<?php echo esc_url($action_url); ?>">
    <?php wp_nonce_field('hlcc_save_watermarks'); ?>
    <input type="hidden" name="action" value="hlcc_save_watermarks" />

    <table class="widefat striped hlcc-table">
      <thead>
        <tr>
          <th style="width:60px;">选择</th>
          <th>缩略图</th>
          <th>标题</th>
          <th>附件 ID</th>
          <th>上传时间</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$png_attachments): ?>
          <tr>
            <td colspan="5">暂时未找到任何 PNG 图片，请先前往媒体库上传。</td>
          </tr>
        <?php else: ?>
          <?php foreach ($png_attachments as $att): 
            $aid = (int)$att->ID;
            $checked = in_array($aid, $selected_ids, true);
          ?>
            <tr>
              <td>
                <label>
                  <input type="checkbox" name="watermark_ids[]" value="<?php echo $aid; ?>" <?php checked($checked); ?> />
                  作为水印
                </label>
              </td>
              <td><?php echo wp_get_attachment_image($aid, 'thumbnail'); ?></td>
              <td><?php echo esc_html($att->post_title); ?></td>
              <td><?php echo $aid; ?></td>
              <td><?php echo esc_html(get_the_date('Y-m-d H:i', $aid)); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <p style="margin-top:16px;">
      <button class="button button-primary">保存水印设置</button>
    </p>
  </form>
</div>
