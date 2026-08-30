<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Models\Project;
use App\Models\TaskProgress;
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

    public function test_user_can_update_their_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$project->id}", [
                'title' => 'Updated Title',
                'idea' => 'Updated idea',
                'target' => 'both',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $project->id,
                'title' => 'Updated Title',
                'idea' => 'Updated idea',
                'target' => 'both',
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'title' => 'Updated Title',
            'idea' => 'Updated idea',
            'target' => 'both',
        ]);
    }

    public function test_cannot_update_other_users_project(): void
    {
        $otherProject = Project::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$otherProject->id}", [
                'title' => 'Hijacked',
            ]);

        $response->assertStatus(404);
    }

    public function test_toggle_favorite(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'is_favorite' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$project->id}/favorite");

        $response->assertStatus(200)
            ->assertJson(['is_favorite' => true]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'is_favorite' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$project->id}/favorite");

        $response->assertStatus(200)
            ->assertJson(['is_favorite' => false]);
    }

    public function test_toggle_pin(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'is_pinned' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$project->id}/pin");

        $response->assertStatus(200)
            ->assertJson(['is_pinned' => true]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'is_pinned' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$project->id}/pin");

        $response->assertStatus(200)
            ->assertJson(['is_pinned' => false]);
    }

    public function test_cannot_pin_other_users_project(): void
    {
        $other = Project::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/projects/{$other->id}/pin");

        $response->assertStatus(404);
    }

    public function test_index_sorts_pinned_first(): void
    {
        $pinned = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Pinned', 'is_pinned' => true]);
        $favorite = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Favorite', 'is_favorite' => true]);
        $normal = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Normal']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/projects');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame('Pinned', $titles[0]);
    }

    public function test_index_filter_target(): void
    {
        Project::factory()->create(['user_id' => $this->user->id, 'title' => 'WebApp', 'target' => 'web']);
        Project::factory()->create(['user_id' => $this->user->id, 'title' => 'BothApp', 'target' => 'both']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/projects?target=web');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame(['WebApp'], $titles);
    }

    public function test_index_filter_pinned(): void
    {
        $pinned = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Pin', 'is_pinned' => true]);
        $unpinned = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'NoPin']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/projects?pinned=1');

        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame(['Pin'], $titles);
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

    public function test_search_finds_projects_by_title_idea_stack(): void
    {
        Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Belajar Laravel', 'idea' => 'xxx', 'stack' => null]);
        Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Other', 'idea' => 'dasar Laravel + Next', 'stack' => null]);
        Project::factory()->create(['user_id' => $this->user->id, 'title' => 'StackApp', 'idea' => 'xxx', 'stack' => 'Flutter+Go']);

        $r1 = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects/search?q=laravel');
        $r1->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($r1->json('projects')));

        $r2 = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects/search?q=flutter');
        $r2->assertStatus(200);
        $this->assertCount(1, $r2->json('projects'));
    }

    public function test_search_rejects_short_query(): void
    {
        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects/search?q=a');
        $r->assertStatus(200)
            ->assertJson(['projects' => [], 'versions' => []]);
    }

    public function test_search_does_not_leak_other_users_projects(): void
    {
        $other = User::factory()->create();
        Project::factory()->create(['user_id' => $other->id, 'title' => 'Hidden']);

        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects/search?q=hidden');
        $r->assertStatus(200)
            ->assertJsonCount(0, 'projects');
    }

    public function test_search_includes_versions(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'P']);
        $version = Version::factory()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'analysis' => 'Arsitektur microservices dengan Kafka',
        ]);

        $r = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects/search?q=kafka');
        $r->assertStatus(200);
        $versions = $r->json('versions');
        $this->assertGreaterThanOrEqual(1, count($versions));
        $this->assertSame($version->id, $versions[0]['id']);
    }

    public function test_toggle_archive_hides_from_default_index(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'ToArchive']);
        $other = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Active']);

        $r = $this->actingAs($this->user, 'sanctum')->patchJson("/api/projects/{$project->id}/archive");
        $r->assertStatus(200)->assertJsonStructure(['archived_at']);

        $r2 = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects');
        $titles = collect($r2->json('data'))->pluck('title')->all();
        $this->assertContains('Active', $titles);
        $this->assertNotContains('ToArchive', $titles);

        $r3 = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects?archived=1');
        $titles3 = collect($r3->json('data'))->pluck('title')->all();
        $this->assertContains('ToArchive', $titles3);
        $this->assertNotContains('Active', $titles3);

        $this->actingAs($this->user, 'sanctum')->patchJson("/api/projects/{$project->id}/archive");
        $r4 = $this->actingAs($this->user, 'sanctum')->getJson('/api/projects');
        $titles4 = collect($r4->json('data'))->pluck('title')->all();
        $this->assertContains('ToArchive', $titles4);
    }

    public function test_cannot_archive_other_users_project(): void
    {
        $other = Project::factory()->create();
        $r = $this->actingAs($this->user, 'sanctum')->patchJson("/api/projects/{$other->id}/archive");
        $r->assertStatus(404);
    }

    public function test_tasks_aggregates_across_versions(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $version = Version::factory()->create(['project_id' => $project->id, 'version_no' => 2]);
        $pp = PhaseProgress::create(['version_id' => $version->id, 'phase_key' => 'phases_web', 'done' => false]);
        TaskProgress::create(['phase_progress_id' => $pp->id, 'task_key' => 'login', 'task_type' => 'halaman', 'title' => 'Login', 'status' => 'done']);
        TaskProgress::create(['phase_progress_id' => $pp->id, 'task_key' => 'dashboard', 'task_type' => 'halaman', 'title' => 'Dashboard', 'status' => 'pending']);
        TaskProgress::create(['phase_progress_id' => $pp->id, 'task_key' => 'billing', 'task_type' => 'fitur', 'title' => 'Billing', 'status' => 'error']);

        $r = $this->actingAs($this->user, 'sanctum')->getJson("/api/projects/{$project->id}/tasks");
        $r->assertStatus(200);
        $this->assertSame(3, $r->json('summary.total'));
        $this->assertSame(1, $r->json('summary.done'));
        $this->assertSame(1, $r->json('summary.pending'));
        $this->assertSame(1, $r->json('summary.error'));
        $this->assertCount(3, $r->json('tasks'));
    }

    public function test_cannot_view_other_users_tasks(): void
    {
        $other = Project::factory()->create();
        $r = $this->actingAs($this->user, 'sanctum')->getJson("/api/projects/{$other->id}/tasks");
        $r->assertStatus(404);
    }

    public function test_export_all_versions_returns_zip(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id, 'title' => 'Exportable']);
        Version::factory()->create(['project_id' => $project->id, 'version_no' => 1, 'analysis' => 'a1']);
        Version::factory()->create(['project_id' => $project->id, 'version_no' => 2, 'analysis' => 'a2']);

        $r = $this->actingAs($this->user, 'sanctum')->getJson("/api/projects/{$project->id}/export-all");
        $r->assertStatus(200);
        $this->assertSame('application/zip', $r->headers->get('content-type'));
        $this->assertStringContainsString('exportable-all-versions.zip', (string) $r->headers->get('content-disposition'));
    }

    public function test_export_all_returns_422_when_no_versions(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        $r = $this->actingAs($this->user, 'sanctum')->getJson("/api/projects/{$project->id}/export-all");
        $r->assertStatus(422);
    }

    public function test_cannot_export_all_other_users_project(): void
    {
        $other = Project::factory()->create();
        $r = $this->actingAs($this->user, 'sanctum')->getJson("/api/projects/{$other->id}/export-all");
        $r->assertStatus(404);
    }
}
