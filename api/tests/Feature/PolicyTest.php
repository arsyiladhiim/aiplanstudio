<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_project_but_not_others(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);

        // Owner can update
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/projects/{$project->id}", ['title' => 'Updated'])
            ->assertOk();

        // Other user gets 404 (scoped query) + 403 if accessed directly
        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/projects/{$project->id}", ['title' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_admin_cannot_update_others_project_via_scoped_route(): void
    {
        // Catatan: ProjectController::update scope by user_id sehingga admin
        // TIDAK otomatis punya akses via endpoint ini. Admin override
        // seharusnya melewati Service/admin route terpisah (di luar scope CP-5).
        // Test ini memastikan scope tidak bocor.
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/projects/{$project->id}", ['title' => 'AdminOverride'])
            ->assertNotFound();
    }

    public function test_user_cannot_update_others_version_artifact(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $owner->id]);
        $version = Version::factory()->create(['project_id' => $project->id]);

        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/artifacts", [
                'stage' => 'analisa',
                'content' => 'hack',
            ])
            ->assertNotFound();
    }
}
