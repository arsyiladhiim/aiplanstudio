<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\TrackingInjector;
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

    public function test_create_version_clones_baseline_from_last_by_default(): void
    {
        $v1 = $this->project->versions()->create([
            'version_no' => 1,
            'stage_status' => array_merge(Version::defaultStageStatus(), ['pertanyaan' => 'done', 'analisa' => 'done']),
            'analysis' => 'Analisa v1',
            'prd' => 'PRD v1',
            'answers' => ['q1' => 'A'],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/versions");

        $response->assertStatus(201)
            ->assertJson([
                'version_no' => 2,
                'source_version_id' => $v1->id,
                'analysis' => 'Analisa v1',
                'prd' => 'PRD v1',
            ]);

        $v2 = Version::find($response->json('id'));
        $this->assertEquals(['q1' => 'A'], $v2->answers);
        $this->assertEquals('done', $v2->stage_status['pertanyaan']);
        $this->assertEquals('done', $v2->stage_status['analisa']);
    }

    public function test_create_version_blank_strategy_does_not_clone(): void
    {
        $this->project->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
            'analysis' => 'Analisa v1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/projects/{$this->project->id}/versions", ['strategy' => 'blank']);

        $response->assertStatus(201)
            ->assertJson([
                'version_no' => 2,
                'analysis' => null,
                'source_version_id' => null,
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
            'api_contract' => [['method' => 'GET', 'path' => '/api/users', 'description' => 'List users', 'auth' => true]],
            'pertanyaan_mobile' => '{"questions":[{"id":"qm1","question":"Q mobile?","options":[]}]}',
            'mobile_answers' => ['qm1: Q mobile?' => 'A. Ya'],
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
        $this->assertStringContainsString('## API Contract', $content);
        $this->assertStringContainsString('/api/users', $content);
        $this->assertStringContainsString('## Pertanyaan Mobile (klarifikasi)', $content);
        $this->assertStringContainsString('### Jawaban Mobile', $content);
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

    public function test_delete_version(): void
    {
        $v1 = $this->project->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ]);
        $v2 = $this->project->versions()->create([
            'version_no' => 2,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/versions/{$v2->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('versions', ['id' => $v2->id]);
    }

    public function test_cannot_delete_last_version(): void
    {
        $version = $this->project->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/versions/{$version->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('versions', ['id' => $version->id]);
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

    public function test_update_artifact(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/artifacts", [
                'stage' => 'analisa',
                'content' => 'Updated analysis content',
            ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('versions', [
            'id' => $version->id,
            'analysis' => 'Updated analysis content',
        ]);
    }

    public function test_update_artifact_all_14_stages(): void
    {
        $stageContents = [
            'pertanyaan' => ['pertanyaan', 'Pertanyaan klarifikasi web'],
            'analisa' => ['analysis', 'Analisa konten'],
            'prd' => ['prd', 'PRD content'],
            'architecture' => ['architecture', 'Architecture content'],
            'erd' => ['erd', '{"tables":[]}'],
            'api_contract' => ['api_contract', '[{"method":"GET","path":"/api/users"}]'],
            'phases_web' => ['phases', '[{"key":"setup","title":"Setup"}]'],
            'standards_web' => ['standards', 'Standards content'],
            'master_web' => ['master_prompt', 'Master prompt content'],
            'pertanyaan_mobile' => ['pertanyaan_mobile', 'Pertanyaan mobile'],
            'phases_mobile' => ['mobile_phases', '[{"key":"m-setup","title":"Setup"}]'],
            'standards_mobile' => ['mobile_standards', 'Mobile standards content'],
            'master_mobile' => ['mobile_master_prompt', 'Mobile master prompt'],
            'agents' => ['agents', 'Agents content'],
        ];

        $versionNo = 1;
        foreach ($stageContents as $stage => [$column, $content]) {
            $version = Version::factory()->create([
                'project_id' => $this->project->id,
                'version_no' => $versionNo++,
            ]);

            $response = $this->actingAs($this->user, 'sanctum')
                ->patchJson("/api/versions/{$version->id}/artifacts", [
                    'stage' => $stage,
                    'content' => $content,
                ]);

            $response->assertStatus(200, "Stage {$stage} failed");

            $updated = Version::find($version->id);
            if (in_array($stage, ['erd', 'api_contract', 'phases_web', 'phases_mobile'])) {
                $this->assertNotNull($updated->{$column}, "Column {$column} is null for stage {$stage}");
            } elseif (in_array($stage, ['master_web', 'master_mobile'])) {
                // CP-45.A: PATCH master auto-injects tracking block (server-side, deterministic).
                $this->assertStringContainsString((string) $updated->id, (string) $updated->{$column}, "Version ID harus ada di master prompt yang di-inject.");
                $this->assertStringContainsString(TrackingInjector::MARKER_START, (string) $updated->{$column}, "Marker tracking harus ada.");
                $this->assertStringContainsString($content, (string) $updated->{$column}, "Konten asli harus dipertahankan.");
            } else {
                $this->assertEquals($content, $updated->{$column}, "Column {$column} mismatch for stage {$stage}");
            }
        }
    }

    public function test_update_artifact_invalid_stage_returns_422(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/artifacts", [
                'stage' => 'invalid_stage',
                'content' => 'Some content',
            ]);

        $response->assertStatus(422);
    }

    public function test_update_answers(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/answers", [
                'answers' => ['q1' => 'Answer 1', 'q2' => 'Answer 2'],
            ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $updated = Version::find($version->id);
        $this->assertEquals(['q1' => 'Answer 1', 'q2' => 'Answer 2'], $updated->answers);
    }

    public function test_update_answers_with_mobile_answers(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/answers", [
                'answers' => ['q1' => 'Web answer'],
                'mobile_answers' => ['qm1' => 'Mobile answer'],
            ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true]);

        $updated = Version::find($version->id);
        $this->assertEquals(['q1' => 'Web answer'], $updated->answers);
        $this->assertEquals(['qm1' => 'Mobile answer'], $updated->mobile_answers);
    }

    public function test_download_standards(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'standards' => '# Web Standards content',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/standards");

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('STANDARDS.md', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('# Web Standards content', $response->getContent());
    }

    public function test_download_agents(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'agents' => '# Web Agents content',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/agents");

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('AGENTS.md', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('# Web Agents content', $response->getContent());
    }

    public function test_download_mobile_standards(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'mobile_standards' => '# Mobile Standards content',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/standards/mobile");

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('STANDARDS-MOBILE.md', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('# Mobile Standards content', $response->getContent());
    }

    public function test_download_mobile_agents(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
            'mobile_agents' => '# Mobile Agents content',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/agents/mobile");

        $response->assertStatus(200);
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('AGENTS-MOBILE.md', $response->headers->get('Content-Disposition') ?? '');
        $this->assertStringContainsString('# Mobile Agents content', $response->getContent());
    }

    public function test_regenerate_standards_returns_400_without_provider(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$version->id}/regenerate-standards");

        $response->assertStatus(400)
            ->assertJson(['ok' => false]);
    }

    public function test_regenerate_mobile_standards_returns_400_without_provider(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$version->id}/regenerate-standards/mobile");

        $response->assertStatus(400)
            ->assertJson(['ok' => false]);
    }

    public function test_cannot_update_artifact_other_users_version(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $version = Version::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$version->id}/artifacts", [
                'stage' => 'analisa',
                'content' => 'Hijacked content',
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_download_other_users_version(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $version = Version::factory()->create([
            'project_id' => $otherProject->id,
            'standards' => 'Secret standards',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$version->id}/standards");

        $response->assertStatus(404);
    }

    public function test_diff_returns_422_without_compare(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$version->id}/diff");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Parameter compare required.']);
    }

    public function test_diff_with_compare(): void
    {
        $v1 = Version::factory()->create([
            'project_id' => $this->project->id,
            'analysis' => 'Analysis v1',
            'prd' => 'PRD v1',
        ]);

        $v2 = Version::factory()->create([
            'project_id' => $this->project->id,
            'version_no' => 2,
            'analysis' => 'Analysis v2',
            'prd' => 'PRD v1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$v1->id}/diff?compare={$v2->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'left' => ['id', 'version_no', 'project_title'],
                'right' => ['id', 'version_no', 'project_title'],
                'diffs' => [
                    '*' => ['field', 'label', 'left', 'right', 'changed'],
                ],
            ]);

        $diffs = collect($response->json('diffs'))->keyBy('field');
        $this->assertTrue($diffs['analysis']['changed']);
        $this->assertFalse($diffs['prd']['changed']);
        $this->assertEquals('Analysis v1', $diffs['analysis']['left']);
        $this->assertEquals('Analysis v2', $diffs['analysis']['right']);
    }

    public function test_regenerate_stage_requires_stage(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$version->id}/regenerate", []);

        $response->assertStatus(422);
    }

    public function test_regenerate_stage_rejects_invalid_stage(): void
    {
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$version->id}/regenerate", [
                'stage' => 'bogus_stage',
            ]);

        $response->assertStatus(422);
    }

    public function test_regenerate_stage_returns_400_without_provider(): void
    {
        AiProvider::query()->delete();
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$version->id}/regenerate", [
                'stage' => 'analisa',
            ]);

        $response->assertStatus(400)
            ->assertJson(['ok' => false]);
    }

    public function test_cannot_regenerate_other_users_version(): void
    {
        $otherProject = Project::factory()->create();
        $otherVersion = Version::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$otherVersion->id}/regenerate", [
                'stage' => 'analisa',
            ]);

        $response->assertStatus(404);
    }

    public function test_restart_from_analisa_returns_400_without_provider(): void
    {
        AiProvider::query()->delete();
        $version = Version::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$version->id}/restart-from-analisa");

        $r->assertStatus(400)->assertJson(['ok' => false]);
    }

    public function test_cannot_restart_other_users_version(): void
    {
        $otherProject = Project::factory()->create();
        $otherVersion = Version::factory()->create([
            'project_id' => $otherProject->id,
        ]);

        $r = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$otherVersion->id}/restart-from-analisa");
        $r->assertStatus(404);
    }
}
