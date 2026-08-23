<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * ArchGate — architecture, ERD, API contract.
 * Syarat: artifact pendahulu (PRD untuk architecture; architecture+prd untuk erd; erd untuk api_contract).
 */
class ArchGate implements StageGate
{
    public function appliesTo(): array
    {
        return ['architecture', 'erd', 'api_contract'];
    }

    /** @var array<string, array<int, string>> */
    private const PREREQ = [
        'architecture' => ['prd'],
        'erd' => ['architecture', 'prd'],
        'api_contract' => ['erd', 'architecture'],
    ];

    public function passes(Version $v, string $stageKey): bool
    {
        $deps = self::PREREQ[$stageKey] ?? [];
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];
        foreach ($deps as $dep) {
            if (($statuses[$dep] ?? 'pending') !== 'done') {
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
            if (($statuses[$dep] ?? 'pending') !== 'done') {
                $missing[] = $dep;
            }
        }

        return $missing ? 'Menunggu: '.implode(', ', $missing) : 'ArchGate ok';
    }

    public function name(): string
    {
        return 'ArchGate';
    }
}
