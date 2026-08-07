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
            'mobile_phases' => [['key' => 'm-setup', 'title' => 'Mobile Setup', 'tasks' => [], 'prompt' => 'Build the Flutter app.']],
            'mobile_master_prompt' => '# Mobile Master Prompt',
            'mobile_standards' => '# Mobile Standards',
            'mobile_agents' => '# Mobile Agents',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$version->id}/export?format=md");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/markdown', $response->headers->get('Content-Type') ?? '');
        $content = $response->getContent();
        $this->assertStringContainsString('## Mobile (Flutter)', $content);
        $this->assertStringContainsString('Mobile Setup', $content);
        $this->assertStringContainsString('## Mobile Standards', $content);
        $this->assertStringContainsString('# Mobile Standards', $content);
        $this->assertStringContainsString('## Mobile Agents', $content);
        $this->assertStringContainsString('# Mobile Agents', $content);
    }

    public function test_export_zip_format_includes_mobile_files(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'analysis' => 'Test analysis',
            'prd' => 'Test PRD',
            'mobile_standards' => 'Standards content',
            'mobile_agents' => 'Agents content',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/export?format=zip");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/zip', $response->headers->get('Content-Type') ?? '');

        $tmp = tempnam(sys_get_temp_dir(), 'ziptest').'.zip';
        file_put_contents($tmp, $response->streamedContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->getFromName('mobile-standards.md'));
        $this->assertNotFalse($zip->getFromName('mobile-agents.md'));
        $this->assertStringContainsString('Standards content', $zip->getFromName('mobile-standards.md'));
        $zip->close();
        @unlink($tmp);
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

    public function test_diff_includes_mobile_artifacts(): void
    {
        $left = Version::factory()->create([
            'project_id' => $this->project->id,
            'mobile_master_prompt' => 'Mobile master v1',
            'mobile_phases' => [['key' => 'm-setup', 'title' => 'Mobile Setup', 'tasks' => [], 'prompt' => '']],
            'mobile_standards' => 'Standards v1',
            'mobile_agents' => 'Agents v1',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/versions");

        $right = Version::where('project_id', $this->project->id)->where('version_no', 2)->firstOrFail();
        $right->update([
            'mobile_master_prompt' => 'Mobile master v2',
            'mobile_phases' => [['key' => 'm-setup', 'title' => 'Mobile Setup', 'tasks' => [], 'prompt' => '']],
            'mobile_standards' => 'Standards v2',
            'mobile_agents' => 'Agents v1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$left->id}/diff?compare={$right->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'diffs' => [
                '*' => ['field', 'label', 'left', 'right', 'changed'],
            ],
        ]);

        $diffs = collect($response->json('diffs'))->keyBy('field');
        $this->assertTrue($diffs->has('mobile_phases'));
        $this->assertTrue($diffs->has('mobile_standards'));
        $this->assertTrue($diffs->has('mobile_agents'));
        $this->assertTrue($diffs['mobile_master_prompt']['changed']);
        $this->assertTrue($diffs['mobile_standards']['changed']);
        $this->assertFalse($diffs['mobile_agents']['changed']);
    }
}
