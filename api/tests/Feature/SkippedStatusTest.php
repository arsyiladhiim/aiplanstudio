<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkippedStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        AiProvider::query()->delete();
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-invalid',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);
    }

    public function test_mobile_stages_marked_skipped_for_web_target(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'web']);
        $version = Version::factory()->create([
            'project_id' => $project->id,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        $client = new AiClient;
        $runner = new PipelineRunner($version, $client);
        $ref = new \ReflectionMethod($runner, 'run');
        $ref->setAccessible(true);

        // stage 'pertanyaan_mobile' → web target → should be marked skipped (not done)
        $ref->invoke($runner, 'pertanyaan_mobile', true);
        $version->refresh();

        $this->assertSame('skipped', ($version->stage_status)['pertanyaan_mobile']);
    }

    public function test_progress_count_excludes_skipped(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'both']);
        $version = Version::factory()->create([
            'project_id' => $project->id,
            'stage_status' => array_merge(
                Version::defaultStageStatus(),
                ['prd' => 'done', 'analisa' => 'done', 'env_config' => 'skipped', 'agents' => 'skipped'],
            ),
        ]);

        $this->assertSame(2, $version->progressCount());
    }

    public function test_visible_stage_count_web_excludes_mobile(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'web']);
        $version = Version::factory()->create(['project_id' => $project->id]);

        $this->assertSame(16, $version->visibleStageCount());
    }

    public function test_visible_stage_count_both_counts_all(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'both']);
        $version = Version::factory()->create(['project_id' => $project->id]);

        $this->assertSame(22, $version->visibleStageCount());
    }
}