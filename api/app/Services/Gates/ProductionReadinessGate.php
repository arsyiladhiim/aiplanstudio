<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * ProductionReadinessGate — composite aggregate.
 * Memerlukan SEMUA evidence verify.* (review, smoke_test, production_readiness) passed dalam window 7 hari.
 *
 * Window hardcoded per Plan 46 §12 (Q-E): 7 hari. Env-overridable via APP_PROD_READINESS_WINDOW_DAYS.
 */
class ProductionReadinessGate implements StageGate
{
    public const WINDOW_DAYS = 7;

    public function appliesTo(): array
    {
        return ['verify.production_readiness'];
    }

    public function passes(Version $v, string $stageKey): bool
    {
        if (! \Schema::hasTable('version_stage_evidence')) {
            return true;
        }

        $windowDays = (int) (env('APP_PROD_READINESS_WINDOW_DAYS') ?: self::WINDOW_DAYS);
        $since = now()->subDays($windowDays);

        $rows = \DB::table('version_stage_evidence')
            ->where('version_id', $v->id)
            ->whereIn('stage_key', ['verify.review', 'smoke_test', 'verify.production_readiness'])
            ->where('updated_at', '>=', $since)
            ->get();

        if ($rows->count() < 3) {
            return false;
        }

        // Semua required flags true di setiap row.
        foreach ($rows as $r) {
            foreach (['tests_passed', 'lint_passed', 'build_passed', 'migrate_passed', 'security_passed', 'perf_passed'] as $flag) {
                if (! ($r->$flag ?? false)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function reason(Version $v, string $stageKey): string
    {
        if (! \Schema::hasTable('version_stage_evidence')) {
            return 'ProductionReadinessGate belum aktif';
        }

        $windowDays = (int) (env('APP_PROD_READINESS_WINDOW_DAYS') ?: self::WINDOW_DAYS);
        $since = now()->subDays($windowDays);
        $rows = \DB::table('version_stage_evidence')
            ->where('version_id', $v->id)
            ->whereIn('stage_key', ['verify.review', 'smoke_test', 'verify.production_readiness'])
            ->where('updated_at', '>=', $since)
            ->get();

        if ($rows->count() < 3) {
            return 'Evidence verify.* belum lengkap dalam '.$windowDays.' hari';
        }

        return 'ProductionReadinessGate ok';
    }

    public function name(): string
    {
        return 'ProductionReadinessGate';
    }
}
