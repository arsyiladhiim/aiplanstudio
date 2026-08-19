<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiOutputParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DependencyInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function makeVersion(array $stageStatus = []): Version
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'target' => 'web',
        ]);
        $version = Version::factory()->create([
            'project_id' => $project->id,
            'stage_status' => array_merge(
                array_fill_keys(Version::ALL_STAGES, 'done'),
                $stageStatus,
            ),
        ]);

        return Version::withoutEvents(fn () => $version);
    }

    public function test_regenerate_prd_resets_downstream_stages(): void
    {
        $version = $this->makeVersion();
        $stagesBefore = $version->stage_status;
        $this->assertSame('done', $stagesBefore['architecture']);
        $this->assertSame('done', $stagesBefore['erd']);
        $this->assertSame('done', $stagesBefore['master_web']);

        // Test the STAGE_DEPENDENTS constant: prd should depend on architecture
        // architecture should depend on erd, etc.
        $ref = new \ReflectionClass(\App\Services\PipelineRunner::class);
        $const = $ref->getConstant('STAGE_DEPENDENTS');
        $this->assertContains('architecture', $const['prd']);
        $this->assertContains('erd', $const['prd']);
        $this->assertContains('master_web', $const['prd']);
    }

    public function test_regenerate_master_web_does_not_reset_prd(): void
    {
        $version = $this->makeVersion();
        $ref = new \ReflectionClass(\App\Services\PipelineRunner::class);
        $const = $ref->getConstant('STAGE_DEPENDENTS');
        $this->assertNotContains('prd', $const['master_web']);
    }

    public function test_regenerate_analisa_resets_everything_except_pertanyaan(): void
    {
        $ref = new \ReflectionClass(\App\Services\PipelineRunner::class);
        $const = $ref->getConstant('STAGE_DEPENDENTS');
        // analisa depends on pertanyaan, but regenerating analisa should NOT reset pertanyaan
        $expectedDownstream = array_diff(Version::ALL_STAGES, ['analisa', 'pertanyaan']);
        $this->assertEqualsCanonicalizing(
            array_values($expectedDownstream),
            array_values($const['analisa'])
        );
    }
}
