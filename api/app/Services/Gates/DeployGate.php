<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * DeployGate — env_config, deployment, observability, agents.
 * Syarat chain: env → security → deployment → observability → agents.
 */
class DeployGate implements StageGate
{
    public function appliesTo(): array
    {
        return ['env_config', 'deployment', 'observability', 'agents'];
    }

    /** @var array<string, array<int, string>> */
    private const PREREQ = [
        'env_config' => ['api_contract', 'master_web', 'master_mobile'],
        'deployment' => ['env_config', 'security'],
        'observability' => ['deployment'],
        'agents' => ['observability', 'security', 'deployment'],
    ];

    public function passes(Version $v, string $stageKey): bool
    {
        $deps = self::PREREQ[$stageKey] ?? [];
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];
        foreach ($deps as $dep) {
            // Mobile stages are skipped for web projects — treat skipped as satisfied.
            $s = $statuses[$dep] ?? 'pending';
            if (! in_array($s, ['done', 'skipped'], true)) {
                return false;
            }
        }

        return true;
    }

    public function reason(Version $v, string $stageKey): string
    {
        $deps = self::PREREQ[$stageKey] ?? [];
        $missing = [];
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];
        foreach ($deps as $dep) {
            $s = $statuses[$dep] ?? 'pending';
            if (! in_array($s, ['done', 'skipped'], true)) {
                $missing[] = $dep;
            }
        }

        return $missing ? 'Menunggu: '.implode(', ', $missing) : 'DeployGate ok';
    }

    public function name(): string
    {
        return 'DeployGate';
    }
}
