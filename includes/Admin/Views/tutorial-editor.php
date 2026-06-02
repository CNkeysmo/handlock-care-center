<?php
use HLCC\Data\Repositories\TutorialRepository;
use HLCC\Domain\CycleRules;
use HLCC\Domain\Phase;

if (!defined('ABSPATH')) exit;

$repo = new TutorialRepository();

$project_key = isset($_GET['project_key']) ? sanitize_text_field(wp_unslash($_GET['project_key'])) : 'tattoo';
$phase_key = isset($_GET['phase_key']) ? sanitize_text_field(wp_unslash($_GET['phase_key'])) : Phase::INFLAMMATION;
$step_id = isset($_GET['step_id']) ? (int)$_GET['step_id'] : 0;

$tutorial = $repo->get_tutorial($project_key, $phase_key);
if (!$tutorial) {
    echo '<div class="wrap"><p>教程不存在，请重新激活插件或检查数据。</p></div>';
    return;
}
$steps = $repo->list_steps((int)$tutorial['id']);
if (!$step_id && $steps) $step_id = (int)$steps[0]['id'];
$current_step = null;
foreach ($steps as $s) { if ((int)$s['id'] === $step_id) { $current_step = $s; break; } }

$saved = isset($_GET['saved']);
?>
<div class="wrap hlcc-admin">
  <h1>换药教程（按项目 × 阶段）</h1>

  <?php if ($saved): ?>
    <div class="notice notice-success"><p>已保存。</p></div>
  <?php endif; ?>

  <div class="hlcc-admin-card">
    <form method="get" action="">
      <input type="hidden" name="page" value="hlcc-tutorials">
      <label>项目：</label>
      <select name="project_key">
        <?php foreach (CycleRules::project_options() as $k => $label): ?>
          <option value="<?php echo esc_attr($k); ?>" <?php selected($project_key===$k); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
      <label>阶段：</label>
      <select name="phase_key">
        <option value="<?php echo esc_attr(Phase::INFLAMMATION); ?>" <?php selected($phase_key===Phase::INFLAMMATION); ?>>炎症期（day0-5）</option>
        <option value="<?php echo esc_attr(Phase::SCAB); ?>" <?php selected($phase_key===Phase::SCAB); ?>>结痂/掉痂期（day6-12）</option>
        <option value="<?php echo esc_attr(Phase::RECOVERY); ?>" <?php selected($phase_key===Phase::RECOVERY); ?>>康复期（day13+）</option>
      </select>
      <button class="button">切换</button>
    </form>
  </div>

  <div class="hlcc-tutorial-layout">
    <div class="hlcc-tutorial-left hlcc-admin-card">
      <h2>步骤列表</h2>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:10px;">
        <?php wp_nonce_field('hlcc_tutorial_save_title'); ?>
        <input type="hidden" name="action" value="hlcc_tutorial_save_title">
        <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
        <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">
        <label>教程标题（可选）：</label>
        <input class="regular-text" name="title" value="<?php echo esc_attr($tutorial['title'] ?? ''); ?>" placeholder="不填则前台不显示" />
        <button class="button">保存标题</button>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_tutorial_add_step'); ?>
        <input type="hidden" name="action" value="hlcc_tutorial_add_step">
        <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
        <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">
        <button class="button button-primary">+ 新增步骤</button>
      </form>

      <ol class="hlcc-step-list">
        <?php foreach ($steps as $i => $s): ?>
          <?php $active = ((int)$s['id'] === (int)$step_id); ?>
          <li class="<?php echo $active ? 'active' : ''; ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=hlcc-tutorials&project_key='.$project_key.'&phase_key='.$phase_key.'&step_id='.(int)$s['id'])); ?>">
              <?php
                $plain = !empty($s['step_title']) ? wp_strip_all_tags((string)$s['step_title']) : '';
                echo $plain ? esc_html($plain) : ('步骤 '.($i+1));
              ?>
            </a>
            <div class="hlcc-step-actions">
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hlcc_tutorial_move_step'); ?>
                <input type="hidden" name="action" value="hlcc_tutorial_move_step">
                <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
                <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">
                <input type="hidden" name="step_id" value="<?php echo (int)$s['id']; ?>">
                <input type="hidden" name="dir" value="up">
                <button class="button small">↑</button>
              </form>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hlcc_tutorial_move_step'); ?>
                <input type="hidden" name="action" value="hlcc_tutorial_move_step">
                <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
                <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">
                <input type="hidden" name="step_id" value="<?php echo (int)$s['id']; ?>">
                <input type="hidden" name="dir" value="down">
                <button class="button small">↓</button>
              </form>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('确定删除此步骤？');">
                <?php wp_nonce_field('hlcc_tutorial_delete_step'); ?>
                <input type="hidden" name="action" value="hlcc_tutorial_delete_step">
                <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
                <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">
                <input type="hidden" name="step_id" value="<?php echo (int)$s['id']; ?>">
                <button class="button small">删</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>

      <p class="description">v0.1：排序用 ↑↓（稳定降级）。后续可加拖拽，但不会影响现有功能。</p>
    </div>

    <div class="hlcc-tutorial-right hlcc-admin-card">
      <h2>编辑步骤</h2>
      <?php if (!$current_step): ?>
        <p>请先新增一个步骤。</p>
      <?php else: ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('hlcc_tutorial_save_step'); ?>
          <input type="hidden" name="action" value="hlcc_tutorial_save_step">
          <input type="hidden" name="project_key" value="<?php echo esc_attr($project_key); ?>">
          <input type="hidden" name="phase_key" value="<?php echo esc_attr($phase_key); ?>">
          <input type="hidden" name="step_id" value="<?php echo (int)$current_step['id']; ?>">

          <table class="form-table">
            <tr>
              <th>步骤标题（可选）</th>
              <td>
                <?php
                  wp_editor($current_step['step_title'] ?? '', 'hlcc_step_title', [
                    'textarea_name' => 'step_title',
                    'media_buttons' => true,
                    'textarea_rows' => 2,
                    'teeny' => false,
                    'quicktags' => false,
                    'tinymce' => [
                      'toolbar1' => 'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | removeformat',
                      'toolbar2' => '',
                      'fontsize_formats' => '12px 14px 16px 18px 20px 24px 28px 32px'
                    ]
                  ]);
                ?>
              </td>
            </tr>
            <tr><th>步骤文字（换行保留）</th><td><?php wp_editor($current_step['step_text'] ?? '', 'hlcc_step_text', ['textarea_name'=>'step_text','media_buttons'=>true,'textarea_rows'=>8,'teeny'=>false,'quicktags'=>false,'tinymce'=>['toolbar1'=>'formatselect fontsizeselect | bold italic underline | alignleft aligncenter alignright | forecolor | bullist numlist | link unlink | removeformat','toolbar2'=>'','fontsize_formats'=>'12px 14px 16px 18px 20px 24px 28px 32px']]); ?></td></tr>
            <tr><th>视频 URL（可选）</th><td><input class="large-text" name="video_url" value="<?php echo esc_attr($current_step['video_url'] ?? ''); ?>" placeholder="https://..."></td></tr>
          </table>

          <p><button class="button button-primary">保存此步骤</button></p>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
