<?php
namespace HLCC\App\Services;

use HLCC\Domain\CycleRules;
use HLCC\Domain\DayCalculator;
use HLCC\Frontend\SelfcheckTips;

if (!defined('ABSPATH')) {
    exit;
}

final class MobilePlanService
{
    private const STAGE1_START = 0;
    private const STAGE1_END = 12;
    private const STAGE2_START = 13;
    private const STAGE2_END = 30;
    private const STAGE1_TIMES = ['12:30', '22:30'];
    private const STAGE2_TIMES = ['12:30', '21:30'];
    private const TEMPLATE_STAGE1_MIDDAY = 'stage1_midday';
    private const TEMPLATE_STAGE1_NIGHT = 'stage1_night';
    private const TEMPLATE_STAGE2_MIDDAY = 'stage2_midday';
    private const TEMPLATE_STAGE2_NIGHT = 'stage2_night';
    private const MIDDAY_STAGE1_TATTOO = '中午检查红肿、渗出和包扎状态，按护理要求保持清洁。';
    private const MIDDAY_STAGE1_BROW = '中午检查眉部结痂和渗出情况，保持干燥，避免抓碰。';
    private const MIDDAY_STAGE1_SCAR = '中午检查疤痕区域颜色、温度和刺激反应，避免摩擦。';
    private const MIDDAY_STAGE1_SCAB = '中午重点观察结痂是否完整，避免抠抓和过度清洁。';
    private const MIDDAY_STAGE2_VE = '中午继续观察增生、泛红和瘙痒变化，按计划护理。';

    private CourseService $courseService;

    public function __construct()
    {
        $this->courseService = new CourseService();
    }

    public function build_user_plan(int $userId, int $days = 2): array
    {
        $days = max(1, min(7, $days));
        $tz = wp_timezone();
        $now = new \DateTimeImmutable(current_time('mysql'), $tz);
        $horizon = $now->modify('+' . $days . ' days');

        $course = $this->courseService->get_active_course_for_user($userId);
        if (!$course) {
            return [
                'plan_version' => 1,
                'generated_at' => current_time('mysql'),
                'timezone' => $this->resolve_timezone_for_client(),
                'days' => $days,
                'course' => null,
                'out_of_range' => false,
                'notifications' => [],
            ];
        }

        $procedureStart = $this->resolve_procedure_start($course);
        if (!$procedureStart) {
            return [
                'plan_version' => 1,
                'generated_at' => current_time('mysql'),
                'timezone' => $this->resolve_timezone_for_client(),
                'days' => $days,
                'course' => [
                    'course_id' => (int) ($course['id'] ?? 0),
                    'project_key' => CycleRules::normalize_project_key((string) ($course['project_key'] ?? 'tattoo')),
                    'project_label' => CycleRules::project_label((string) ($course['project_key'] ?? 'tattoo')),
                    'procedure_datetime' => (string) ($course['procedure_datetime'] ?? ''),
                    'procedure_date' => (string) ($course['procedure_date'] ?? ''),
                    'day_index' => 0,
                    'effective_phase' => 'unknown',
                ],
                'out_of_range' => false,
                'notifications' => [],
            ];
        }

        $dayIndexNow = DayCalculator::day_index(
            $course['procedure_datetime'] ?? null,
            $course['procedure_date'] ?? null
        );
        $dayIndexNow = max(0, $dayIndexNow);
        $projectKey = CycleRules::normalize_project_key((string) ($course['project_key'] ?? 'tattoo'));
        $effectivePhase = $this->courseService->effective_phase($course, $dayIndexNow);
        $notifications = [];

        for ($day = self::STAGE1_START; $day <= self::STAGE2_END; $day++) {
            $baseDate = $procedureStart->modify('+' . $day . ' days');
            $scheduleTimes = [
                self::STAGE1_TIMES[0],
                $this->resolve_night_time_for_date($procedureStart, $baseDate), // 21:30 或 22:30
            ];

            foreach ($scheduleTimes as $time) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $scheduled = $baseDate->setTime($hour, $minute, 0);
                if ($scheduled <= $now || $scheduled > $horizon) {
                    continue;
                }

                $dayIndexForScheduled = $this->day_index_at($procedureStart, $scheduled);
                if ($dayIndexForScheduled < self::STAGE1_START || $dayIndexForScheduled > self::STAGE2_END) {
                    continue;
                }

                $stage = $dayIndexForScheduled <= self::STAGE1_END ? 'stage1' : 'stage2';
                $slotKey = $this->slot_key_for($stage, $time);
                $notifications[] = [
                    'id' => $this->build_notification_id($userId, $course, $slotKey, $scheduled),
                    'slot_key' => $slotKey,
                    'window' => str_contains($slotKey, 'midday') ? 'midday' : 'night',
                    'stage' => $stage,
                    'day_index' => $dayIndexForScheduled,
                    'fire_at' => $scheduled->format('Y-m-d H:i:s'),
                    'title' => $this->build_slot_title($dayIndexForScheduled, $slotKey, $stage),
                    'body' => $this->build_slot_body($slotKey, $dayIndexForScheduled, $projectKey),
                    'deep_link' => home_url('/care/'),
                ];
            }
        }

        usort($notifications, static function (array $a, array $b): int {
            return strcmp((string) $a['fire_at'], (string) $b['fire_at']);
        });

        return [
            'plan_version' => 1,
            'generated_at' => current_time('mysql'),
            'timezone' => $this->resolve_timezone_for_client(),
            'days' => $days,
            'course' => [
                'course_id' => (int) ($course['id'] ?? 0),
                'project_key' => $projectKey,
                'project_label' => CycleRules::project_label($projectKey),
                'procedure_datetime' => (string) ($course['procedure_datetime'] ?? ''),
                'procedure_date' => (string) ($course['procedure_date'] ?? ''),
                'day_index' => $dayIndexNow,
                'effective_phase' => $effectivePhase,
            ],
            'out_of_range' => $dayIndexNow > self::STAGE2_END,
            'notifications' => $notifications,
        ];
    }

    private function slot_key_for(string $stage, string $time): string
    {
        if ($stage === 'stage1') {
            return $time === self::STAGE1_TIMES[0]
                ? self::TEMPLATE_STAGE1_MIDDAY
                : self::TEMPLATE_STAGE1_NIGHT;
        }

        return $time === self::STAGE2_TIMES[0]
            ? self::TEMPLATE_STAGE2_MIDDAY
            : self::TEMPLATE_STAGE2_NIGHT;
    }

    private function build_slot_title(int $dayIndex, string $slotKey, string $stage): string
    {
        $dayHuman = max(0, $dayIndex);
        if ($slotKey === self::TEMPLATE_STAGE1_MIDDAY || $slotKey === self::TEMPLATE_STAGE2_MIDDAY) {
            return '第' . $dayHuman . '天中午护理检查';
        }

        if ($stage === 'stage1') {
            return '第' . $dayHuman . '天晚间自检提醒';
        }

        return '第' . $dayHuman . '天晚间护理复查';
    }

    private function build_slot_body(string $slotKey, int $dayIndex, string $projectKey): string
    {
        if ($slotKey === self::TEMPLATE_STAGE1_MIDDAY || $slotKey === self::TEMPLATE_STAGE2_MIDDAY) {
            return $this->resolve_midday_body($dayIndex, $projectKey);
        }

        if ($slotKey === self::TEMPLATE_STAGE1_NIGHT || $slotKey === self::TEMPLATE_STAGE2_NIGHT) {
            return $this->resolve_night_body($dayIndex);
        }

        return '请按护理要求执行今日流程。';
    }

    private function resolve_midday_body(int $dayIndex, string $projectKey): string
    {
        $safeDay = max(0, $dayIndex);
        $projectKey = CycleRules::normalize_project_key($projectKey);

        if ($safeDay >= self::STAGE1_START && $safeDay <= 5) {
            if ($projectKey === 'brow') {
                return self::MIDDAY_STAGE1_BROW;
            }
            if ($projectKey === 'scar') {
                return self::MIDDAY_STAGE1_SCAR;
            }
            return self::MIDDAY_STAGE1_TATTOO;
        }

        if ($safeDay >= 6 && $safeDay <= self::STAGE1_END) {
            return self::MIDDAY_STAGE1_SCAB;
        }

        if ($safeDay >= self::STAGE2_START && $safeDay <= self::STAGE2_END) {
            return self::MIDDAY_STAGE2_VE;
        }

        return '请按护理要求执行今日中午检查。';
    }

    private function resolve_night_body(int $dayIndex): string
    {
        $safeDay = max(0, $dayIndex);
        $selfcheck = $this->build_selfcheck_digest($safeDay);
        if ($selfcheck !== null) {
            return $selfcheck;
        }

        return '请留意清洁、疼痛变化与碰水时长。';
    }

    private function build_selfcheck_digest(int $dayIndex): ?string
    {
        $tips = SelfcheckTips::get_for_day($dayIndex);
        if (empty($tips) || !isset($tips[0]) || !is_array($tips[0])) {
            return null;
        }

        $title = trim(wp_strip_all_tags((string) ($tips[0]['title'] ?? '')));
        $body = trim(wp_strip_all_tags((string) ($tips[0]['body'] ?? '')));
        $body = preg_replace('/\s+/u', ' ', $body) ?: $body;
        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($body, 'UTF-8') > 60) {
            $body = rtrim(mb_substr($body, 0, 60, 'UTF-8')) . '...';
        }

        if ($title !== '' && $body !== '') {
            return $title . '：' . $body;
        }

        if ($body !== '') {
            return $body;
        }

        if ($title !== '') {
            return $title;
        }

        return null;
    }

    private function build_notification_id(int $userId, array $course, string $slotKey, \DateTimeImmutable $scheduled): string
    {
        $courseId = (int) ($course['id'] ?? 0);
        $seed = $userId . '|' . $courseId . '|' . $slotKey . '|' . $scheduled->format('Y-m-d H:i:s');
        return 'hlcc_mobile_' . substr(hash('sha256', $seed), 0, 24);
    }

    private function day_index_at(\DateTimeImmutable $procedureStart, \DateTimeImmutable $scheduled): int
    {
        $diff = $scheduled->getTimestamp() - $procedureStart->getTimestamp();
        return (int) floor($diff / 86400);
    }

    private function resolve_night_time_for_date(\DateTimeImmutable $procedureStart, \DateTimeImmutable $baseDate): string
    {
        [$h21, $m21] = array_map('intval', explode(':', self::STAGE2_TIMES[1]));
        $time2130 = $baseDate->setTime($h21, $m21, 0);
        $dayAt2130 = $this->day_index_at($procedureStart, $time2130);
        if ($dayAt2130 >= self::STAGE2_START) {
            return self::STAGE2_TIMES[1];
        }
        return self::STAGE1_TIMES[1];
    }

    private function resolve_procedure_start(array $course): ?\DateTimeImmutable
    {
        $tz = wp_timezone();

        try {
            if (!empty($course['procedure_datetime'])) {
                return new \DateTimeImmutable((string) $course['procedure_datetime'], $tz);
            }
            if (!empty($course['procedure_date'])) {
                return new \DateTimeImmutable((string) $course['procedure_date'] . ' 12:00:00', $tz);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function resolve_timezone_for_client(): string
    {
        if (function_exists('wp_timezone_string')) {
            $tz = (string) wp_timezone_string();
            if ($tz !== '') {
                return $tz;
            }
        }

        $tz = (string) get_option('timezone_string');
        if ($tz !== '') {
            return $tz;
        }

        $offset = (float) get_option('gmt_offset', 0);
        $sign = $offset >= 0 ? '+' : '-';
        $abs = abs($offset);
        $hours = (int) floor($abs);
        $minutes = (int) round(($abs - $hours) * 60);
        return sprintf('GMT%s%02d:%02d', $sign, $hours, $minutes);
    }
}
