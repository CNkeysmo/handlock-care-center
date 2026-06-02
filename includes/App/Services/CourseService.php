<?php
namespace HLCC\App\Services;

use HLCC\Data\Repositories\CourseRepository;
use HLCC\Domain\PhaseRules;
use HLCC\Domain\CycleRules;

if (!defined('ABSPATH'))
    exit;

final class CourseService
{
    private CourseRepository $repo;

    private function normalize_course(array $course): array
    {
        $raw = $course['project_key'] ?? 'tattoo';
        $norm = CycleRules::normalize_project_key($raw);
        if ((string) $raw !== (string) $norm && !empty($course['id'])) {
            // Best-effort fix for buggy/legacy values.
            $this->repo->update((int) $course['id'], ['project_key' => $norm]);
        }
        $course['project_key'] = $norm;
        return $course;
    }

    public function __construct()
    {
        $this->repo = new CourseRepository();
    }

    public function get_active_course_for_user(int $user_id): ?array
    {
        $c = $this->repo->get_active($user_id);
        return $c ? $this->normalize_course($c) : null;
    }

    public function list_courses(int $user_id): array
    {
        $list = $this->repo->list_by_user($user_id);
        $out = [];
        foreach ($list as $c) {
            $out[] = $this->normalize_course($c);
        }
        return $out;
    }

    public function create_course(int $user_id, string $project_key, string $procedure_date, string $note, bool $make_active, ?int $custom_cycle_days = null): int
    {
        $project_key = CycleRules::normalize_project_key($project_key);
        // 新客户使用精确时间：如果 procedure_date 是今天，则使用当前精确时间
        // 如果是过去的日期，则使用该日期的中午 12:00:00
        $today = current_time('Y-m-d');
        if ($procedure_date === $today) {
            $procedure_datetime = current_time('mysql'); // 使用当前精确时间
        } else {
            $procedure_datetime = $procedure_date . ' 12:00:00'; // 历史日期使用中午
        }

        $id = $this->repo->insert([
            'user_id' => $user_id,
            'project_key' => $project_key,
            'procedure_date' => $procedure_date,
            'procedure_datetime' => $procedure_datetime, // 保存精确时间
            'note' => $note,
            'is_active' => $make_active ? 1 : 0,
            'custom_cycle_days' => $custom_cycle_days,
            // Explicitly init phase overrides to null
            'phase_override' => null,
            'phase_override_by' => null,
            'phase_override_at' => null,
        ]);
        if ($make_active) {
            $this->repo->set_active($user_id, $id);
        }

        return $id;
    }

    public function set_active(int $user_id, int $course_id): void
    {
        $this->repo->set_active($user_id, $course_id);
    }

    public function update_course_basic(int $course_id, string $procedure_date, string $note, ?int $custom_cycle_days = null): void
    {
        $course = $this->repo->get($course_id);
        if (!$course) {
            return;
        }

        // 更新时也设置 procedure_datetime，让旧客户升级到精确时间模式
        $today = current_time('Y-m-d');
        if ($procedure_date === $today) {
            $procedure_datetime = current_time('mysql');
        } else {
            $procedure_datetime = $procedure_date . ' 12:00:00';
        }

        $data = [
            'procedure_date' => $procedure_date,
            'procedure_datetime' => $procedure_datetime,
            'note' => $note,
        ];

        // 只有当传入非 null (包括 0 或正整数) 时才更新该字段
        // 但通常 custom_cycle_days 为 null 表示清除，0 表示无效?
        // 我们约定：如果参数传了(非null)，就更新。如果要清除自定义，传0或特定值？
        // 实际上数据库是 NULLable。
        // 为了简单，如果传入参数，则更新。如果要清除，传入0或null?
        // 在 Controller 层解析为空字符串 -> null？
        // 这里如果是 null，是否意味着“不更新”还是“更新为默认”？
        // 通常 update_course_basic 是全量更新基础信息。
        // 让我们约定：update_course_basic 总是接收所有基础字段。
        // 但为了兼容可能的旧调用(虽然我是全栈掌控)，默认为 null。
        // 如果是 null，我们假设不更新它？或者更新为 null ?
        // 考虑到 create_course 是可选参数，为了保持行为一致，
        // 我们在这里明确处理：如果 argument 被传递了（func_num_args?）...
        // 算了，直接把 custom_cycle_days 加入 data。
        // 但是要注意，如果是在只更新日期的场景调用... 目前只有 PostHandler

        // 简单策略：如果 custom_cycle_days 传递了（即使是null），就更新它。
        // 但 PHP 默认参数 null。无法区分“未传”和“传了null”。
        // 我们假设 PostHandlers 总是会从表单读取并传递。
        // 所以这里我们更新它。

        // 修正逻辑：仅当 custom_cycle_days !== null 时更新，或者我们强制要求调用者传递。
        // 为了代码清晰，我修改方法的调用处传递该参数。

        // Wait, PHP arguments... I'll check func_get_args or just always pass it from Handler.
        // Handler will read from POST, default to null or existing?
        // Admin form will have the field.

        $data['custom_cycle_days'] = $custom_cycle_days;

        $this->repo->update($course_id, $data);
    }

    public function update_custom_cycle(int $course_id, ?int $days): void
    {
        $course = $this->repo->get($course_id);
        if (!$course) {
            return;
        }

        $this->repo->update($course_id, ['custom_cycle_days' => $days]);
    }

    public function set_phase_override(int $course_id, ?string $phase_key, int $by_user_id): void
    {
        $course = $this->repo->get($course_id);
        if (!$course) {
            return;
        }

        $data = [
            'phase_override' => $phase_key,
            'phase_override_by' => $phase_key ? $by_user_id : null,
            'phase_override_at' => $phase_key ? current_time('mysql') : null,
        ];
        $this->repo->update($course_id, $data);
    }

    public function effective_phase(array $course, int $day_index): string
    {
        $base = PhaseRules::phase_by_day($day_index);
        $override = $course['phase_override'] ?? null;
        $override = PhaseRules::sanitize_override($base, $override ? (string) $override : null);

        if ($override && PhaseRules::is_override_resolved(
            $base,
            $override,
            $course['phase_override_at'] ?? null,
            $course['procedure_datetime'] ?? null,
            $course['procedure_date'] ?? null
        )) {
            return $base;
        }

        return $override ?: $base;
    }

    /**
     * 切换疗程的项目类型
     * 
     * 切换后会重置 procedure_date/datetime 为当前时间，
     * 照片对比保留（通过 course_id 关联）。
     * 
     * @param int $course_id 疗程 ID
     * @param string $new_project_key 新项目类型 (tattoo, brow, scar)
     * @return bool 是否成功
     */
    public function switch_project(int $course_id, string $new_project_key): bool
    {
        // 验证项目类型
        $new_project_key = CycleRules::normalize_project_key($new_project_key);
        if (!CycleRules::is_valid_project_key($new_project_key)) {
            return false;
        }

        // 获取疗程并验证存在
        $course = $this->repo->get($course_id);
        if (!$course) {
            return false;
        }

        // 如果项目类型相同，无需操作
        $old_project_key = CycleRules::normalize_project_key($course['project_key'] ?? 'tattoo');
        if ($old_project_key === $new_project_key) {
            return true;
        }

        // 切换项目并重置时间为当前时间
        $now_date = current_time('Y-m-d');
        $now_datetime = current_time('mysql');

        $this->repo->update($course_id, [
            'project_key' => $new_project_key,
            'procedure_date' => $now_date,
            'procedure_datetime' => $now_datetime,
            'phase_override' => null, // 清除阶段覆盖
            'phase_override_by' => null,
            'phase_override_at' => null,
        ]);

        return true;
    }

    public function delete_course(int $course_id, int $user_id): void
    {
        $course = $this->repo->get($course_id);
        if (!$course || (int) $course['user_id'] !== $user_id)
            return;

        // If deleting active course, we will deactivate it and set another as active if available.
        $was_active = (int) ($course['is_active'] ?? 0) === 1;

        $this->repo->delete($course_id);

        if ($was_active) {
            $list = $this->repo->list_by_user($user_id);
            if (!empty($list)) {
                $this->repo->set_active($user_id, (int) $list[0]['id']);
            }
        }

    }

    public function delete_all_courses_for_user(int $user_id): void
    {
        $list = $this->repo->list_by_user($user_id);
        foreach ($list as $c) {
            $this->repo->delete((int) $c['id']);
        }

    }
}
