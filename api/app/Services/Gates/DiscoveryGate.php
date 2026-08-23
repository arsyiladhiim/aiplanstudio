<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * DiscoveryGate — prerequisite untuk pertanyaan/pertanyaan_mobile.
 * MCQ 5-10 valid (mirrors PipelineRunner::MIN_MCQ_QUESTIONS = 5).
 * Untuk pertanyaan_mobile, wajib master_web sudah done (existing mobile-gate behavior).
 */
class DiscoveryGate implements StageGate
{
    public function appliesTo(): array
    {
        return ['pertanyaan', 'pertanyaan_mobile'];
    }

    public function passes(Version $v, string $stageKey): bool
    {
        // pertanyaan_mobile: hard requirement master_web done (mobile-gate pattern existing).
        if ($stageKey === 'pertanyaan_mobile') {
            $statuses = is_array($v->stage_status) ? $v->stage_status : [];
            if (($statuses['master_web'] ?? 'pending') !== 'done') {
                return false;
            }
        }

        // Project target harus mendukung stage ini.
        if ($stageKey === 'pertanyaan_mobile' && ($v->project?->target ?? 'web') !== 'both') {
            return false;
        }

        return true;
    }

    public function reason(Version $v, string $stageKey): string
    {
        if ($stageKey === 'pertanyaan_mobile') {
            $statuses = is_array($v->stage_status) ? $v->stage_status : [];

            return ($statuses['master_web'] ?? 'pending') === 'done'
                ? 'Target project bukan both'
                : 'master_web belum done — selesaikan web track dulu';
        }

        return 'Discovery prerequisite belum terpenuhi';
    }

    public function name(): string
    {
        return 'DiscoveryGate';
    }
}
