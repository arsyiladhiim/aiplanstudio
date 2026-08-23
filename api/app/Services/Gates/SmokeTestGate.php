<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * SmokeTestGate — agent posts evidence with tests_passed=true + build_passed=true.
 */
class SmokeTestGate implements StageGate
{
    public function appliesTo(): array
    {
        return ['smoke_test'];
    }

    public function passes(Version $v, string $stageKey): bool
    {
        if (! \Schema::hasTable('version_stage_evidence')) {
            return true;
        }

        $row = \DB::table('version_stage_evidence')
            ->where('version_id', $v->id)
            ->where('stage_key', $stageKey)
            ->first();

        if (! $row) {
            return false;
        }

        return (bool) ($row->tests_passed ?? false) && (bool) ($row->build_passed ?? false);
    }

    public function reason(Version $v, string $stageKey): string
    {
        if (! \Schema::hasTable('version_stage_evidence')) {
            return 'SmokeTestGate belum aktif';
        }

        $row = \DB::table('version_stage_evidence')
            ->where('version_id', $v->id)
            ->where('stage_key', $stageKey)
            ->first();

        if (! $row) {
            return 'Belum ada smoke test evidence dari agent';
        }

        $missing = [];
        if (! ($row->tests_passed ?? false)) {
            $missing[] = 'tests_passed';
        }
        if (! ($row->build_passed ?? false)) {
            $missing[] = 'build_passed';
        }

        return $missing ? 'Belum pass: '.implode(', ', $missing) : 'SmokeTestGate ok';
    }

    public function name(): string
    {
        return 'SmokeTestGate';
    }
}
