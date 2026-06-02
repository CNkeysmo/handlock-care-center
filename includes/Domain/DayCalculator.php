<?php
namespace HLCC\Domain;

if (!defined('ABSPATH'))
    exit;

/**
 * 天数计算器（统一满24小时口径）
 * 
 * 支持两种起算来源：
 * 1. 精确时间模式：使用 procedure_datetime（新客户）
 * 2. 日期回退模式：使用 procedure_date + 12:00:00（旧客户）
 */
final class DayCalculator
{
    /**
     * 计算精确天数（统一满24小时口径）
     *
     * @param string|null $procedure_datetime 操作时间 (Y-m-d H:i:s)
     * @param string|null $procedure_date 操作日期 (Y-m-d)
     * @return float 精确天数
     */
    public static function exact_days(?string $procedure_datetime, ?string $procedure_date): float
    {
        $tz = wp_timezone();

        // 优先使用 datetime（精确时间）
        if ($procedure_datetime) {
            $start = new \DateTimeImmutable($procedure_datetime, $tz);
            $now = new \DateTimeImmutable(current_time('mysql'), $tz);
            $diff = $now->getTimestamp() - $start->getTimestamp();
            return $diff / 86400;
        }

        // 降级到 date（旧数据回退到中午锚点，仍按满24小时递增）
        if ($procedure_date) {
            $start = new \DateTimeImmutable($procedure_date . ' 12:00:00', $tz);
            $now = new \DateTimeImmutable(current_time('mysql'), $tz);
            $diff = $now->getTimestamp() - $start->getTimestamp();
            return $diff / 86400;
        }

        return 0;
    }

    /**
     * 计算天数索引（兼容新旧模式）
     * 
     * @param string|null $procedure_datetime 操作时间 (Y-m-d H:i:s)
     * @param string|null $procedure_date 操作日期 (Y-m-d)
     * @return int 天数索引 (0-based)
     */
    public static function day_index(?string $procedure_datetime, ?string $procedure_date): int
    {
        return (int) floor(self::exact_days($procedure_datetime, $procedure_date));
    }

    /**
     * 计算剩余天数
     * 
     * @param string $project_key 项目类型
     * @param int $day_index 当前天数索引
     * @param int|null $custom_cycle_days 自定义周期天数
     * @return int 剩余天数
     */
    public static function remaining_days(string $project_key, int $day_index, ?int $custom_cycle_days = null): int
    {
        $cycle = CycleRules::cycle_days($project_key, $custom_cycle_days);
        $remain = $cycle - $day_index;
        if ($remain < 0)
            $remain = 0;
        return $remain;
    }

    /**
     * 计算下次操作日期（兼容新旧模式）
     * 
     * @param string|null $procedure_datetime 操作时间 (Y-m-d H:i:s)
     * @param string|null $procedure_date 操作日期 (Y-m-d)
     * @param string $project_key 项目类型
     * @param int|null $custom_cycle_days 自定义周期天数
     * @return string 下次操作日期 (Y-m-d)
     */
    public static function next_date(?string $procedure_datetime, ?string $procedure_date, string $project_key, ?int $custom_cycle_days = null): string
    {
        $tz = wp_timezone();

        // 优先使用 datetime
        if ($procedure_datetime) {
            $start = new \DateTimeImmutable($procedure_datetime, $tz);
        } elseif ($procedure_date) {
            $start = new \DateTimeImmutable($procedure_date . ' 12:00:00', $tz);
        } else {
            return '';
        }

        $cycle = CycleRules::cycle_days($project_key, $custom_cycle_days);
        $next = $start->modify('+' . $cycle . ' days');
        return $next->format('Y-m-d');
    }
}
