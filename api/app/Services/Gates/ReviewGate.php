<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * ReviewGate — composite gate for code/security/performance review.
 * Syarat: agent posts evidence (version_stage_evidence) dengan security_passed=true + perf_passed=true.
 * Tabel evidence ditambah di CP-46.B; untuk CP-46.A kita cek absence-of-evidence gracefully.
 */
class ReviewGate implements StageGate
{
    public function appliesTo(): array
    {
        return ['verify.review'];
    }

    public function passes(Version $v, string $stageKey): bool
    {
        // CP-46.A: cek tabel evidence bila sudah ada (migration CP-46.B); fallback pass-by-default.
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

        return (bool) ($row->security_passed ?? false) && (bool) ($row->perf_passed ?? false);
    }

    public function reason(Version $v, string $stageKey): string
    {
        if (! \Schema::hasTable('version_stage_evidence')) {
            return 'ReviewGate belum aktif (evidence table missing)';
        }

        $row = \DB::table('version_stage_evidence')
            ->where('version_id', $v->id)
            ->where('stage_key', $stageKey)
            ->first();

        if (! $row) {
            return 'Belum ada evidence dari agent untuk verify.review';
        }

        $missing = [];
        if (! ($row->security_passed ?? false)) {
            $missing[] = 'security_passed';
        }
        if (! ($row->perf_passed ?? false)) {
            $missing[] = 'perf_passed';
        }

        return $missing ? 'Belum pass: '.implode(', ', $missing) : 'ReviewGate ok';
    }

    public function name(): string
    {
        return 'ReviewGate';
    }
}
