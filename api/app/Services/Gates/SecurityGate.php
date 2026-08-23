<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * SecurityGate — security checklist.
 * Syarat: env_config + api_contract + prd done.
 */
class SecurityGate implements StageGate
{
    public function appliesTo(): array
    {
        return ['security'];
    }

    public function passes(Version $v, string $stageKey): bool
    {
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];

        return ($statuses['env_config'] ?? 'pending') === 'done'
            && ($statuses['api_contract'] ?? 'pending') === 'done'
            && ($statuses['prd'] ?? 'pending') === 'done';
    }

    public function reason(Version $v, string $stageKey): string
    {
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];
        $missing = [];
        foreach (['env_config', 'api_contract', 'prd'] as $dep) {
            if (($statuses[$dep] ?? 'pending') !== 'done') {
                $missing[] = $dep;
            }
        }

        return $missing ? 'Menunggu: '.implode(', ', $missing) : 'SecurityGate ok';
    }

    public function name(): string
    {
        return 'SecurityGate';
    }
}
