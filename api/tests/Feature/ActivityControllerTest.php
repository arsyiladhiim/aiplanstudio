<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        Activity::create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'action' => 'project.created',
            'description' => 'Project dibuat',
        ]);
    }

    public function test_owner_can_view_project_activities(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/projects/{$this->project->id}/activities");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['action', 'description', 'user' => ['id', 'name']],
            ],
        ]);
        $response->assertJsonCount(1, 'data');
    }

    public function test_other_user_cannot_view_project_activities(): void
    {
        $other = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/projects/{$otherProject->id}/activities");

        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson("/api/projects/{$this->project->id}/activities");

        $response->assertStatus(401);
    }

    public function test_global_activities_requires_admin(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/activities');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_global_activities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/activities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['action', 'user' => ['id', 'name'], 'project' => ['id', 'title']],
            ],
        ]);
    }
}
