<?php

namespace Tests\Unit\PromptValidation;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossReferenceValidatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'web']);
        $this->version = Version::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_app_spec_pages_present_in_master_passes(): void
    {
        $this->version->master_prompt = "# Master Prompt\n\n## Halaman\n- home_dashboard\n- contact_list\n\n## SELESAI";
        $this->version->save();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'validateAppSpecMasterCrossRef');
        $ref->setAccessible(true);

        $spec = ['nama' => 'X', 'halaman' => [['nama' => 'home_dashboard'], ['nama' => 'contact_list']], 'components' => []];
        $ref->invoke($runner, 'app_spec_web', $spec);
        $this->assertTrue(true);
    }

    public function test_app_spec_missing_page_no_longer_throws(): void
    {
        $this->version->master_prompt = "# Master Prompt\n\n## Halaman\n- home_dashboard\n\n## SELESAI";
        $this->version->save();

        \Log::spy();
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'validateAppSpecMasterCrossRef');
        $ref->setAccessible(true);

        $spec = ['nama' => 'X', 'halaman' => [['nama' => 'never_mentioned']], 'components' => []];
        $ref->invoke($runner, 'app_spec_web', $spec);
        \Log::shouldHaveReceived('warning')->once()->withArgs(fn ($msg) => str_contains($msg, 'app_spec↔master'));
    }

    public function test_app_spec_without_master_skips_check(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'validateAppSpecMasterCrossRef');
        $ref->setAccessible(true);

        $spec = ['nama' => 'X', 'halaman' => [['nama' => 'anything']], 'components' => []];
        $ref->invoke($runner, 'app_spec_web', $spec);
        $this->assertTrue(true);
    }

    public function test_master_standards_cross_ref_logs_when_no_match(): void
    {
        $this->version->standards = "# STANDARDS\n\n## Coding Conventions\nuse TypeScript\n";
        $this->version->save();

        \Log::spy();
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'validateMasterStandardsCrossRef');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'master_web', '# Master tanpa referensi standard apapun');

        \Log::shouldHaveReceived('warning')->once()->withArgs(fn ($msg) => str_contains($msg, 'master↔standards'));
    }
}