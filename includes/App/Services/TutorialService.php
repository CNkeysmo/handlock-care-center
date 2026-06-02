<?php
namespace HLCC\App\Services;

use HLCC\Data\Repositories\TutorialRepository;

if (!defined('ABSPATH')) exit;

final class TutorialService {
    private TutorialRepository $repo;

    public function __construct() {
        $this->repo = new TutorialRepository();
    }

    public function get_tutorial_with_steps(string $project_key, string $phase_key): array {
        $t = $this->repo->get_tutorial($project_key, $phase_key);
        if (!$t) {
            return ['tutorial' => null, 'steps' => []];
        }
        $steps = $this->repo->list_steps((int)$t['id']);
        return ['tutorial' => $t, 'steps' => $steps];
    }
}
