<?php
use HLCC\App\Services\CourseService;
use HLCC\Domain\CycleRules;
use HLCC\Domain\Phase;
use HLCC\Domain\PhaseRules;
use HLCC\Domain\DayCalculator;

if (!defined('ABSPATH'))
  exit;

$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if (!$user_id)
  wp_die('缺少 user_id');

$user = get_user_by('id', $user_id);
if (!$user)
  wp_die('用户不存在');

$svc = new CourseService();
$courses = $svc->list_courses($user_id);
$active = $svc->get_active_course_for_user($user_id);
$saved = isset($_GET['saved']);
?>
<div class="wrap hlcc-admin">
  <h1>疗程管理：<?php echo esc_html($user->user_login); ?>（ID <?php echo (int) $user_id; ?>）</h1>

  <?php if ($saved): ?>
    <div class="notice notice-success">
      <p>已保存。</p>
    </div>
  <?php endif; ?>

  <p><a href="<?php echo esc_url(admin_url('admin.php?page=hlcc-customers')); ?>">&larr; 返回客户列表</a></p>

  <div class="hlcc-admin-grid">
    <div class="hlcc-admin-card">
      <h2>新增疗程</h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('hlcc_create_course'); ?>
        <input type="hidden" name="action" value="hlcc_create_course" />
        <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
        <table class="form-table">
          <tr>
            <th>项目</th>
            <td>
              <select name="project_key">
                <?php foreach (CycleRules::project_options() as $k => $label): ?>
                  <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <tr>
            <th>自定义周期 (天)</th>
            <td><input type="number" name="custom_cycle_days" class="small-text" placeholder="默认" min="1" />
              <p class="description">留空则使用默认周期。</p>
            </td>
          </tr>
          <tr>
            <th>操作日期</th>
            <td><input name="procedure_date" type="date" max="<?php echo esc_attr(current_time('Y-m-d')); ?>"
                required /></td>
          </tr>
          <tr>
            <th>备注</th>
            <td><input class="regular-text" name="note" /></td>
          </tr>
          <tr>
            <th>设为当前疗程</th>
            <td><label><input type="checkbox" name="make_active" checked> 激活</label></td>
          </tr>
        </table>
        <p><button class="button button-primary">创建疗程</button></p>
      </form>
    </div>

    <div class="hlcc-admin-card">
      <h2>疗程列表</h2>
      <?php if (!$courses): ?>
        <p>暂无疗程。</p>
      <?php else: ?>
        <?php foreach ($courses as $c): ?>
          <div class="hlcc-course-block <?php echo ((int) $c['is_active'] === 1) ? 'active' : ''; ?>">
            <div class="hlcc-course-head">
              <?php
              $c_project = (string) ($c['project_key'] ?? 'tattoo');
              $c_datetime = isset($c['procedure_datetime']) ? (string) $c['procedure_datetime'] : null;
              $c_date = (string) ($c['procedure_date'] ?? '');
              $c_day = DayCalculator::day_index($c_datetime, $c_date);
              if ($c_day < 0)
                $c_day = 0;
              $c_cycle = CycleRules::cycle_days($c_project);
              $c_remain = DayCalculator::remaining_days($c_project, $c_day);
              $c_sys_phase = PhaseRules::phase_by_day($c_day);
              $c_eff_phase = !empty($c['phase_override']) ? (string) $c['phase_override'] : $c_sys_phase;
              ?>
              <strong><?php echo esc_html(CycleRules::project_label($c_project)); ?></strong>
              <span class="hlcc-muted">#<?php echo (int) $c['id']; ?></span>
              <?php if ((int) $c['is_active'] === 1): ?>
                <span class="hlcc-tag">当前</span>
              <?php endif; ?>
            </div>
            <div class="hlcc-course-meta">
              <span class="hlcc-muted">周期 <?php echo (int) $c_cycle; ?> 天</span>
              <span class="hlcc-muted">· 已进行 <?php echo (int) $c_day; ?> 天</span>
              <span class="hlcc-muted">· 剩余 <?php echo (int) $c_remain; ?> 天</span>
              <span class="hlcc-muted">· 阶段：<?php echo esc_html(Phase::label($c_eff_phase)); ?></span>
              <?php if (!empty($c['phase_override'])): ?>
                <span class="hlcc-tag hlcc-tag-warn">已覆盖</span>
              <?php endif; ?>
            </div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline">
              <?php wp_nonce_field('hlcc_update_course'); ?>
              <input type="hidden" name="action" value="hlcc_update_course" />
              <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
              <input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>" />
              <div class="hlcc-row">
                <label>操作日期</label>
                <input name="procedure_date" type="date" max="<?php echo esc_attr(current_time('Y-m-d')); ?>"
                  value="<?php echo esc_attr($c['procedure_date']); ?>" required />
                <label>自定义周期</label>
                <input name="custom_cycle_days" type="number" class="small-text" style="width: 60px;" placeholder="默认"
                  min="1" value="<?php echo esc_attr($c['custom_cycle_days'] ?? ''); ?>" />
                <label>备注</label>
                <input name="note" class="regular-text" value="<?php echo esc_attr($c['note'] ?? ''); ?>" />
                <button class="button">保存</button>
              </div>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              class="hlcc-inline hlcc-inline-delete" onsubmit="return confirm('确认删除该疗程？');">
              <?php wp_nonce_field('hlcc_delete_course'); ?>
              <input type="hidden" name="action" value="hlcc_delete_course" />
              <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
              <input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>" />
              <button class="button button-link-delete" type="submit">删除</button>
            </form>


            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline">
              <?php wp_nonce_field('hlcc_set_phase_override'); ?>
              <input type="hidden" name="action" value="hlcc_set_phase_override" />
              <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
              <input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>" />
              <div class="hlcc-row">
                <label>阶段覆盖</label>
                <select name="phase_override">
                  <option value="" <?php selected(empty($c['phase_override'])); ?>>系统自动</option>
                  <option value="<?php echo esc_attr(Phase::INFLAMMATION); ?>" <?php selected(($c['phase_override'] ?? '') === Phase::INFLAMMATION); ?>>炎症期</option>
                  <option value="<?php echo esc_attr(Phase::SCAB); ?>" <?php selected(($c['phase_override'] ?? '') === Phase::SCAB); ?>>结痂/掉痂期</option>
                  <option value="<?php echo esc_attr(Phase::RECOVERY); ?>" <?php selected(($c['phase_override'] ?? '') === Phase::RECOVERY); ?>>康复期</option>
                </select>
                <button class="button">应用</button>
                <span class="hlcc-muted">（仅影响护理内容/换药流程，不影响 day 与周期）</span>
              </div>
            </form>

            <?php
            // 项目切换表单，可切换项目类型（保留疗程照片）
            $current_project_key = CycleRules::normalize_project_key($c['project_key'] ?? 'tattoo');
            $project_options = CycleRules::project_options(true);
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline"
              onsubmit="return confirm('确认切换项目类型？\n\n切换后将重置操作日期为今天。\n已有照片不会丢失。');">
              <?php wp_nonce_field('hlcc_switch_project'); ?>
              <input type="hidden" name="action" value="hlcc_switch_project" />
              <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
              <input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>" />
              <div class="hlcc-row">
                <label>切换项目</label>
                <select name="new_project_key">
                  <?php foreach ($project_options as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_project_key, $key); ?>>
                      <?php echo esc_html($label); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="button button-secondary">切换</button>
                <span class="hlcc-muted">（保留照片，重置日期）</span>
              </div>
            </form>

            <?php if ((int) $c['is_active'] !== 1): ?>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="hlcc-inline">
                <?php wp_nonce_field('hlcc_set_active_course'); ?>
                <input type="hidden" name="action" value="hlcc_set_active_course" />
                <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>" />
                <input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>" />
                <button class="button button-secondary">设为当前疗程</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>



      <div class="hlcc-admin-card">
        <h2>疗程图片对比</h2>
        <p>疗程图片的上传、时间线、排序与对比图生成，已经集中到「疗程图片对比」后台页面管理。</p>
        <p>
          <a class="button button-primary"
            href="<?php echo esc_url(admin_url('admin.php?page=hlcc-photo-compare&user_id=' . (int) $user_id)); ?>">打开放大管理页</a>
        </p>
      </div>