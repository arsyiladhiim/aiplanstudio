<?php

namespace App\Console\Commands;

use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Console\Command;

/**
 * Hitung ulang stage_quality dari artifact yang tersimpan (mis. setelah
 * perubahan rubric). Hanya menimpa score stage yang punya artifact non-kosong.
 */
class RecomputeQuality extends Command
{
    protected $signature = 'pipeline:recompute-quality {--version-id= : Batasi ke satu version id}';

    protected $description = 'Rehitung stage_quality semua version dari artifact tersimpan';

    public function handle(): int
    {
        $q = Version::query()->whereNotNull('stage_status');
        if ($id = $this->option('version-id')) {
            $q->where('id', $id);
        }

        $total = 0;
        foreach ($q->cursor() as $version) {
            $runner = new PipelineRunner($version, new AiClient);
            $ref = new \ReflectionMethod($runner, 'artifactColumn');
            $ref->setAccessible(true);
            $refScore = new \ReflectionMethod($runner, 'computeStageQuality');
            $refScore->setAccessible(true);

            $quality = $version->stage_quality ?? [];
            $changed = false;
            foreach (array_keys($version->stage_status ?? []) as $stage) {
                $col = $ref->invoke($runner, $stage);
                if (! $col) {
                    continue;
                }
                $stored = $version->{$col};
                if (empty($stored)) {
                    continue;
                }
                $content = is_array($stored) ? json_encode($stored) : (string) $stored;
                $score = $refScore->invoke($runner, $stage, $content);
                if ($score !== null && ($quality[$stage] ?? null) !== $score) {
                    $quality[$stage] = $score;
                    $changed = true;
                }
            }
            if ($changed) {
                $version->stage_quality = $quality;
                $version->saveQuietly();
                $total++;
            }
        }

        $this->info("pipeline:recompute-quality done — {$total} version(s) diperbarui.");

        return self::SUCCESS;
    }
}
