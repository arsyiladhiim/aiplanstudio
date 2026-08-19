<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiffEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    private function makeVersion(array $overrides = []): Version
    {
        return Version::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'version_no' => Version::where('project_id', $this->project->id)->count() + 1,
        ], $overrides));
    }

    public function test_diff_includes_new_stage_fields(): void
    {
        $v1 = $this->makeVersion(['prd' => 'PRD v1']);
        $v2 = $this->makeVersion([
            'prd' => 'PRD v2',
            'design_system' => '# Design System A',
            'app_spec_web' => ['nama' => 'X', 'halaman' => [], 'components' => []],
        ]);

        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$v1->id}/diff?compare={$v2->id}");

        $resp->assertOk();
        $fields = collect($resp->json('diffs'))->pluck('field')->all();
        $this->assertContains('design_system', $fields);
        $this->assertContains('app_spec_web', $fields);
        $this->assertContains('env_config', $fields);
        $this->assertContains('observability', $fields);

        $prd = collect($resp->json('diffs'))->firstWhere('field', 'prd');
        $this->assertTrue($prd['changed']);
        $this->assertSame('PRD v1', $prd['left']);
        $this->assertSame('PRD v2', $prd['right']);

        $ds = collect($resp->json('diffs'))->firstWhere('field', 'design_system');
        $this->assertNull($ds['left']);
        $this->assertSame('# Design System A', $ds['right']);
        $this->assertTrue($ds['changed']);
    }

    public function test_diff_requires_compare_param(): void
    {
        $v1 = $this->makeVersion();
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$v1->id}/diff")
            ->assertStatus(422);
    }

    public function test_diff_blocks_other_users_versions(): void
    {
        $other = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $other->id]);
        $v1 = $this->makeVersion();
        $v2 = Version::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$v1->id}/diff?compare={$v2->id}")
            ->assertStatus(404);
    }
}
