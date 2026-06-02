<?php
/**
 * 疗程切换（前台外挂版）
 *
 * 基于当前登录用户的所有疗程，按操作日期升序绘制时间线。
 *  - 蓝色大点：当前疗程
 *  - 灰色点：历史疗程
 *
 * 本视图只负责 UI，切换逻辑复用 hlcc_front_set_active_course。
 */

if (!defined('ABSPATH'))
  exit;

use HLCC\Domain\CycleRules;
use HLCC\Domain\DayCalculator;

/** @var array<int,array<string,mixed>> $courses_for_timeline */
?>

<div class="hlcc-timeline-card">
  <div class="hlcc-timeline-head">
    <div class="hlcc-timeline-title">疗程切换</div>
    <div class="hlcc-timeline-sub">点选节点可切换当前疗程</div>
  </div>

  <?php if (empty($courses_for_timeline)): ?>
    <div class="hlcc-timeline-empty">当前暂无可显示的疗程。</div>
  <?php else: ?>
    <?php
    // 按操作日期由旧到新排序，保证时间线顺序清晰
    usort($courses_for_timeline, function ($a, $b) {
      $da = isset($a['procedure_date']) ? (string) $a['procedure_date'] : '';
      $db = isset($b['procedure_date']) ? (string) $b['procedure_date'] : '';
      if ($da === $db) {
        $ida = isset($a['id']) ? (int) $a['id'] : 0;
        $idb = isset($b['id']) ? (int) $b['id'] : 0;
        return $ida <=> $idb;
      }
      return strcmp($da, $db);
    });
    ?>
    <div class="hlcc-timeline-strip">
      <div class="hlcc-timeline-scroller">
        <?php foreach ($courses_for_timeline as $index => $c): ?>
          <?php
          $ck = isset($c['project_key']) ? (string) $c['project_key'] : 'tattoo';
          $ck = CycleRules::normalize_project_key($ck);
          $clabel = CycleRules::project_label($ck);
          $cid = isset($c['id']) ? (int) $c['id'] : 0;
          $cdate = isset($c['procedure_date']) ? (string) $c['procedure_date'] : '';
          $cnote = isset($c['note']) ? (string) $c['note'] : '';
          $is_active = isset($c['is_active']) && (int) $c['is_active'] === 1;

          // Icon selection based on project type
          $icon_name = 'calendar';
          if ($ck === 'tattoo')
            $icon_name = 'brush';
          if ($ck === 'scab')
            $icon_name = 'layers';
          ?>
          <div class="hlcc-timeline-card-item <?php echo $is_active ? 'is-active' : ''; ?>">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="hlcc_front_set_active_course">
              <input type="hidden" name="course_id" value="<?php echo (int) $cid; ?>">
              <input type="hidden" name="user_id" value="<?php echo isset($user_id) ? (int) $user_id : 0; ?>">
              <?php wp_nonce_field('hlcc_front_set_active_course'); ?>

              <button type="submit" class="hlcc-course-card-btn" <?php if ($is_active): ?> disabled<?php endif; ?>>
                <div class="hlcc-card-title"><?php echo esc_html($clabel); ?></div>
                <div class="hlcc-card-meta">
                  <?php
                  // Mini Progress Bar Logic
                  $total_days = CycleRules::cycle_days($ck);
                  $days_elapsed = 0;
                  $pct = 0;
                  $cdatetime = isset($c['procedure_datetime']) ? (string) $c['procedure_datetime'] : null;
                  if (!empty($cdate) || !empty($cdatetime)) {
                    $days_elapsed = DayCalculator::day_index($cdatetime, $cdate ?: null);
                    if ($total_days > 0) {
                      $pct = ($days_elapsed / $total_days) * 100;
                    }
                  }
                  $pct = max(0, min(100, $pct));
                  $tips_text = '已恢复 ' . max(0, $days_elapsed) . '/' . $total_days . ' 天 (' . round($pct) . '%)';
                  ?>
                  <div class="hlcc-mini-progress-wrap" title="<?php echo esc_attr($tips_text); ?>">
                    <div class="hlcc-mini-progress-bar" style="width: <?php echo esc_attr($pct); ?>%;"></div>
                  </div>
                </div>
                <?php if ($cnote !== ''): ?>
                  <div class="hlcc-card-note" title="<?php echo esc_attr($cnote); ?>"><?php echo esc_html($cnote); ?></div>
                <?php endif; ?>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
