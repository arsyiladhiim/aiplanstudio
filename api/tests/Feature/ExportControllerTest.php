<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use App\Models\VersionStageEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'web']);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'version_no' => 1,
            'prd' => "# PRD\n\nSome PRD content.",
            'analysis' => "# Analysis\n\nSome analysis.",
            'master_prompt' => "## 6. Tracking Webhook\n\nPlaceholder section.",
            'architecture' => "## Architecture\n\nContent.",
            'design_system' => "## Design System\n\nContent.",
            'standards' => "## Standards\n\nContent.",
            'phases' => [['key' => 'fase1', 'title' => 'Setup', 'tasks' => ['init']]],
            'security' => "## Security\n\nContent.",
            'deployment' => "## Deployment\n\nContent.",
            'env_config' => "## Env\n\nContent.",
            'agents' => "## Agents\n\nContent.",
        ]);

        ProjectApiToken::generate($this->project, 'auto-tracking-'.substr(md5((string) $this->version->id), 0, 8));
        $this->version->refresh();
    }

    public function test_export_package_zip_contains_core_files(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$this->version->id}/export-package");

        $resp->assertStatus(200);
        $this->assertSame('application/zip', $resp->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', $resp->headers->get('Content-Disposition'));

        $zipPath = tempnam(sys_get_temp_dir(), 'ep');
        file_put_contents($zipPath, $resp->streamedContent());

        $za = new \ZipArchive;
        $za->open($zipPath);
        $names = [];
        for ($i = 0; $i < $za->numFiles; $i++) {
            $names[] = $za->getNameIndex($i);
        }
        $za->close();
        @unlink($zipPath);

        $this->assertContains('PRD.md', $names);
        $this->assertContains('MASTER-INJECTED.md', $names);
        $this->assertContains('TRACKING.md', $names);
        $this->assertContains('MANIFEST.json', $names);
        $this->assertContains('STANDARDS.md', $names);
    }

    public function test_export_package_master_injected_contains_marker(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$this->version->id}/export-package");

        $zipPath = tempnam(sys_get_temp_dir(), 'ep');
        file_put_contents($zipPath, $resp->streamedContent());
        $za = new \ZipArchive;
        $za->open($zipPath);
        $master = $za->getFromName('MASTER-INJECTED.md');
        $manifest = $za->getFromName('MANIFEST.json');
        $za->close();
        @unlink($zipPath);

        $this->assertNotFalse($master);
        $this->assertStringContainsString('cp45:tracking-live:start', $master, 'MASTER-INJECTED.md harus memuat marker tracking live.');

        $manifestData = json_decode($manifest, true);
        $this->assertSame($this->version->id, $manifestData['version_id']);
        $this->assertSame('web', $manifestData['target']);
        $this->assertArrayHasKey('gate_states', $manifestData);
    }

    public function test_export_package_other_user_returns_404(): void
    {
        $other = User::factory()->create();
        $resp = $this->actingAs($other, 'sanctum')
            ->get("/api/versions/{$this->version->id}/export-package");
        $resp->assertStatus(404);
    }

    public function test_production_readiness_blocked_no_evidence(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$this->version->id}/production-readiness");

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.production_ready', false);
        $resp->assertJsonPath('data.gate', 'ProductionReadinessGate');
        $resp->assertJsonPath('data.evidence_count', 0);
    }

    public function test_production_readiness_pass_semua_evidence(): void
    {
        $evidence = [
            'tests_passed' => true,
            'lint_passed' => true,
            'build_passed' => true,
            'migrate_passed' => true,
            'security_passed' => true,
            'perf_passed' => true,
        ];
        foreach (['verify.review', 'smoke_test', 'verify.production_readiness'] as $stage) {
            VersionStageEvidence::create([
                'project_id' => $this->project->id,
                'version_id' => $this->version->id,
                'stage_key' => $stage,
            ] + $evidence);
        }

        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$this->version->id}/production-readiness");

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.production_ready', true);
        $resp->assertJsonPath('data.evidence_count', 3);
    }
}
