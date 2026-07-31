<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionTest extends TestCase
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

    public function test_create_version_auto_increments(): void
    {
        $first = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/versions");

        $first->assertStatus(201)
            ->assertJson([
                'version_no' => 1,
            ]);

        $second = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/versions");

        $second->assertStatus(201)
            ->assertJson([
                'version_no' => 2,
            ]);
    }

    public function test_show_version_with_relations(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'analysis' => 'Test analysis',
            'prd' => 'Test PRD',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$version->id}");

        $response->assertStatus(200)
            ->assertJson([
                'version_no' => 1,
                'analysis' => 'Test analysis',
                'prd' => 'Test PRD',
            ])
            ->assertJsonStructure([
                'id', 'version_no', 'stage_status', 'analysis', 'prd',
                'project' => ['id', 'title'],
                'phase_progress',
            ]);
    }

    public function test_toggle_phase_done(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'phases' => [['key' => 'setup', 'title' => 'Setup', 'tasks' => [], 'prompt' => '']],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/phases/setup", [
                'done' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'phase_key' => 'setup',
                'done' => true,
            ]);

        $this->assertDatabaseHas('phase_progress', [
            'version_id' => $version->id,
            'phase_key' => 'setup',
            'done' => true,
        ]);
    }

    public function test_toggle_phase_invalid_key_returns_422(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/phases/invalid-key", [
                'done' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_toggle_phase_undo(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'phases' => [['key' => 'setup', 'title' => 'Setup', 'tasks' => [], 'prompt' => '']],
        ]);

        // Set done
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/phases/setup", ['done' => true]);

        // Toggle back to undone
        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/phases/setup", ['done' => false]);

        $response->assertStatus(200)
            ->assertJson(['done' => false]);
    }

    public function test_export_markdown_format(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'analysis' => '# Analysis',
            'prd' => '# PRD',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$version->id}/export?format=md");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/markdown', $response->headers->get('Content-Type') ?? '');
    }

    public function test_export_zip_format(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'analysis' => 'Test analysis',
            'prd' => 'Test PRD',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/export?format=zip");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/zip', $response->headers->get('Content-Type') ?? '');
    }

    public function test_export_invalid_format_returns_422(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$version->id}/export?format=pdf");

        $response->assertStatus(422);
    }

    public function test_cannot_access_other_users_version(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $version = Version::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$version->id}");

        $response->assertStatus(404);
    }
}
