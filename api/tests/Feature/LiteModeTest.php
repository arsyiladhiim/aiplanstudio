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

class LiteModeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

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

    public function test_lite_stages_are_marked_skipped_with_reason(): void
    {
        $this->project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'both']);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'run');
        $ref->setAccessible(true);

        // Start at a non-lite stage (security); all non-lite stages downstream get skipped.
        $ref->invoke($runner, 'security', false, true);
        $this->version->refresh();

        foreach (['security', 'deployment', 'observability', 'agents'] as $stage) {
            $this->assertSame('skipped', ($this->version->stage_status)[$stage], "{$stage} harus skipped di lite plan");
        }
        $this->assertSame('Lite plan — hanya tahap inti dihasilkan', ($this->version->skip_reasons)['security'] ?? null);
    }

    public function test_lite_stages_subset_definition(): void
    {
        $ref = new \ReflectionClass(\App\Services\PipelineRunner::class);
        $lite = $ref->getConstant('LITE_STAGES');
        $this->assertContains('prd', $lite);
        $this->assertContains('master_web', $lite);
        $this->assertNotContains('agents', $lite);
        $this->assertNotContains('design_system', $lite);
    }

    public function test_skip_reason_records_lite(): void
    {
        $this->project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'web']);
        $this->version = Version::factory()->create(['project_id' => $this->project->id]);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'recordSkipReason');
        $ref->setAccessible(true);
        $ref->invoke($runner, 'env_config', 'Lite plan — hanya tahap inti dihasilkan');

        $this->version->refresh();
        $this->assertSame('Lite plan — hanya tahap inti dihasilkan', ($this->version->skip_reasons)['env_config']);
    }
}