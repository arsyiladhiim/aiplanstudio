<?php

namespace App\Console\Commands;

use App\Models\Version;
use Illuminate\Console\Command;

/**
 * Sweeper stage stuck "running": SSE stream mati saat container restart /
 * request abort sehingga tidak ada yang mereset status. Versi yang tidak
 * di-update > 15 menit dengan stage running dianggap orphan → pending.
 */
class SweepStuckStages extends Command
{
    protected $signature = 'pipeline:sweep-stuck {--minutes=15}';

    protected $description = 'Reset stage status running yang orphan (stuck) ke pending';

    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));
        $versions = Version::query()
            ->where('updated_at', '<', $cutoff)
            ->get()
            ->filter(fn (Version $v) => in_array('running', $v->stage_status ?? [], true));

        $swept = 0;
        foreach ($versions as $v) {
            $statuses = $v->stage_status;
            foreach ($statuses as $k => $s) {
                if ($s === 'running') {
                    $statuses[$k] = 'pending';
                }
            }
            $v->stage_status = $statuses;
            $v->saveQuietly();
            $swept++;
        }

        $this->info("pipeline:sweep-stuck done — {$swept} version(s) direset.");

        return self::SUCCESS;
    }
}
