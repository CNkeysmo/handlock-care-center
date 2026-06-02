<?php
use HLCC\Data\Repositories\CareContentRepository;
use HLCC\Domain\CycleRules;
use HLCC\Domain\Phase;

if (!defined('ABSPATH'))
  exit;

$repo = new CareContentRepository();

$project_key = isset($_GET['project_key']) ? sanitize_text_field(wp_unslash($_GET['project_key'])) : 'tattoo';
$day_index = isset($_GET['day_index']) ? (int) $_GET['day_index'] : 0;
$phase_key = isset($_GET['phase_key']) ? sanitize_text_field(wp_unslash($_GET['phase_key'])) : Phase::SCAB;

$mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : 'day';
if ($mode !== 'phase')
  $mode = 'day';

$saved = isset($_GET['saved']);

if ($mode === 'day') {
  if ($day_index < 0)
    $day_index = 0;
  if ($day_index > 5)
    $day_index = 5;
  $row = $repo->get_day($project_key, $day_index) ?: [];
} else {
  $row = $repo->get_phase($phase_key) ?: [];
}

?>
<div class="wrap hlcc-admin">
  <h1>今日护理内容</h1>

  <?php if ($saved): ?>
    <div class="notice notice-success">
      <p>已保存。</p>
    </div>
  <?php endif; ?>

  <h2 class="nav-tab-wrapper">
    <a class="nav-tab <?php echo $mode === 'day' ? 'nav-tab-active' : ''; ?>"
      href="<?php echo esc_url(admin_url('admin.php?page=hlcc-care-content&mode=day&project_key=' . $project_key . '&day_index=' . $day_index)); ?>">炎症期逐日（day0-5）</a>
    <a class="nav-tab <?php echo $mode === 'phase' ? 'nav-tab-active' : ''; ?>"
      href="<?php echo esc_url(admin_url('admin.php?page=hlcc-care-content&mode=phase&phase_key=' . $phase_key)); ?>">阶段模板（统一）</a>
  </h2>

  <?php if ($mode === 'day'): ?>
    <div class="hlcc-admin-card">
      <form method="get" action="">
        <input type="hidden" name="page" value="hlcc-care-content">
        <input type="hidden" name="mode" value="day">
        <label>项目：</label>
        <select name="project_key">
          <?php foreach (CycleRules::project_options() as $k => $label): ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($project_key === $k); ?>>
              <?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
        <label>天数：</label>
        <select name="day_index">
          <?php for ($d = 0; $d <= 5; $d++): ?>
            <option value="<?php echo (int) $d; ?>" <?php selected($day_index === $d); ?>>day<?php echo (int) $d; ?></option>
          <?php endfor; ?>
        </select>
        <button class="button">切换</button>
      </form>
      <hr />

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_save_care_day'); ?>
        <input type="hidden" name="action" value="hlcc_save_care_day">
        <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
        <input type="hidden" name="day_index" value="<?php echo (int) $day_index; ?>">

        <table class="form-table">
          <tr>
            <th>标题</th>
            <td>
              <?php
              $title_val = (string) ($row['title'] ?? '');
              wp_editor($title_val, 'hlcc_title', [
                'textarea_name' => 'title',
                'media_buttons' => false,
                'teeny' => false,
                'quicktags' => false,
                'textarea_rows' => 2,
                'tinymce' => [
                  'toolbar1' => 'fontsizeselect bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat',
                  'toolbar2' => '',
                  'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px',
                ],
              ]);
              ?>
            </td>
          </tr>
          <tr>
            <th>正文（今日护理）</th>
            <td>
              <div class="hlcc-admin-editor-tip">
                <span class="hlcc-tip-icon">💡</span>
                每一行都会自动生成一张<strong>「护理卡片」</strong>。若行首有 emoji（如 💊），会自动作为图标。
              </div>
              <?php wp_editor($row['body'] ?? '', 'hlcc_body', ['textarea_name' => 'body', 'media_buttons' => false, 'textarea_rows' => 6, 'teeny' => false, 'quicktags' => false, 'tinymce' => ['toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat', 'toolbar2' => '', 'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px']]); ?>
            </td>
          </tr>
          <tr>
            <th>今日重点</th>
            <td>
              <?php wp_editor($row['key_points'] ?? '', 'hlcc_key_points', ['textarea_name' => 'key_points', 'media_buttons' => false, 'textarea_rows' => 4, 'teeny' => false, 'quicktags' => false, 'tinymce' => ['toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat', 'toolbar2' => '', 'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px']]); ?>
            </td>
          </tr>
          <tr>
            <th>禁忌标题</th>
            <td>
              <?php
              $taboo_title_val = (string) ($row['taboo_title'] ?? '今日禁忌');
              wp_editor($taboo_title_val, 'hlcc_taboo_title', [
                'textarea_name' => 'taboo_title',
                'media_buttons' => false,
                'teeny' => false,
                'quicktags' => false,
                'textarea_rows' => 2,
                'tinymce' => [
                  'toolbar1' => 'fontsizeselect bold italic underline | alignleft aligncenter alignright | forecolor | link unlink | removeformat',
                  'toolbar2' => '',
                  'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px',
                ],
              ]);
              ?>
            </td>
          </tr>
          <tr>
            <th>禁忌内容（红卡）</th>
            <td>
              <div class="hlcc-admin-editor-tip hlcc-tip-red">
                <span class="hlcc-tip-icon">🚫</span>
                每一行都会自动生成一张<strong>「红色禁忌卡片」</strong>。请分行输入。
              </div>
              <?php wp_editor($row['taboo_body'] ?? '', 'hlcc_taboo_body', ['textarea_name' => 'taboo_body', 'media_buttons' => false, 'textarea_rows' => 4, 'teeny' => false, 'quicktags' => false, 'tinymce' => ['toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat', 'toolbar2' => '', 'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px']]); ?>
            </td>
          </tr>
          <tr>
            <th>底部补充</th>
            <td><input class="regular-text" name="footer_note" value="<?php echo esc_attr($row['footer_note'] ?? ''); ?>">
            </td>
          </tr>
        </table>

        <p class="description">提示：这里是“输入什么就显示什么”。支持 emoji。</p>
        <p><button class="button button-primary">保存 day<?php echo (int) $day_index; ?></button></p>
      </form>
    </div>

  <?php else: ?>
    <div class="hlcc-admin-card">
      <form method="get" action="">
        <input type="hidden" name="page" value="hlcc-care-content">
        <input type="hidden" name="mode" value="phase">
        <label>阶段模板：</label>
        <select name="phase_key">
          <option value="<?php echo esc_attr(Phase::SCAB); ?>" <?php selected($phase_key === Phase::SCAB); ?>>
            结痂/掉痂期（day6-12）</option>
          <option value="<?php echo esc_attr(Phase::RECOVERY); ?>" <?php selected($phase_key === Phase::RECOVERY); ?>>
            康复期（day13+）</option>
        </select>
        <button class="button">切换</button>
      </form>
      <hr />

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_save_care_phase'); ?>
        <input type="hidden" name="action" value="hlcc_save_care_phase">
        <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">

        <table class="form-table">
          <tr>
            <th>标题</th>
            <td>
              <?php
              $title_val = (string) ($row['title'] ?? '');
              wp_editor($title_val, 'hlcc_title', [
                'textarea_name' => 'title',
                'media_buttons' => false,
                'teeny' => false,
                'quicktags' => false,
                'textarea_rows' => 2,
                'tinymce' => [
                  'toolbar1' => 'fontsizeselect bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat',
                  'toolbar2' => '',
                  'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px',
                ],
              ]);
              ?>
            </td>
          </tr>
          <tr>
            <th>正文（换行保留）</th>
            <td>
              <?php wp_editor($row['body'] ?? '', 'hlcc_phase_body', ['textarea_name' => 'body', 'media_buttons' => false, 'textarea_rows' => 6, 'teeny' => false, 'quicktags' => false, 'tinymce' => ['toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat', 'toolbar2' => '', 'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px']]); ?>
            </td>
          </tr>
          <tr>
            <th>今日重点</th>
            <td>
              <?php wp_editor($row['key_points'] ?? '', 'hlcc_phase_key_points', ['textarea_name' => 'key_points', 'media_buttons' => false, 'textarea_rows' => 4, 'teeny' => false, 'quicktags' => false, 'tinymce' => ['toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat', 'toolbar2' => '', 'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px']]); ?>
            </td>
          </tr>
          <tr>
            <th>禁忌标题</th>
            <td>
              <?php
              $taboo_title_val = (string) ($row['taboo_title'] ?? '今日禁忌');
              wp_editor($taboo_title_val, 'hlcc_taboo_title', [
                'textarea_name' => 'taboo_title',
                'media_buttons' => false,
                'teeny' => false,
                'quicktags' => false,
                'textarea_rows' => 2,
                'tinymce' => [
                  'toolbar1' => 'fontsizeselect bold italic underline | alignleft aligncenter alignright | forecolor | link unlink | removeformat',
                  'toolbar2' => '',
                  'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px',
                ],
              ]);
              ?>
            </td>
          </tr>
          <tr>
            <th>禁忌内容（换行保留）</th>
            <td>
              <?php wp_editor($row['taboo_body'] ?? '', 'hlcc_phase_taboo_body', ['textarea_name' => 'taboo_body', 'media_buttons' => false, 'textarea_rows' => 4, 'teeny' => false, 'quicktags' => false, 'tinymce' => ['toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat', 'toolbar2' => '', 'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px']]); ?>
            </td>
          </tr>
          <tr>
            <th>底部补充</th>
            <td><input class="regular-text" name="footer_note" value="<?php echo esc_attr($row['footer_note'] ?? ''); ?>">
            </td>
          </tr>
        </table>

        <p class="description">提示：这里是“输入什么就显示什么”。支持 emoji。</p>
        <p><button class="button button-primary">保存阶段模板</button></p>
      </form>
    </div>
  <?php endif; ?>
</div>