<?php
namespace HLCC\App\Services;

use HLCC\Data\Repositories\CareContentRepository;
use HLCC\Domain\Phase;

if (!defined('ABSPATH')) exit;

final class CareService {
    private CareContentRepository $repo;

    public function __construct() {
        $this->repo = new CareContentRepository();
    }

        public function get_today_content(string $project_key, string $effective_phase, int $day_index, bool $is_overridden = false): array {
        // 如果没有手动阶段切换，并且在 Day0–5，则优先使用每日内容配置
        if (!$is_overridden && $day_index >= 0 && $day_index <= 5) {
            $row = $this->repo->get_day($project_key, $day_index);
            if ($row) return $row;
        }

        // 其他情况：按阶段模板（按当前生效阶段）
        $phase_row = $this->repo->get_phase($effective_phase);
        if ($phase_row) return $phase_row;

        // 未配置则不显示
        return [];
    }

}
