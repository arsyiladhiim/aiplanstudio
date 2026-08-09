<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_create_project(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/projects', [
                'title' => 'Test Project',
                'idea' => 'A great idea for an app',
                'target' => 'web',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'idea',
                'target',
            ]);

        $this->assertDatabaseHas('projects', [
            'title' => 'Test Project',
            'user_id' => $this->user->id,
        ]);

        // Check that version 1 was auto-created
        $project = Project::first();
        $this->assertEquals(1, $project->versions()->count());
    }

    public function test_user_can_list_their_projects(): void
    {
        Project::factory()->count(3)->create(['user_id' => $this->user->id]);
        Project::factory()->count(2)->create(); // Other user's projects

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/projects');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_their_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $project->id,
                'title' => $project->title,
            ]);
    }

    public function test_user_cannot_view_other_users_project(): void
    {
        $otherProject = Project::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/projects/{$otherProject->id}");

        $response->assertStatus(404); // findOrFail returns 404 for security
    }

    public function test_user_can_delete_their_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_unauthenticated_user_cannot_create_project(): void
    {
        $response = $this->postJson('/api/projects', [
            'title' => 'Test Project',
            'idea' => 'Some idea',
            'target' => 'web',
        ]);

        $response->assertStatus(401);
    }

    public function test_create_project_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/projects', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'idea', 'target']);
    }

    public function test_create_project_rejects_invalid_target(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/projects', [
                'title' => 'Test',
                'idea' => 'Some idea',
                'target' => 'desktop',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['target']);
    }

    public function test_dashboard_active_projects_counts_projects_with_done_stage(): void
    {
        // Project A: punya 1 stage done → aktif
        $active = Project::factory()->create(['user_id' => $this->user->id]);
        $active->versions()->create([
            'version_no' => 1,
            'stage_status' => array_merge(Version::defaultStageStatus(), ['pertanyaan' => 'done']),
        ]);

        // Project B: semua pending → tidak aktif
        $idle = Project::factory()->create(['user_id' => $this->user->id]);
        $idle->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        // Project C: tidak punya versi → tidak aktif
        Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJson([
                'active_projects' => 1,
                'total_projects' => 3,
            ]);
    }
}
