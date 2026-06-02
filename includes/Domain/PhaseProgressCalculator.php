<?php
namespace HLCC\Domain;

if (!defined('ABSPATH'))
    exit;

/**
 * 分阶段进度计算器
 * 
 * 计算炎症期、结痂掉痂期、康复期三个阶段的独立进度
 * 支持实时进度显示和项目差异化提示
 */
final class PhaseProgressCalculator
{

    /**
     * 计算三阶段进度（兼容新旧模式）
     *
     * @param string|null $procedure_datetime 操作时间 (Y-m-d H:i:s) - 新客户使用
     * @param string|null $procedure_date 操作日期 (Y-m-d) - 旧客户使用
     * @param string $project_key 项目类型 (tattoo/brow/scar)
     * @param string|null $phase_override 阶段覆盖 (如果管理员手动设置了阶段)
     * @param int|null $custom_cycle_days 自定义周期天数
     * @param string|null $phase_override_at 覆盖设置时间 (Y-m-d H:i:s)
     * @return array 三阶段进度数据
     */
    public static function calculate(
        ?string $procedure_datetime,
        ?string $procedure_date,
        string $project_key,
        ?string $phase_override = null,
        ?int $custom_cycle_days = null,
        ?string $phase_override_at = null
    ): array {
        // 计算精确天数（自动兼容新旧模式）
        $exact_days = DayCalculator::exact_days($procedure_datetime, $procedure_date);

        // [Fix v8.3.3] UX Smoothing: Force 0% for the first ~1.2 hours to avoid immediate non-zero progress for new courses
        if ($exact_days < 0.05) {
            $exact_days = 0;
        }

        $day_index = (int) floor($exact_days);
        $cycle_days = CycleRules::cycle_days($project_key, $custom_cycle_days);

        // 计算基于实际天数的系统阶段
        $system_phase = PhaseRules::phase_by_day($day_index);

        // 检查 advance override 是否已被自然天数追上（自动解除）
        $override_resolved = PhaseRules::is_override_resolved(
            $system_phase, $phase_override, $phase_override_at, $procedure_datetime, $procedure_date
        );

        if ($override_resolved) {
            // Override 已解除：护理提示用系统阶段，不传 override 给子方法（阻止徽章和强制100%/0%）
            $effective_phase = $system_phase;
            $active_override = null;
        } else {
            $effective_phase = $phase_override ?: $system_phase;
            $active_override = $phase_override;
        }

        // 炎症期: 0-6 天（精确）
        $inflammation = self::calculate_inflammation($exact_days, $day_index, $project_key, $effective_phase, $active_override);

        // 结痂掉痂期: 6-13 天（精确）
        $scab = self::calculate_scab($exact_days, $day_index, $effective_phase, $active_override);

        // 康复期: 13+ 天（精确）
        $recovery = self::calculate_recovery($exact_days, $day_index, $cycle_days, $effective_phase, $active_override);

        return [
            'inflammation' => $inflammation,
            'scab' => $scab,
            'recovery' => $recovery,
        ];
    }

    /**
     * 计算炎症期进度（使用精确天数）
     */
    private static function calculate_inflammation(
        float $exact_days,
        int $day_index,
        string $project_key,
        string $effective_phase,
        ?string $phase_override
    ): array {
        $total_days = 6; // Day 0-5
        $system_phase = PhaseRules::phase_by_day($day_index);

        // 检查是否被 override 跳过（提前进入下一阶段）
        if ($phase_override && $phase_override !== Phase::INFLAMMATION) {
            $ordered = PhaseRules::ordered();
            $system_idx = array_search($system_phase, $ordered, true);
            $override_idx = array_search($phase_override, $ordered, true);

            // 如果已提前进入下一阶段，炎症期显示 100% 已完成
            if ($override_idx !== false && $system_idx !== false && $override_idx > $system_idx) {
                $progress = 100;
                $status = 'completed';
                $status_text = '已度过';
                $tip = self::get_inflammation_tip_completed($project_key);
                $override_badge = self::get_override_badge(Phase::INFLAMMATION, $system_phase, $phase_override);

                return [
                    'label' => '炎症期',
                    'range' => 'Day 0-5',
                    'days' => $total_days,
                    'progress' => round($progress, 1),
                    'status' => $status,
                    'status_text' => $status_text,
                    'tip' => $tip,
                    'override_badge' => $override_badge,
                    'is_overridden' => true, // 新增标记
                ];
            }
        }

        // 使用精确天数计算进度：Day 0 从 0% 开始，Day 6 结束时 100%
        if ($exact_days < 6) {
            // 进行中: Day 0 开始 0%, Day 5 结束 100%
            $progress = min(100, ($exact_days / $total_days) * 100);
            $status = 'active';
            $status_text = '进行中';
        } else {
            // 已完成
            $progress = 100;
            $status = 'completed';
            $status_text = '已度过';
        }

        // 护理提示使用effective_phase(可能是override的)
        if ($effective_phase === Phase::INFLAMMATION) {
            $tip = ($status === 'active')
                ? self::get_inflammation_tip_active($project_key)
                : self::get_inflammation_tip_completed($project_key);
        } else {
            // 如果被override到其他阶段,使用对应阶段的提示
            $tip = self::get_tip_for_phase($effective_phase, $project_key, $day_index);
        }

        // 添加override标识
        $override_badge = self::get_override_badge(Phase::INFLAMMATION, $system_phase, $phase_override);

        return [
            'label' => '炎症期',
            'range' => 'Day 0-5',
            'days' => $total_days,
            'progress' => round($progress, 1),
            'status' => $status,
            'status_text' => $status_text,
            'tip' => $tip,
            'override_badge' => $override_badge,
        ];
    }

    /**
     * 计算结痂掉痂期进度（使用精确天数）
     */
    private static function calculate_scab(
        float $exact_days,
        int $day_index,
        string $effective_phase,
        ?string $phase_override
    ): array {
        $total_days = 7; // Day 6-12
        $system_phase = PhaseRules::phase_by_day($day_index);

        // 检查是否被 override 跳过
        if ($phase_override && $phase_override !== Phase::SCAB) {
            $ordered = PhaseRules::ordered();
            $system_idx = array_search($system_phase, $ordered, true);
            $override_idx = array_search($phase_override, $ordered, true);

            // 如果已提前进入下一阶段，结痂期显示 100% 已完成
            if ($override_idx !== false && $system_idx !== false && $override_idx > $system_idx && $exact_days >= 6) {
                $progress = 100;
                $status = 'completed';
                $status_text = '已度过';
                $tip = '✅ 已度过结痂期！可以碰水,无需换药(避免热水) 🚿';
                $override_badge = self::get_override_badge(Phase::SCAB, $system_phase, $phase_override);

                return [
                    'label' => '结痂掉痂期',
                    'range' => 'Day 6-12',
                    'days' => $total_days,
                    'progress' => round($progress, 1),
                    'status' => $status,
                    'status_text' => $status_text,
                    'tip' => $tip,
                    'override_badge' => $override_badge,
                    'is_overridden' => true, // 新增标记
                ];
            }
        }

        // 使用精确天数计算进度：Day 6 从 0% 开始，Day 13 结束时 100%
        if ($exact_days < 6) {
            // 未开始
            $progress = 0;
            $status = 'pending';
            $status_text = '未开始';
        } elseif ($exact_days < 13) {
            // 进行中: Day 6 开始 0%, Day 12 结束 100%
            $days_in_phase = $exact_days - 6; // Day 6 = 0, Day 12.99 = 6.99
            $progress = min(100, ($days_in_phase / $total_days) * 100);
            $status = 'active';
            $status_text = '进行中';
        } else {
            // 已完成
            $progress = 100;
            $status = 'completed';
            $status_text = '已度过';
        }

        // 护理提示使用effective_phase
        if ($effective_phase === Phase::SCAB) {
            if ($status === 'pending') {
                $tip = '表皮修复阶段,需继续换药 🔄';
            } elseif ($status === 'active') {
                $tip = '表皮修复中,继续换药,避免碰热水 🔄💧';
            } else {
                $tip = '✅ 已度过结痂期！可以碰水,无需换药(避免热水) 🚿';
            }
        } else {
            $tip = self::get_tip_for_phase($effective_phase, '', $day_index);
        }

        // 添加override标识
        $override_badge = self::get_override_badge(Phase::SCAB, $system_phase, $phase_override);

        return [
            'label' => '结痂掉痂期',
            'range' => 'Day 6-12',
            'days' => $total_days,
            'progress' => round($progress, 1),
            'status' => $status,
            'status_text' => $status_text,
            'tip' => $tip,
            'override_badge' => $override_badge,
        ];
    }

    /**
     * 计算康复期进度（使用精确天数）
     */
    private static function calculate_recovery(
        float $exact_days,
        int $day_index,
        int $cycle_days,
        string $effective_phase,
        ?string $phase_override
    ): array {
        $start_day = 13;
        $total_days = max(1, $cycle_days - 12); // 总周期 - 前两阶段
        $system_phase = PhaseRules::phase_by_day($day_index);

        // 检查是否被 override 跳过（不太可能，但保持一致性）
        if ($phase_override && $phase_override !== Phase::RECOVERY && $exact_days >= 13) {
            // 康复期是最后阶段，不会被跳过，但保持逻辑一致
        }

        // 使用精确天数计算进度：Day 13 从 0% 开始
        if ($exact_days < $start_day) {
            // 未开始
            $progress = 0;
            $status = 'pending';
            $status_text = '未开始';
        } elseif ($exact_days < $cycle_days) {
            // 进行中: Day 13 开始 0%
            $days_in_phase = $exact_days - 13; // Day 13 = 0
            $progress = min(100, ($days_in_phase / $total_days) * 100);
            $status = 'active';
            $status_text = '进行中';
        } else {
            // 已完成
            $progress = 100;
            $status = 'completed';
            $status_text = '已度过';
        }

        // 护理提示使用effective_phase
        if ($effective_phase === Phase::RECOVERY) {
            if ($status === 'pending') {
                $tip = '皮肤恢复阶段,保持正常护理 🌱';
            } elseif ($status === 'active') {
                $tip = '皮肤逐步恢复中,保持正常护理 🌱';
            } else {
                $tip = '🎉 恭喜完成康复期！可联系诊所安排下次操作';
            }
        } else {
            $tip = self::get_tip_for_phase($effective_phase, '', $day_index);
        }

        // 添加override标识
        $override_badge = self::get_override_badge(Phase::RECOVERY, $system_phase, $phase_override);

        return [
            'label' => '康复期',
            'range' => "Day {$start_day}-{$cycle_days}",
            'days' => $total_days,
            'progress' => round($progress, 1),
            'status' => $status,
            'status_text' => $status_text,
            'tip' => $tip,
            'override_badge' => $override_badge,
        ];
    }

    /**
     * 获取炎症期进行中提示(项目差异化)
     */
    private static function get_inflammation_tip_active(string $project_key): string
    {
        if ($project_key === 'brow') {
            // 洗眉毛不需要包扎
            return '伤口稳定中,避免碰水(尤其热水) 🩹💧';
        }
        // 洗纹身和疤痕修复需要包扎
        return '伤口稳定中,继续包扎,避免碰水 🩹💧';
    }

    /**
     * 获取炎症期完成提示(项目差异化)
     */
    private static function get_inflammation_tip_completed(string $project_key): string
    {
        if ($project_key === 'brow') {
            // 洗眉毛不需要包扎
            return '✅ 已度过炎症期！仍需避免碰水(尤其热水) 💧';
        }
        // 洗纹身和疤痕修复需要包扎
        return '✅ 已度过炎症期！可停止包扎,仍需避免碰水 💧';
    }

    /**
     * 获取override标识徽章
     * 
     * @param string $this_phase 当前阶段
     * @param string $system_phase 系统计算的阶段
     * @param string|null $phase_override 覆盖阶段
     * @return string|null 徽章文字,null表示无override
     */
    private static function get_override_badge(string $this_phase, string $system_phase, ?string $phase_override): ?string
    {
        if (!$phase_override || $this_phase !== $phase_override) {
            return null;
        }

        // 当前阶段被设置为override阶段
        $phase_labels = [
            Phase::INFLAMMATION => '炎症期',
            Phase::SCAB => '结痂期',
            Phase::RECOVERY => '康复期',
        ];

        $label = $phase_labels[$phase_override] ?? '';

        // 判断是提前还是延后
        $ordered = PhaseRules::ordered();
        $system_idx = array_search($system_phase, $ordered, true);
        $override_idx = array_search($phase_override, $ordered, true);

        if ($override_idx > $system_idx) {
            return "⚡️ 已提前进入{$label}";
        } elseif ($override_idx < $system_idx) {
            return "⏸ 延后至{$label}";
        }

        return null;
    }

    /**
     * 获取跨阶段的护理提示
     * 
     * @param string $phase 阶段
     * @param string $project_key 项目类型
     * @param int $day_index 天数索引
     * @return string 提示文字
     */
    private static function get_tip_for_phase(string $phase, string $project_key, int $day_index): string
    {
        switch ($phase) {
            case Phase::INFLAMMATION:
                return self::get_inflammation_tip_active($project_key);
            case Phase::SCAB:
                return '表皮修复中,继续换药,避免碰热水 🔄💧';
            case Phase::RECOVERY:
                return '皮肤逐步恢复中,保持正常护理 🌱';
            default:
                return '请遵循护理指导';
        }
    }

    /**
     * 获取用于前端实时更新的基准数据（兼容新旧模式）
     * 
     * @param string|null $procedure_datetime 操作时间 (Y-m-d H:i:s)
     * @param string|null $procedure_date 操作日期 (Y-m-d)
     * @param string $project_key 项目类型
     * @param int|null $custom_cycle_days 自定义周期天数
     * @param string|null $phase_override 覆盖阶段
     * @param string|null $phase_override_at 覆盖时间
     * @return array 基准数据
     */
    public static function get_realtime_base_data(
        ?string $procedure_datetime,
        ?string $procedure_date,
        string $project_key,
        ?int $custom_cycle_days = null,
        ?string $phase_override = null,
        ?string $phase_override_at = null
    ): array {
        $cycle_days = CycleRules::cycle_days($project_key, $custom_cycle_days);

        // 计算 override 是否已被自然天数追上
        $exact_days = DayCalculator::exact_days($procedure_datetime, $procedure_date);
        $system_phase = PhaseRules::phase_by_day((int) floor($exact_days));
        $override_resolved = PhaseRules::is_override_resolved(
            $system_phase, $phase_override, $phase_override_at, $procedure_datetime, $procedure_date
        );

        return [
            'procedure_datetime' => $procedure_datetime,
            'procedure_date' => $procedure_date,
            'project_key' => $project_key,
            'cycle_days' => $cycle_days,
            'phase_override' => $phase_override,
            'phase_override_at' => $phase_override_at,
            'override_resolved' => $override_resolved,
            'phase_config' => [
                'inflammation' => ['start' => 0, 'end' => 5, 'days' => 6],
                'scab' => ['start' => 6, 'end' => 12, 'days' => 7],
                'recovery' => ['start' => 13, 'end' => $cycle_days - 1],
            ],
        ];
    }
}
