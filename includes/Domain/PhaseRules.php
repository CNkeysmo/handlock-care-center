<?php
namespace HLCC\Domain;

if (!defined('ABSPATH')) exit;

final class PhaseRules {
    /** @return string[] ordered low->high */
    public static function ordered(): array {
        return [Phase::INFLAMMATION, Phase::SCAB, Phase::RECOVERY];
    }

    public static function phase_by_day(int $day_index): string {
        if ($day_index <= 5) return Phase::INFLAMMATION;
        if ($day_index <= 12) return Phase::SCAB;
        return Phase::RECOVERY;
    }

    /**
     * Allowed override phases relative to base phase.
     * Always includes base phase itself, plus at most 1-step neighbor(s).
     *
     * @return array{base:string, delay:?string, advance:?string, allowed:string[]}
     */
    public static function override_options(string $base_phase): array {
        $order = self::ordered();
        $idx = array_search($base_phase, $order, true);
        if ($idx === false) $idx = 0;

        $delay = ($idx > 0) ? $order[$idx - 1] : null;
        $advance = ($idx < count($order) - 1) ? $order[$idx + 1] : null;

        $allowed = [$base_phase];
        if ($delay) $allowed[] = $delay;
        if ($advance) $allowed[] = $advance;

        return [
            'base' => $base_phase,
            'delay' => $delay,
            'advance' => $advance,
            'allowed' => $allowed,
        ];
    }

    /** Ensure override is adjacent to base, otherwise return null. */
    public static function sanitize_override(string $base_phase, ?string $override): ?string {
        if (!$override) return null;
        $opt = self::override_options($base_phase);
        return in_array($override, $opt['allowed'], true) ? $override : null;
    }

    public static function range_label(string $phase): string {
        switch ($phase) {
            case Phase::INFLAMMATION: return '第 0-5 天';
            case Phase::SCAB: return '第 6-12 天';
            case Phase::RECOVERY: return '第 13 天起';
            default: return '';
        }
    }

    /**
     * Allowed phases relative to a base phase.
     * Always includes base. May include previous/next if exists.
     *
     * @return string[]
     */
    public static function allowed_relative_to(string $base_phase): array {
        $ordered = self::ordered();
        $idx = array_search($base_phase, $ordered, true);
        if ($idx === false) return [$base_phase];

        $out = [$base_phase];
        if ($idx > 0) $out[] = $ordered[$idx - 1];
        if ($idx < count($ordered) - 1) $out[] = $ordered[$idx + 1];
        return array_values(array_unique($out));
    }

    public static function is_allowed_override(string $base_phase, string $override_phase): bool {
        return in_array($override_phase, self::allowed_relative_to($base_phase), true);
    }

    /** Normalize override: if invalid for base, ignore. */
    public static function normalize_override(string $base_phase, ?string $override_phase): ?string {
        if (!$override_phase) return null;
        return self::is_allowed_override($base_phase, $override_phase) ? $override_phase : null;
    }

    public static function previous_of(string $phase): ?string {
        $ordered = self::ordered();
        $idx = array_search($phase, $ordered, true);
        if ($idx === false || $idx === 0) return null;
        return $ordered[$idx - 1];
    }

    public static function next_of(string $phase): ?string {
        $ordered = self::ordered();
        $idx = array_search($phase, $ordered, true);
        if ($idx === false || $idx >= count($ordered) - 1) return null;
        return $ordered[$idx + 1];
    }

    /**
     * 判断 override 是否为 advance 类型（提前进入下一阶段）。
     * 通过 override_at 时间推断设置时的系统阶段，与 override 阶段对比。
     */
    public static function was_advance_override(
        ?string $override,
        ?string $phase_override_at,
        ?string $procedure_datetime,
        ?string $procedure_date
    ): bool {
        if (!$override || !$phase_override_at) return false;

        $ordered = self::ordered();
        $override_idx = array_search($override, $ordered, true);
        if ($override_idx === false) return false;

        $tz = wp_timezone();
        $proc_time = $procedure_datetime
            ? new \DateTimeImmutable($procedure_datetime, $tz)
            : new \DateTimeImmutable(($procedure_date ?: '2000-01-01') . ' 12:00:00', $tz);
        $override_time = new \DateTimeImmutable($phase_override_at, $tz);
        $diff = max(0, $override_time->getTimestamp() - $proc_time->getTimestamp());
        $day_at_override = (int) floor($diff / 86400);

        $system_at_override = self::phase_by_day($day_at_override);
        $system_idx = array_search($system_at_override, $ordered, true);

        return $override_idx > $system_idx;
    }

    /**
     * 判断 advance override 是否应自动解除。
     * 条件：override 是 advance 类型，且当前系统阶段已追上 override 阶段。
     */
    public static function is_override_resolved(
        string $current_system_phase,
        ?string $override,
        ?string $phase_override_at,
        ?string $procedure_datetime,
        ?string $procedure_date
    ): bool {
        if (!$override) return false;

        if (!self::was_advance_override($override, $phase_override_at, $procedure_datetime, $procedure_date)) {
            return false;
        }

        $ordered = self::ordered();
        $sys_idx = array_search($current_system_phase, $ordered, true);
        $ovr_idx = array_search($override, $ordered, true);

        return $sys_idx !== false && $ovr_idx !== false && $sys_idx >= $ovr_idx;
    }
}
