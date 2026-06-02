<?php
use HLCC\Support\Helpers;
use HLCC\Domain\CycleRules;
use HLCC\Frontend\SelfcheckTips;

if (!defined('ABSPATH'))
  exit;

$is_app_mode = isset($_GET['hlcc_app']) && (string) $_GET['hlcc_app'] === '1';
$logout_target = $is_app_mode ? add_query_arg('hlcc_app', '1', home_url('/care/')) : home_url('/care/');
$logout_url = wp_logout_url($logout_target);
$account_url = add_query_arg(['tab' => 'account']);
$project_label = CycleRules::project_label($project_key);
?>
<!-- Modern Changelog Design v8.7.4 - Inline Critical CSS -->
<style id="hlcc-force-ui-v874">
  /* Changelog Modern Card Layout */
  .hlcc-wrap .hlcc-tab-content#hlcc-tab-changelog {
    padding: 16px 12px !important;
    background: #f8fafc !important;
  }

  .hlcc-wrap .hlcc-changelog-compact {
    margin: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 16px !important;
  }

  /* Card Container - Ultra Strong Selectors */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-compact .hlcc-changelog-row {
    display: flex !important;
    align-items: flex-start !important;
    gap: 14px !important;
    padding: 18px 20px !important;
    background: #ffffff !important;
    border-radius: 16px !important;
    border: 2px solid #e2e8f0 !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.04) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    position: relative !important;
    overflow: visible !important;
  }

  /* Hover Effects - Enhanced */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-compact .hlcc-changelog-row:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16), 0 4px 8px rgba(0, 0, 0, 0.08) !important;
    border-color: #94a3b8 !important;
  }

  /* Background Gradient by Type - More Vibrant */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="new"] {
    border-left: 4px solid #10b981 !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="new"]:hover {
    background: linear-gradient(135deg, #d1fae5 0%, #ffffff 50%, #f0fdf4 100%) !important;
    border-color: #10b981 !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="improve"] {
    border-left: 4px solid #3b82f6 !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="improve"]:hover {
    background: linear-gradient(135deg, #dbeafe 0%, #ffffff 50%, #eff6ff 100%) !important;
    border-color: #3b82f6 !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="fix"] {
    border-left: 4px solid #f59e0b !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="fix"]:hover {
    background: linear-gradient(135deg, #fef3c7 0%, #ffffff 50%, #fffbeb 100%) !important;
    border-color: #f59e0b !important;
  }

  /* Icon Container - Larger & More Prominent */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-icon {
    width: 48px !important;
    height: 48px !important;
    border-radius: 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="new"] .hlcc-changelog-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="improve"] .hlcc-changelog-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row[data-type="fix"] .hlcc-changelog-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.4) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-icon svg {
    width: 24px !important;
    height: 24px !important;
    stroke-width: 2.5 !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-row:hover .hlcc-changelog-icon {
    transform: scale(1.15) rotate(8deg) !important;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3) !important;
  }

  /* Content Area */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-content {
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
  }

  /* Header (Badge + Date) */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-header {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    flex-wrap: wrap !important;
    margin-bottom: 2px !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-badge {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    padding: 4px 10px !important;
    border-radius: 6px !important;
    color: #fff !important;
    white-space: nowrap !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-tag-new {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-tag-improve {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-tag-fix {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-date {
    font-size: 11px !important;
    color: #64748b !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    background: #f1f5f9 !important;
    padding: 3px 8px !important;
    border-radius: 4px !important;
  }

  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-date:before {
    content: "📅 " !important;
    opacity: 0.8 !important;
  }

  /* Title - Larger & Bold */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-title {
    font-size: 15px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    line-height: 1.4 !important;
    letter-spacing: -0.02em !important;
    margin-bottom: 2px !important;
  }

  /* Description Text */
  .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-text {
    font-size: 12px !important;
    line-height: 1.6 !important;
    color: #64748b !important;
    word-break: break-word !important;
  }

  /* Previous Changelog in Letter (Keep small too) */
  .hlcc-wrap #hlcc-changelog {
    margin: 5px 0 0 !important;
    padding: 4px 8px !important;
    border-top: 1px dotted #e2e8f0 !important;
    background: #fcfcfc !important;
  }

  .hlcc-wrap #hlcc-changelog h4 {
    font-size: 10px !important;
    margin: 0 0 2px !important;
    transform: scale(0.9);
    transform-origin: left;
  }

  .hlcc-wrap #hlcc-changelog ul.hlcc-tm-list li {
    font-size: 9px !important;
    line-height: 1.1 !important;
    margin: 0 !important;
    padding: 0 0 1px 0 !important;
  }

  /* Responsive Adjustment */
  @media (max-width: 480px) {
    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-compact .hlcc-changelog-row {
      gap: 12px !important;
      padding: 16px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-icon {
      width: 42px !important;
      height: 42px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-icon svg {
      width: 20px !important;
      height: 20px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-header {
      gap: 8px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-badge {
      font-size: 10px !important;
      padding: 3px 7px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-date {
      font-size: 10px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-title {
      font-size: 14px !important;
    }

    .hlcc-wrap #hlcc-tab-changelog .hlcc-changelog-text {
      font-size: 11px !important;
    }
  }
</style>
<div class="hlcc-wrap<?php echo $is_app_mode ? ' hlcc-wrap-app' : ''; ?>" id="hlcc-root">
  <div class="hlcc-topbar">
    <div class="hlcc-brand">
      <div class="hlcc-brand-line1"><span class="hlcc-brand-main">HANDLock</span><span class="hlcc-brand-x">X</span>
      </div>
      <div class="hlcc-brand-line2">RECOVERY SYSTEM</div>
    </div>
    <div class="hlcc-top-actions">


      <?php if (isset($_GET['tab']) && $_GET['tab'] === 'account'): ?>
        <a class="hlcc-btn ghost" href="<?php echo esc_url(remove_query_arg('tab')); ?>">返回</a>
      <?php else: ?>
        <a class="hlcc-btn ghost" href="<?php echo esc_url($account_url); ?>">账号设置</a>
      <?php endif; ?>





      <a class="hlcc-link" href="<?php echo esc_url($logout_url); ?>">
        <?php echo \HLCC\Support\Helpers::get_icon('log-out', 'hlcc-icon-sm'); ?> Exit
      </a>
    </div>
  </div>

  <?php
  // Sanitize tab parameter to prevent XSS
  $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
  ?>
  <div class="hlcc-main<?php echo ($current_tab === 'account') ? ' hlcc-main-account' : ''; ?>" id="hlcc-main">

    <?php if ($current_tab === 'account'): ?>
      <?php
      $is_preview = (isset($_GET['hlcc_preview_user']) && current_user_can('manage_options') && (int) $_GET['hlcc_preview_user'] === (int) $user_id);
      $courseRepo = new \HLCC\Data\Repositories\CourseRepository();
      $courses = $courseRepo->list_by_user((int) $user_id);
      $active_id = !empty($course['id']) ? (int) $course['id'] : 0;
      ?>
      <div class="hlcc-account-layout">
        <h3 class="hlcc-account-title">账号设置</h3>

        <?php if ($is_preview): ?>
          <p class="hlcc-muted">管理员预览中：当前查看的是用户 ID <?php echo (int) $user_id; ?>。</p>
        <?php endif; ?>


        <div class="hlcc-account-block">
          <div class="hlcc-section-title">我的疗程</div>
          <?php if (empty($courses)): ?>
            <p class="hlcc-muted">暂无疗程，请联系诊所。</p>
          <?php else: ?>
            <?php foreach ($courses as $c): ?>
              <?php
              $ck = (string) ($c['project_key'] ?? 'tattoo');
              $ck = CycleRules::normalize_project_key($ck);
              $clabel = CycleRules::project_label($ck);
              $cid = (int) $c['id'];
              $cdate = (string) ($c['procedure_date'] ?? '');
              $cnote = (string) ($c['note'] ?? '');
              $is_active = ((int) ($c['is_active'] ?? 0) === 1);
              $max_date = current_time('Y-m-d');
              ?>
              <div class="hlcc-course-item<?php echo $is_active ? ' is-active' : ''; ?>">
                <div class="hlcc-course-compact">
                  <div class="hlcc-course-left">
                    <div class="hlcc-course-name">
                      <?php echo esc_html($clabel); ?>
                      <?php if ($is_active): ?><span class="hlcc-pill">当前</span><?php endif; ?>
                    </div>
                    <div class="hlcc-course-sub">操作日期：<?php echo esc_html($cdate); ?></div>
                  </div>

                  <details class="hlcc-course-more">
                    <summary class="hlcc-btn hlcc-btn-tertiary hlcc-btn-sm">
                      <?php echo \HLCC\Support\Helpers::get_icon('more-horizontal', 'hlcc-icon-sm'); ?> 更多
                    </summary>
                    <div class="hlcc-more-panel">

                      <?php if ($cnote !== ''): ?>
                        <div class="hlcc-more-row"><span class="hlcc-more-label">备注</span><span
                            class="hlcc-more-value"><?php echo esc_html($cnote); ?></span></div>
                      <?php endif; ?>

                      <div class="hlcc-more-actions">
                        <?php if (!$is_active): ?>
                          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="hlcc_front_set_active_course">
                            <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                            <?php wp_nonce_field('hlcc_front_set_active_course'); ?>
                            <button class="hlcc-btn hlcc-btn-sm" type="submit">设为当前</button>
                          </form>
                        <?php endif; ?>

                        <details class="hlcc-course-details">
                          <summary class="hlcc-btn hlcc-btn-secondary hlcc-btn-sm">
                            <?php echo \HLCC\Support\Helpers::get_icon('edit', 'hlcc-icon-sm'); ?> 编辑
                          </summary>
                          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                            class="hlcc-form hlcc-form-compact">
                            <input type="hidden" name="action" value="hlcc_front_update_course">
                            <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                            <?php wp_nonce_field('hlcc_front_update_course'); ?>

                            <label class="hlcc-field">
                              <span>操作日期</span>
                              <input type="date" name="procedure_date" value="<?php echo esc_attr($cdate); ?>"
                                max="<?php echo esc_attr($max_date); ?>">
                            </label>

                            <label class="hlcc-field">
                              <span>备注</span>
                              <input type="text" name="note" value="<?php echo esc_attr($cnote); ?>" placeholder="例如：大腿 / 左眉">
                            </label>

                            <button class="hlcc-btn hlcc-btn-secondary" type="submit">保存</button>
                          </form>
                        </details>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                          onsubmit="return confirm('确定要删除此疗程吗？');">
                          <input type="hidden" name="action" value="hlcc_front_delete_course">
                          <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
                          <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                          <?php wp_nonce_field('hlcc_front_delete_course'); ?>
                          <button class="hlcc-btn hlcc-btn-danger hlcc-btn-sm" type="submit">
                            <?php echo \HLCC\Support\Helpers::get_icon('trash-2', 'hlcc-icon-sm'); ?> 删除疗程
                          </button>
                        </form>

                        <?php if (current_user_can('manage_options')): ?>
                          <details class="hlcc-course-details hlcc-custom-cycle-details">
                            <summary class="hlcc-btn hlcc-btn-secondary hlcc-btn-sm"
                              style="background-color:#475569;border-color:#475569;">
                              <?php echo \HLCC\Support\Helpers::get_icon('timer', 'hlcc-icon-sm'); ?> 周期设置
                            </summary>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              class="hlcc-form hlcc-form-compact">
                              <input type="hidden" name="action" value="hlcc_front_update_custom_cycle">
                              <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
                              <?php wp_nonce_field('hlcc_front_custom_cycle'); ?>
                              <label class="hlcc-field">
                                <span>自定义周期 (天)</span>
                                <input type="number" name="custom_cycle_days" placeholder="默认" min="1"
                                  value="<?php echo esc_attr($c['custom_cycle_days'] ?? ''); ?>">
                              </label>
                              <p class="hlcc-muted">留空则使用默认周期</p>
                              <button class="hlcc-btn hlcc-btn-secondary" style="background-color:#475569;border-color:#475569;"
                                type="submit">保存设置</button>
                            </form>
                          </details>

                          <details class="hlcc-course-details hlcc-switch-project-details">
                            <summary class="hlcc-btn hlcc-btn-warning hlcc-btn-sm">
                              <?php echo \HLCC\Support\Helpers::get_icon('refresh-cw', 'hlcc-icon-sm'); ?> 切换项目
                            </summary>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                              class="hlcc-form hlcc-form-compact"
                              onsubmit="return confirm('确认切换项目类型？\n\n切换后将重置操作日期为今天。\n已有照片不会丢失。');">
                              <input type="hidden" name="action" value="hlcc_front_switch_project">
                              <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
                              <?php wp_nonce_field('hlcc_front_switch_project'); ?>
                              <label class="hlcc-field">
                                <span>新项目类型</span>
                                <select name="new_project_key" class="hlcc-select">
                                  <?php foreach (CycleRules::project_options(true) as $pk => $pl): ?>
                                    <option value="<?php echo esc_attr($pk); ?>" <?php selected($ck, $pk); ?>>
                                      <?php echo esc_html($pl); ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </label>
                              <p class="hlcc-muted">保留照片，重置日期为今天</p>
                              <button class="hlcc-btn hlcc-btn-warning" type="submit">确认切换</button>
                            </form>
                          </details>
                        <?php endif; ?>
                      </div>
                    </div>
                  </details>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>


          <?php if (!$is_preview): ?>
            <details class="hlcc-course-add">
              <summary class="hlcc-course-summary">
                <?php echo \HLCC\Support\Helpers::get_icon('plus', 'hlcc-icon-sm'); ?> 新增疗程
              </summary>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="hlcc_front_create_course">
                <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                <?php wp_nonce_field('hlcc_front_create_course'); ?>

                <label class="hlcc-field">
                  <span>项目</span>
                  <select name="project_key" class="hlcc-select">
                    <?php foreach (CycleRules::project_options_plain() as $pk => $pl): ?>
                      <option value="<?php echo esc_attr($pk); ?>"><?php echo esc_html($pl); ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>

                <label class="hlcc-field">
                  <span>操作日期</span>
                  <input type="date" name="procedure_date" required max="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                </label>

                <label class="hlcc-field">
                  <span>备注</span>
                  <input type="text" name="note" placeholder="例如：大腿 / 左眉">
                </label>

                <label class="hlcc-field hlcc-field-inline">
                  <input type="checkbox" name="make_active" value="1" checked>
                  <span>设为当前疗程</span>
                </label>

                <button class="hlcc-btn" type="submit">创建疗程</button>
              </form>
            </details>
          <?php endif; ?>
        </div>

        <!-- 密码修改和显示风格设置已移除 - 统一由后台管理 -->

      </div>
      <?php return; ?>
    <?php endif; ?>


    <?php
    // v6: 登录后项目滑动条（按用户全部疗程显示）
    $courseRepoSlider = new \HLCC\Data\Repositories\CourseRepository();
    $courses_for_slider = $courseRepoSlider->list_by_user((int) $user_id);
    ?>

    <?php if (!empty($courses_for_slider)): ?>
      <?php
      // 使用前台疗程切换时间线替代旧滑动条
      $courses_for_timeline = $courses_for_slider;
      $user_id = isset($user_id) ? (int) $user_id : get_current_user_id();
      include HLCC_PLUGIN_DIR . 'includes/Frontend/Views/course-timeline.php';
      ?>
    <?php endif; ?>



    <!-- Online Stats Interstitial (v7.5.3) -->
    <?php if (!empty($online_stats)): ?>
      <div class="hlcc-status-interstitial">
        <span class="hlcc-online-dot"></span>
        <span class="hlcc-status-text">
          <?php echo (int) $online_stats['online']; ?>人在线 &bull; 今日已守护 <?php echo (int) $online_stats['daily']; ?> 人
        </span>
      </div>
    <?php endif; ?>

    <!-- Three-Phase Progress Card (v6.10.0) -->
    <div class="hlcc-card hlcc-card-outline hlcc-phase-progress-card">
      <div class="hlcc-progress-head">
        已进行 <span class="hlcc-pill hlcc-pill-project"><?php echo esc_html($project_label); ?></span>
        <?php if (!empty($note)): ?><span
            class="hlcc-pill hlcc-pill-note"><?php echo esc_html($note); ?></span><?php endif; ?>
      </div>

      <div class="hlcc-meta" style="margin-bottom: 16px;">操作日期：<?php echo esc_html($procedure_date); ?></div>

      <!-- 炎症期 -->
      <div class="hlcc-phase-item" data-phase="inflammation"
        data-status="<?php echo esc_attr($phase_progress['inflammation']['status']); ?>"
        data-collapsible="<?php echo $phase_progress['inflammation']['status'] !== 'active' ? 'true' : 'false'; ?>">
        <div
          class="<?php echo $phase_progress['inflammation']['status'] !== 'active' ? 'hlcc-phase-header hlcc-phase-header-clickable' : 'hlcc-phase-header'; ?>">
          <span class="hlcc-phase-label">
            <span
              class="hlcc-phase-icon-wrapper"><?php echo \HLCC\Support\Helpers::get_icon('activity', 'hlcc-phase-icon hlcc-text-rose'); ?></span>
            <?php echo esc_html($phase_progress['inflammation']['label']); ?>
            <span class="hlcc-phase-range"></span>
            <?php if (!empty($phase_progress['inflammation']['override_badge'])): ?>
              <span
                class="hlcc-phase-override-badge"><?php echo esc_html($phase_progress['inflammation']['override_badge']); ?></span>
            <?php endif; ?>
          </span>
          <span class="hlcc-phase-status">
            <?php if ($phase_progress['inflammation']['status'] === 'completed'): ?>
              <?php echo \HLCC\Support\Helpers::get_icon('check-circle', 'hlcc-status-icon hlcc-text-blue'); ?>
              <?php echo esc_html($phase_progress['inflammation']['status_text']); ?>
            <?php elseif ($phase_progress['inflammation']['status'] === 'active'): ?>
              <?php echo \HLCC\Support\Helpers::get_icon('timer', 'hlcc-status-icon hlcc-text-blue hlcc-animate-pulse'); ?>
              <?php echo esc_html($phase_progress['inflammation']['status_text']); ?>
            <?php else: ?>
              <?php echo \HLCC\Support\Helpers::get_icon('circle', 'hlcc-status-icon hlcc-text-muted-light'); ?>
              <?php echo esc_html($phase_progress['inflammation']['status_text']); ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="hlcc-phase-collapsible-content">
          <div class="hlcc-phase-progress">
            <div class="hlcc-phase-progress-bar"
              style="width: <?php echo esc_attr(number_format($phase_progress['inflammation']['progress'], 7, '.', '')); ?>%">
            </div>
          </div>
          <div class="hlcc-phase-progress-text">
            <span class="hlcc-phase-progress-pct"><?php
            $p_infl = number_format($phase_progress['inflammation']['progress'], 7, '.', '');
            $dot_pos = strpos($p_infl, '.');
            if ($dot_pos !== false) {
              echo esc_html(substr($p_infl, 0, $dot_pos + 3)) . '<small>' . esc_html(substr($p_infl, $dot_pos + 3)) . '</small>';
            } else {
              echo esc_html($p_infl);
            }
            ?>%</span>
          </div>
          <div class="hlcc-phase-tip">
            <?php echo \HLCC\Support\Helpers::parse_emoji($phase_progress['inflammation']['tip']); ?>
          </div>
        </div>
      </div>

      <!-- 结痂掉痂期 -->
      <div class="hlcc-phase-item" data-phase="scab"
        data-status="<?php echo esc_attr($phase_progress['scab']['status']); ?>"
        data-collapsible="<?php echo $phase_progress['scab']['status'] !== 'active' ? 'true' : 'false'; ?>">
        <div
          class="<?php echo $phase_progress['scab']['status'] !== 'active' ? 'hlcc-phase-header hlcc-phase-header-clickable' : 'hlcc-phase-header'; ?>">
          <span class="hlcc-phase-label">
            <span
              class="hlcc-phase-icon-wrapper"><?php echo \HLCC\Support\Helpers::get_icon('layers', 'hlcc-phase-icon hlcc-text-amber'); ?></span>
            <?php echo esc_html($phase_progress['scab']['label']); ?>
            <span class="hlcc-phase-range"></span>
            <?php if (!empty($phase_progress['scab']['override_badge'])): ?>
              <span
                class="hlcc-phase-override-badge"><?php echo esc_html($phase_progress['scab']['override_badge']); ?></span>
            <?php endif; ?>
          </span>
          <span class="hlcc-phase-status">
            <?php if ($phase_progress['scab']['status'] === 'completed'): ?>
              <?php echo \HLCC\Support\Helpers::get_icon('check-circle', 'hlcc-status-icon hlcc-text-blue'); ?>
              <?php echo esc_html($phase_progress['scab']['status_text']); ?>
            <?php elseif ($phase_progress['scab']['status'] === 'active'): ?>
              <?php echo \HLCC\Support\Helpers::get_icon('timer', 'hlcc-status-icon hlcc-text-blue hlcc-animate-pulse'); ?>
              <?php echo esc_html($phase_progress['scab']['status_text']); ?>
            <?php else: ?>
              <?php echo \HLCC\Support\Helpers::get_icon('circle', 'hlcc-status-icon hlcc-text-muted-light'); ?>
              <?php echo esc_html($phase_progress['scab']['status_text']); ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="hlcc-phase-collapsible-content">
          <div class="hlcc-phase-progress">
            <div class="hlcc-phase-progress-bar"
              style="width: <?php echo esc_attr(number_format($phase_progress['scab']['progress'], 7, '.', '')); ?>%">
            </div>
          </div>
          <div class="hlcc-phase-progress-text">
            <span class="hlcc-phase-progress-pct"><?php
            $p_scab = number_format($phase_progress['scab']['progress'], 7, '.', '');
            $dot_pos_s = strpos($p_scab, '.');
            if ($dot_pos_s !== false) {
              echo esc_html(substr($p_scab, 0, $dot_pos_s + 3)) . '<small>' . esc_html(substr($p_scab, $dot_pos_s + 3)) . '</small>';
            } else {
              echo esc_html($p_scab);
            }
            ?>%</span>
          </div>
          <div class="hlcc-phase-tip"><?php echo \HLCC\Support\Helpers::parse_emoji($phase_progress['scab']['tip']); ?>
          </div>
        </div>
      </div>

      <!-- 康复期 -->
      <div class="hlcc-phase-item" data-phase="recovery"
        data-status="<?php echo esc_attr($phase_progress['recovery']['status']); ?>"
        data-collapsible="<?php echo $phase_progress['recovery']['status'] !== 'active' ? 'true' : 'false'; ?>">
        <div
          class="<?php echo $phase_progress['recovery']['status'] !== 'active' ? 'hlcc-phase-header hlcc-phase-header-clickable' : 'hlcc-phase-header'; ?>">
          <span class="hlcc-phase-label">
            <span
              class="hlcc-phase-icon-wrapper"><?php echo \HLCC\Support\Helpers::get_icon('sprout', 'hlcc-phase-icon hlcc-text-emerald'); ?></span>
            <?php echo esc_html($phase_progress['recovery']['label']); ?>
            <span class="hlcc-phase-range"></span>
            <?php if (!empty($phase_progress['recovery']['override_badge'])): ?>
              <span
                class="hlcc-phase-override-badge"><?php echo esc_html($phase_progress['recovery']['override_badge']); ?></span>
            <?php endif; ?>
          </span>
          <span class="hlcc-phase-status">
            <?php if ($phase_progress['recovery']['status'] === 'completed'): ?>
              <?php echo \HLCC\Support\Helpers::get_icon('trophy', 'hlcc-status-icon hlcc-text-amber'); ?>
              <?php echo esc_html($phase_progress['recovery']['status_text']); ?>
            <?php elseif ($phase_progress['recovery']['status'] === 'active'): ?>
              <?php echo \HLCC\Support\Helpers::get_icon('timer', 'hlcc-status-icon hlcc-text-blue hlcc-animate-pulse'); ?>
              <?php echo esc_html($phase_progress['recovery']['status_text']); ?>
            <?php else: ?>
              <?php echo \HLCC\Support\Helpers::get_icon('circle', 'hlcc-status-icon hlcc-text-muted-light'); ?>
              <?php echo esc_html($phase_progress['recovery']['status_text']); ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="hlcc-phase-collapsible-content">
          <div class="hlcc-phase-progress">
            <div class="hlcc-phase-progress-bar"
              style="width: <?php echo esc_attr(number_format($phase_progress['recovery']['progress'], 7, '.', '')); ?>%">
            </div>
          </div>
          <div class="hlcc-phase-progress-text">
            <span class="hlcc-phase-progress-pct"><?php
            $p_rec = number_format($phase_progress['recovery']['progress'], 7, '.', '');
            $dot_pos_r = strpos($p_rec, '.');
            if ($dot_pos_r !== false) {
              echo esc_html(substr($p_rec, 0, $dot_pos_r + 3)) . '<small>' . esc_html(substr($p_rec, $dot_pos_r + 3)) . '</small>';
            } else {
              echo esc_html($p_rec);
            }
            ?>%</span>
            <span class="hlcc-day-progress-detail"></span>
          </div>
          <div class="hlcc-phase-tip">
            <?php echo \HLCC\Support\Helpers::parse_emoji($phase_progress['recovery']['tip']); ?>
          </div>
        </div>
      </div>

      <!-- Initialize realtime progress update -->
      <script>
        (function () {
          // Define data globally to handle script loading order
          window.hlccProgressData = <?php echo wp_json_encode($realtime_base); ?>;

          // Try to init if script is already loaded
          if (typeof window.hlccInitRealtimeProgress === 'function') {
            window.hlccInitRealtimeProgress(window.hlccProgressData);
          }
        })();
      </script>
    </div>

    <?php
    // 百科全书搜索组件 (v9.0.0)
    include HLCC_PLUGIN_DIR . 'includes/Frontend/Views/wiki-search.php';
    ?>

    <div class="hlcc-card-immersive hlcc-today">

      <!-- Immersive Header -->
      <div class="hlcc-immersive-header">
        <div class="hlcc-immersive-title-group">
          <h2 class="hlcc-immersive-title">今日护理</h2>
          <div class="hlcc-pills-row">
            <span class="hlcc-immersive-pill">恢复第 <?php echo (int) $day_index; ?> 天</span>
            <?php
            // 使用 Shortcodes.php 已计算好的 $effective_phase（含 override 自动解除逻辑）
            $action_pill_text = '';
            switch ($effective_phase) {
              case \HLCC\Domain\Phase::INFLAMMATION:
                $action_pill_text = '涂抹烫伤膏后包扎';
                break;
              case \HLCC\Domain\Phase::SCAB:
                $action_pill_text = '使用蓝冠薄涂成膜';
                break;
              case \HLCC\Domain\Phase::RECOVERY:
                $action_pill_text = '使用VE乳保湿';
                break;
            }
            ?>
            <?php if ($action_pill_text): ?>
              <span class="hlcc-immersive-pill hlcc-action-pill"><?php echo esc_html($action_pill_text); ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="hlcc-immersive-body">
        <?php
        // $effective_phase, $base_phase, $is_overridden 已在 Shortcodes.php 中计算（含 override 自动解除逻辑）
        ?>

        <?php if (isset($display_mode) && $display_mode !== 'minimal'): ?>
          <div class="hlcc-info-capsule-wrapper">
            <details class="hlcc-info-capsule">
              <summary class="hlcc-info-summary">
                <div class="hlcc-info-icon-group">
                  <?php echo \HLCC\Support\Helpers::get_icon('lightbulb', 'hlcc-info-icon-svg'); ?>
                  <span class="hlcc-info-label">为什么这样护理</span>
                </div>
                <span class="hlcc-info-arrow">›</span>
              </summary>
              <div class="hlcc-info-content">
                <div class="hlcc-why-main"><?php echo esc_html((string) $status_main); ?></div>
                <?php if (!empty($status_extra)): ?>
                  <div class="hlcc-why-extra"><?php echo esc_html((string) $status_extra); ?></div>
                <?php endif; ?>
              </div>
            </details>
          </div>
        <?php endif; ?>


        <?php /* hlcc-phase-meta removed: status_tag now shown in titlebar as pill */ ?>
        <?php if (!empty($care['title'])): ?>
          <div class="hlcc-h3 hlcc-title-rich"><?php echo Helpers::safe_html((string) $care['title']); ?></div>
        <?php endif; ?>

        <?php if (!empty($care['body'])): ?>
          <?php
          // v6.10.21: Apply Action Cards logic to Body content as well
          // This fixes the issue where users put list items in the Body field.
          echo Helpers::html_to_action_cards((string) $care['body']);
          ?>
        <?php endif; ?>

        <?php if (!empty($care['key_points'])): ?>
          <div class="hlcc-section">
            <?php
            // v6.10.19: Force Action Card layout by parsing HTML
            $kp = (string) $care['key_points'];
            echo Helpers::html_to_action_cards($kp);
            ?>
          </div>
        <?php endif; ?>

        <?php
        // 今日自检提示（基于 day_index 与恢复阶段）
        $hlcc_selfcheck_tips = SelfcheckTips::get_for_day((int) $day_index);
        if (!empty($hlcc_selfcheck_tips)):
          ?>
          <div class="hlcc-whyline hlcc-selfcheck-line">
            <details class="hlcc-why-details hlcc-selfcheck-details">
              <summary class="hlcc-why-summaryline">
                <div class="hlcc-why-title-group">
                  <span class="hlcc-badge-pulse">今日更新</span>
                  <span class="hlcc-why-title">今日自检提示</span>
                </div>
                <span class="hlcc-why-sub">轻点展开，查看今天需要特别留意的 3 项内容</span>
                <span class="hlcc-why-chevron">›</span>
              </summary>
              <div class="hlcc-why-body hlcc-selfcheck-body">
                <?php foreach ($hlcc_selfcheck_tips as $tip): ?>
                  <div class="hlcc-selfcheck-item">
                    <div class="hlcc-selfcheck-item-title">
                      <?php
                      $tip_title = (string) ($tip['title'] ?? '');
                      // Upgrade: Convert Emoji Squares to Badges
                      if (str_starts_with($tip_title, '🟥')) {
                        echo '<span class="hlcc-badge hlcc-badge-danger">重要</span> ';
                        echo esc_html(trim(str_replace('🟥', '', $tip_title)));
                      } elseif (str_starts_with($tip_title, '🟧')) {
                        echo '<span class="hlcc-badge hlcc-badge-warning">注意</span> ';
                        echo esc_html(trim(str_replace('🟧', '', $tip_title)));
                      } else {
                        echo esc_html($tip_title);
                      }
                      ?>
                    </div>
                    <?php if (!empty($tip['body'])): ?>
                      <div class="hlcc-selfcheck-item-text">
                        <?php echo Helpers::safe_html((string) $tip['body']); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          </div>
        <?php endif; ?>


        <?php if (!empty($care['taboo_body'])): ?>
          <div class="hlcc-taboo-container">
            <div class="hlcc-taboo-title-group">
              <span
                class="hlcc-taboo-header"><?php echo !empty($care['taboo_title']) ? esc_html($care['taboo_title']) : '禁忌事项'; ?></span>
              <span class="hlcc-taboo-sub">应避免的事项</span>
            </div>

            <?php
            // Use the new Taboo Card parser
            echo Helpers::html_to_taboo_cards((string) $care['taboo_body']);
            ?>
          </div>
        <?php endif; ?>


        <?php
        $hlcc_quotes = [
          '你已经做得很好，慢慢来就会更好。',
          '今天的护理完成，就离恢复更近一步。',
          '不舒服是暂时的，坚持就会看到变化。',
          '照顾好伤口，也是在照顾自己。',
          '稳稳地走，每一天都算数。',
          '有任何不适记得及时联系诊所，我们一直都在。',
        ];
        $hlcc_quote = $hlcc_quotes[wp_rand(0, count($hlcc_quotes) - 1)];
        ?>
        <div class="hlcc-daily-quote"><?php echo esc_html($hlcc_quote); ?></div>

        <?php if (!empty($care['footer_note'])): ?>
          <div class="hlcc-footer-note"><?php echo esc_html($care['footer_note']); ?></div>
        <?php endif; ?>
      </div> <!-- end .hlcc-immersive-body -->
    </div>

    <!-- Team Message Button (Moved in v8.3.2) -->
    <!-- Team Message Button (Moved in v8.3.2) -->
    <div style="text-align: center; margin: 24px 0 12px; position: relative;">
      <button type="button" id="hlcc-words-trigger-btn" class="hlcc-btn ghost is-pill">
        <?php echo \HLCC\Support\Helpers::get_icon('heart', 'hlcc-btn-icon-svg'); ?> 想对你们说的话
      </button>
    </div>

    <div class="hlcc-muted hlcc-version" style="text-align: center; margin-bottom: 30px;">行楽客户康复系统HLCC
      v<?php echo esc_html(defined('HLCC_VERSION') ? HLCC_VERSION : '8.3.21'); ?>
      By GINO</div>







  </div>
</div>

<!-- Floating action buttons (Moved out of container for safer fixed positioning) -->
<div id="hlcc-fab-clicklayer" class="hlcc-fab-clicklayer" style="display:none;" aria-hidden="true"></div>
<div class="hlcc-fab" aria-hidden="false">
  <div id="hlcc-fab-more-group" class="hlcc-fab-more-group" style="display:none;">
    <div class="hlcc-fab-item hlcc-fab-tutorial">
      <div class="hlcc-fab-label">换药教程</div>
      <button type="button" class="hlcc-fab-btn" aria-label="打开换药教程">
        <span class="hlcc-fab-icon" aria-hidden="true">
          <?php echo \HLCC\Support\Helpers::get_icon('book-open', 'hlcc-fab-icon-svg'); ?>
        </span>
      </button>
    </div>
    <div class="hlcc-fab-item hlcc-fab-stage">
      <div class="hlcc-fab-label">当前阶段切换</div>
      <button type="button" class="hlcc-fab-btn" aria-label="当前阶段切换">
        <span class="hlcc-fab-icon"
          aria-hidden="true"><?php echo \HLCC\Support\Helpers::get_icon('refresh-cw', 'hlcc-fab-icon-svg'); ?></span>
      </button>
    </div>
    <div class="hlcc-fab-item hlcc-fab-compare">
      <div class="hlcc-fab-label">疗程照片对比</div>
      <button type="button" class="hlcc-fab-btn" id="hlcc-fab-compare-toggle" aria-label="打开疗程照片对比">
        <span class="hlcc-fab-icon"
          aria-hidden="true"><?php echo \HLCC\Support\Helpers::get_icon('image', 'hlcc-fab-icon-svg'); ?></span>
      </button>
    </div>
  </div>

  <div class="hlcc-fab-item hlcc-fab-more">
    <div class="hlcc-fab-label">更多</div>
    <button type="button" class="hlcc-fab-btn hlcc-fab-btn-more" aria-label="更多">
      <span class="hlcc-fab-icon" aria-hidden="true"
        style="color: #fff; display: flex; align-items: center; justify-content: center;">
        <?php echo \HLCC\Support\Helpers::get_icon('plus', 'hlcc-fab-icon-svg'); ?>
      </span>
    </button>
  </div>

</div>


<!-- ============================================================
     HLCC WORDS TO YOU MODAL (v8.3.34) - TEST MODE ONLY
     ============================================================ -->
<!-- HLCC WORDS TO YOU MODAL (v8.5.1) -->
<div id="hlcc-words-modal" class="hlcc-modal" aria-hidden="true" style="display:none;">
  <div class="hlcc-modal-backdrop"></div>
  <div class="hlcc-modal-container">
    <div class="hlcc-modal-header">
      <div class="hlcc-segmented-control">
        <button type="button" class="hlcc-segment-btn active" data-target="letter">致每一位</button>
        <button type="button" class="hlcc-segment-btn" data-target="changelog">版本更新</button>
      </div>
      <button type="button" class="hlcc-modal-close" aria-label="关闭">
        <?php echo \HLCC\Support\Helpers::get_icon('x', 'hlcc-icon-svg'); ?>
      </button>
    </div>

    <div class="hlcc-modal-body">
      <!-- 1. Letter to You -->
      <div id="hlcc-tab-letter" class="hlcc-tab-content active">
        <div class="hlcc-letter-paper">
          <div class="hlcc-letter-header">亲爱的朋友：</div>
          <div class="hlcc-letter-body">
            <p>感谢你选择信任行楽。</p>
            <p>这一路走来，我们见证了无数个从焦虑到释然的故事。每一位来到这里的你，都带着改变的勇气。</p>
            <p>洗纹身不仅是一次物理上的消除，更是一次心理上的重塑。我们不仅仅是在提供技术，更是在守护一段段想要重新开始的决心。</p>
            <p>无论过程多么漫长，请相信，新的篇章终将展开。</p>
            <p>祝你，早日与过去的印记告别，拥抱全新的自己。</p>
          </div>
          <div class="hlcc-letter-footer">
            <div class="hlcc-letter-sign">行楽团队 敬上</div>
            <div class="hlcc-letter-date">
              <?php echo date('Y.m.d'); ?>
            </div>
          </div>
        </div>
      </div>

      <!-- 2. Changelog -->
      <div id="hlcc-tab-changelog" class="hlcc-tab-content">
        <div class="hlcc-changelog-compact">
          <!-- Version 9.3.0 -->
          <div class="hlcc-changelog-row" data-type="new">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14m7-7H5" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-new">v9.3.0</div>
                <div class="hlcc-changelog-date">2026.02.18</div>
              </div>
              <div class="hlcc-changelog-title">最新版本安卓端 App 已上线</div>
              <div class="hlcc-changelog-text">安卓用户现可安装 HLCC App，登录后可更稳定接收护理提醒、快速查看每日计划与消息。请联系门店获取安装包。</div>
            </div>
          </div>

          <!-- Version 9.2.21 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v9.2.21</div>
                <div class="hlcc-changelog-date">2026.02.16</div>
              </div>
              <div class="hlcc-changelog-title">成功打通推送提醒功能</div>
              <div class="hlcc-changelog-text">iOS Safari/Chrome 添加到桌面后，护理提醒推送链路已打通并可正常送达。</div>
            </div>
          </div>

          <!-- Version 9.1.5 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v9.1.5</div>
                <div class="hlcc-changelog-date">2026.02.15</div>
              </div>
              <div class="hlcc-changelog-title">百科词条支持引用论文链接</div>
              <div class="hlcc-changelog-text">知识库词条现在可以包含超链接，专业引用论文和参考资料清晰可见，蓝色下划线样式更易识别</div>
            </div>
          </div>

          <!-- Version 9.1.4 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v9.1.4</div>
                <div class="hlcc-changelog-date">2026.02.08</div>
              </div>
              <div class="hlcc-changelog-title">自检百科搜索体验升级</div>
              <div class="hlcc-changelog-text">支持单字符模糊搜索，搜索范围覆盖标题和内容，让你更快找到需要的护理知识</div>
            </div>
          </div>

          <!-- Version 9.1.0 -->
          <div class="hlcc-changelog-row" data-type="new">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-new">v9.1.0</div>
                <div class="hlcc-changelog-date">2026.02.07</div>
              </div>
              <div class="hlcc-changelog-title">全面升级自检百科功能</div>
              <div class="hlcc-changelog-text">新增 14 条专业洗纹身护理知识词条；百科词条支持配图展示；页面图标全面升级为精美 SVG 图标</div>
            </div>
          </div>

          <!-- Version 8.8.1 -->
          <div class="hlcc-changelog-row" data-type="new">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14m7-7H5" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-new">v8.8.1</div>
                <div class="hlcc-changelog-date">2026.02.07</div>
              </div>
              <div class="hlcc-changelog-title">留言板回复通知与动画优化</div>
              <div class="hlcc-changelog-text">当你的留言收到回复时，留言板按钮会显示小红点提醒；关闭弹窗添加流畅的缩小动效，体验更顺滑</div>
            </div>
          </div>

          <!-- Version 8.7.8 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v8.7.8</div>
                <div class="hlcc-changelog-date">2026.02.06</div>
              </div>
              <div class="hlcc-changelog-title">优化留言板位置</div>
              <div class="hlcc-changelog-text">留言板移至右下角悬浮按钮，点击即可打开。工作人员留言显示真实头像和认证标记，交流更安心</div>
            </div>
          </div>

          <!-- Version 8.7.6 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path
                  d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v8.7.6</div>
                <div class="hlcc-changelog-date">2026.02.06</div>
              </div>
              <div class="hlcc-changelog-title">优化版本更新展示效果</div>
              <div class="hlcc-changelog-text">让您更清楚地看到系统的每一次进步，更好的视觉体验帮助您了解我们为您做的每一份努力</div>
            </div>
          </div>

          <!-- Version 8.7.1 -->
          <div class="hlcc-changelog-row" data-type="new">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14m7-7H5" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-new">v8.7.1</div>
                <div class="hlcc-changelog-date">2026.02.05</div>
              </div>
              <div class="hlcc-changelog-title">升级客户数据安全防护</div>
              <div class="hlcc-changelog-text">所有康复档案加密备份，让你的隐私得到最高保护</div>
            </div>
          </div>

          <!-- Version 8.7.0 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v8.7.0</div>
                <div class="hlcc-changelog-date">2026.02.05</div>
              </div>
              <div class="hlcc-changelog-title">新增智能护理日志分析</div>
              <div class="hlcc-changelog-text">自动总结你的恢复趋势，帮助你更清楚地看到进展</div>
            </div>
          </div>

          <!-- Version 8.6.5 -->
          <div class="hlcc-changelog-row" data-type="new">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6v6l4 2" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-new">v8.6.5</div>
                <div class="hlcc-changelog-date">2026.02.04</div>
              </div>
              <div class="hlcc-changelog-title">推出分阶段护理贴心提醒</div>
              <div class="hlcc-changelog-text">让你在对的时间做对的护理，再也不用担心护理步骤</div>
            </div>
          </div>

          <!-- Version 8.6.0 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v8.6.0</div>
                <div class="hlcc-changelog-date">2026.02.03</div>
              </div>
              <div class="hlcc-changelog-title">建立专业护理知识库</div>
              <div class="hlcc-changelog-text">随时可查专家建议，为你的康复之路保驾护航</div>
            </div>
          </div>

          <!-- Version 8.5.1 -->
          <div class="hlcc-changelog-row" data-type="improve">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-improve">v8.5.1</div>
                <div class="hlcc-changelog-date">2026.02.02</div>
              </div>
              <div class="hlcc-changelog-title">优化留言板互动速度</div>
              <div class="hlcc-changelog-text">让你与我们的交流更顺畅，及时获得专业的护理指导</div>
            </div>
          </div>

          <!-- Version 8.5.0 -->
          <div class="hlcc-changelog-row" data-type="new">
            <div class="hlcc-changelog-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
            </div>
            <div class="hlcc-changelog-content">
              <div class="hlcc-changelog-header">
                <div class="hlcc-changelog-badge hlcc-tag-new">v8.5.0</div>
                <div class="hlcc-changelog-date">2026.02.01</div>
              </div>
              <div class="hlcc-changelog-title">新增「想对你们说的话」互动空间</div>
              <div class="hlcc-changelog-text">记录康复路上的感受，我们一直在倾听</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Tutorial floating panel -->
<div id="hlcc-tutorial-panel" class="hlcc-float-panel" aria-hidden="true">
  <div class="hlcc-float-panel-inner" role="dialog" aria-label="换药教程">
    <div class="hlcc-float-header">
      <div class="hlcc-float-title">换药教程 · <?php echo esc_html($phase_label); ?></div>
      <button type="button" class="hlcc-float-close" data-hlcc-close="tutorial" aria-label="关闭换药教程">×</button>
    </div>
    <div class="hlcc-float-body">
      <?php if (!empty($tpack['steps'])): ?>
        <ol class="hlcc-tutorial-steps">
          <?php foreach ($tpack['steps'] as $step): ?>
            <li class="hlcc-tutorial-step">
              <?php if (!empty($step['step_title'])): ?>
                <h4 class="hlcc-tutorial-step-title"><?php echo wp_kses(
                  $step['step_title'],
                  array(
                    'strong' => array(),
                    'b' => array(),
                    'em' => array(),
                    'i' => array(),
                    'u' => array(),
                    'a' => array('href' => array(), 'target' => array(), 'rel' => array(), 'class' => array()),
                    'span' => array('style' => array()),
                    'p' => array('style' => array()),
                    'br' => array(),
                    'img' => array(
                      'src' => array(),
                      'alt' => array(),
                      'title' => array(),
                      'width' => array(),
                      'height' => array(),
                      'class' => array(),
                      'style' => array(),
                      'srcset' => array(),
                      'sizes' => array(),
                      'loading' => array(),
                      'decoding' => array(),
                    ),
                    'figure' => array('class' => array(), 'style' => array()),
                    'figcaption' => array('class' => array(), 'style' => array()),
                  )
                ); ?></h4>
              <?php endif; ?>
              <?php if (!empty($step['step_text'])): ?>
                <p class="hlcc-tutorial-step-text"><?php echo wp_kses(
                  $step['step_text'],
                  array(
                    'strong' => array(),
                    'b' => array(),
                    'em' => array(),
                    'i' => array(),
                    'u' => array(),
                    'a' => array('href' => array(), 'target' => array(), 'rel' => array(), 'class' => array()),
                    'span' => array('style' => array()),
                    'p' => array('style' => array()),
                    'div' => array('class' => array(), 'style' => array()),
                    'br' => array(),
                    'img' => array(
                      'src' => array(),
                      'alt' => array(),
                      'title' => array(),
                      'width' => array(),
                      'height' => array(),
                      'class' => array(),
                      'style' => array(),
                      'srcset' => array(),
                      'sizes' => array(),
                      'loading' => array(),
                      'decoding' => array(),
                    ),
                    'figure' => array('class' => array(), 'style' => array()),
                    'figcaption' => array('class' => array(), 'style' => array()),
                  )
                ); ?></p>
              <?php endif; ?>
              <?php if (!empty($step['video_url'])): ?>
                <div class="hlcc-tutorial-video">
                  <?php
                  $video_url = $step['video_url'];
                  if (strpos($video_url, 'bilibili.com') !== false):
                    ?>
                    <div class="hlcc-bili-wrap" data-hlcc-bili="1" data-hlcc-bili-src="<?php echo esc_attr($video_url); ?>">
                      <button type="button" class="hlcc-bili-playbtn" aria-label="播放视频">▶</button>
                      <iframe class="hlcc-bilibili-player" src="<?php echo esc_url($video_url); ?>" frameborder="0"
                        allowfullscreen referrerpolicy="no-referrer-when-downgrade"
                        style="width:100%;aspect-ratio:16/9;border-radius:12px;"></iframe>
                    </div>
                  <?php else: ?>
                    <video class="hlcc-inline-video" src="<?php echo esc_url($video_url); ?>" controls playsinline
                      style="width:100%;border-radius:12px;"></video>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <p class="hlcc-muted hlcc-tutorial-empty">当前阶段暂未配置换药教程，如有疑问请向技师或诊所确认护理指引。</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Photo compare floating panel -->
<div id="hlcc-compare-panel" class="hlcc-float-panel hlcc-compare-panel" aria-hidden="true">
  <div class="hlcc-float-panel-inner" role="dialog" aria-label="疗程照片对比">
    <div class="hlcc-float-header">
      <div class="hlcc-float-title">疗程照片对比</div>
      <button type="button" class="hlcc-float-close" data-hlcc-close="compare" aria-label="关闭疗程照片对比">×</button>
    </div>
    <div class="hlcc-float-body">
      <?php
      // 构建后台疗程图片管理页面的链接，用于员工扫码后在手机后台直接上传
      $upload_admin_url = '';
      if (!empty($user_id) && !empty($course['id'])) {
        $upload_admin_url = add_query_arg(
          [
            'page' => 'hlcc-photo-compare',
            'user_id' => (int) $user_id,
            'course_id' => (int) $course['id'],
          ],
          admin_url('admin.php')
        );
      }
      ?>
      <?php if (!empty($treatment_photos)): ?>
        <div class="hlcc-compare-gallery">
          <?php
          $first_photo = $treatment_photos[0];
          $first_label = '原图';
          // 标签永远按后台时间线排序（数组顺序）来决定：#1=原图，#2=第一次疗程后，#3=第二次疗程后...
          // 这样后台删除/调整顺序后，前台不会出现“序号往后推”或保留旧标签的问题。
          ?>
          <div class="hlcc-compare-main">
            <div class="hlcc-compare-main-inner">
              <?php
              echo wp_get_attachment_image(
                (int) $first_photo['attachment_id'],
                'large',
                false,
                ['data-hlcc-compare-main' => '', 'alt' => esc_attr($first_label)]
              );
              ?>
            </div>
          </div>

          <div class="hlcc-compare-thumbs">
            <?php foreach ($treatment_photos as $idx => $photo):
              $label_title = ($idx === 0) ? '原图' : ('第' . $idx . '次疗程后');
              $label_date = !empty($photo['shot_at']) ? $photo['shot_at'] : '';
              ?>
              <button type="button" class="hlcc-compare-thumb<?php echo $idx === 0 ? ' is-active' : ''; ?>"
                data-hlcc-url="<?php echo esc_url($photo['url']); ?>"
                data-hlcc-label="<?php echo esc_attr($label_title . ($label_date ? ' · ' . $label_date : '')); ?>">
                <div class="hlcc-compare-thumb-inner">
                  <?php echo wp_get_attachment_image((int) $photo['attachment_id'], 'thumbnail'); ?>
                </div>
                <div class="hlcc-compare-thumb-meta">
                  <span class="hlcc-compare-thumb-index"><?php echo esc_html($label_title); ?></span>
                  <?php if (!empty($label_date)): ?>
                    <span class="hlcc-compare-thumb-date"><?php echo esc_html($label_date); ?></span>
                  <?php endif; ?>
                </div>
              </button>
            <?php endforeach; ?>
            <?php if (!empty($upload_admin_url)): ?> <button type="button" class="hlcc-compare-add-btn"
                data-hlcc-open-qr="compare" aria-label="行乐技术部门上传当前疗程照片">
                <div class="hlcc-compare-add-inner">
                  <span class="hlcc-compare-add-icon">＋</span>
                </div>
              </button>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($upload_admin_url)): ?>
          <div class="hlcc-compare-qr-inline" data-hlcc-qr-inline="compare" aria-hidden="true">
            <p class="hlcc-muted">如需继续为当前疗程增加照片，可让工作人员使用手机相机扫描下方二维码，在后台疗程图片管理页上传。</p>
            <div class="hlcc-compare-qr">
              <img
                src="<?php echo esc_url('https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode($upload_admin_url)); ?>"
                alt="扫描二维码上传疗程照片" width="300" height="300" />
            </div>
            <p class="hlcc-compare-empty-tip">上传完成后，请返回本页面刷新，再次打开「疗程照片对比」查看最新照片。</p>
            <button type="button" class="hlcc-btn hlcc-btn-secondary" data-hlcc-close-qr="compare">关闭</button>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <?php
        // 构建后台疗程图片管理页面的链接，用于员工扫码后在手机后台直接上传
        $upload_admin_url = '';
        if (!empty($user_id) && !empty($course['id'])) {
          $upload_admin_url = add_query_arg(
            [
              'page' => 'hlcc-photo-compare',
              'user_id' => (int) $user_id,
              'course_id' => (int) $course['id'],
            ],
            admin_url('admin.php')
          );
        }
        ?>
        <?php if (!empty($upload_admin_url)): ?>
          <div class="hlcc-compare-empty">
            <p class="hlcc-muted">当前疗程暂未上传任何照片，可让工作人员使用手机相机扫描下方二维码，进入后台疗程图片管理页进行上传。</p>
            <div class="hlcc-compare-qr">
              <img
                src="<?php echo esc_url('https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . rawurlencode($upload_admin_url)); ?>"
                alt="扫描二维码上传疗程照片" width="260" height="260" />
            </div>
            <p class="hlcc-compare-empty-tip">上传完成后，请返回本页面刷新，再次打开「疗程照片对比」查看。</p>
          </div>
        <?php else: ?>
          <p class="hlcc-muted">当前疗程暂未上传任何照片，稍后可在此查看疗程照片的对比画廊。</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- Phase switch warning panel -->
<div id="hlcc-phase-panel" class="hlcc-float-panel hlcc-phase-panel" aria-hidden="true">
  <div class="hlcc-float-panel-inner hlcc-phase-panel-inner" role="dialog" aria-label="当前阶段切换">
    <div class="hlcc-phase-pill">⚠️ 警告</div>
    <div class="hlcc-phase-title">行楽技术部门是否通知您可以提前进入下一阶段？</div>
    <div class="hlcc-phase-sub"></div>
    <div class="hlcc-phase-actions">
      <button type="button" class="hlcc-btn hlcc-btn-ghost" data-hlcc-phase="no">NO</button>
      <button type="button" class="hlcc-btn hlcc-btn-secondary" data-hlcc-phase="reset">恢复到当前阶段(系统判断)</button>
      <button type="button" class="hlcc-btn hlcc-btn-primary" data-hlcc-phase="yes">YES</button>
    </div>
  </div>
</div>



<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="hlcc-phase-advance-form"
  style="display:none;">
  <input type="hidden" name="action" value="hlcc_front_set_phase_override">
  <input type="hidden" name="course_id" value="<?php echo !empty($course['id']) ? (int) $course['id'] : 0; ?>">
  <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
  <input type="hidden" name="phase_mode" value="advance" id="hlcc-phase-mode">
  <?php wp_nonce_field('hlcc_front_phase_override'); ?>
</form>


<!-- Team Message Button (v7.6.0) -->








<!-- System Message Edit Modal (v7.9.0) -->
<div id="hlcc-system-edit-modal" class="hlcc-modal" style="display:none;">
  <div class="hlcc-modal-overlay" onclick="hlccCloseSystemEditModal()"></div>
  <div class="hlcc-modal-content">
    <div class="hlcc-modal-header">
      <h3 id="hlcc-system-edit-title">编辑系统留言</h3>
      <button class="hlcc-modal-close" onclick="hlccCloseSystemEditModal()">×</button>
    </div>
    <div class="hlcc-modal-body">
      <textarea id="hlcc-system-edit-textarea" rows="10" style="width:100%;"></textarea>
      <input type="hidden" id="hlcc-system-edit-id" value="">
    </div>
    <div class="hlcc-modal-footer">
      <button class="hlcc-btn hlcc-btn-secondary" onclick="hlccCloseSystemEditModal()">取消</button>
      <button class="hlcc-btn hlcc-btn-primary" onclick="hlccSaveSystemMessage(this)">保存</button>
    </div>
  </div>
</div>
