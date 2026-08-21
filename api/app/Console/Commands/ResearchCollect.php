<?php

namespace App\Console\Commands;

use App\Services\Research\ResearchAgentService;
use Illuminate\Console\Command;

class ResearchCollect extends Command
{
    protected $signature = 'research:collect {--force : Abaikan status enabled}';

    protected $description = 'Kumpulkan ide digitalisasi via web research (scheduler hourly)';

    public function handle(ResearchAgentService $service): int
    {
        $result = $service->collect(force: (bool) $this->option('force'));
        $this->info("research-agent: {$result['status']} ({$result['created']} baru)");

        return self::SUCCESS;
    }
}
