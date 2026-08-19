<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OriginalityScoreEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_stats_include_originality_score(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        Version::factory()->create([
            'project_id' => $project->id,
            'stage_status' => ['prd' => 'done', 'analisa' => 'done'],
            'stage_quality' => ['prd' => 0.9, 'analisa' => 0.7],
        ]);

        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $resp->assertOk();
        $project = $resp->json('recent_projects.0');
        $this->assertSame(80, $project['originality_score']); // avg(0.9, 0.7) * 100 = 80
    }

    public function test_originality_score_null_when_no_quality(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        Version::factory()->create([
            'project_id' => $project->id,
            'stage_status' => ['prd' => 'done'],
            'stage_quality' => null,
        ]);

        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $resp->assertOk();
        $this->assertNull($resp->json('recent_projects.0.originality_score'));
    }
}