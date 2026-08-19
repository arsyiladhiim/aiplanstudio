<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class ZipExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'prd' => '# PRD Test',
            'design_system' => '# Design System A',
            'app_spec_web' => ['nama' => 'X', 'halaman' => [], 'components' => []],
            'env_config' => "APP_KEY=\nDB_PASSWORD=\n",
            'security' => 'OWASP checks',
            'deployment' => 'Docker deploy',
            'observability' => 'Sentry logging',
            'agents' => 'AGENTS.md rules',
        ]);
    }

    public function test_zip_export_contains_artifact_files(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$this->version->id}/export?format=zip");

        $resp->assertOk();
        $this->assertStringContainsString('application/zip', $resp->headers->get('Content-Type'));

        $tmp = tempnam(sys_get_temp_dir(), 'export-test').'.zip';
        file_put_contents($tmp, $resp->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        foreach (['design-system.md', 'app-spec-web.json', 'env-config.md', 'security.md', 'deployment.md', 'observability.md', 'agents.md', 'prd.md'] as $expected) {
            $this->assertContains($expected, $names, "Missing {$expected} in zip");
        }

        $this->assertStringContainsString('# PRD Test', $zip->getFromName('prd.md'));

        $zip->close();
        @unlink($tmp);
    }

    public function test_md_export_contains_new_sections(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->get("/api/versions/{$this->version->id}/export?format=md");

        $resp->assertOk();
        $content = $resp->getContent();

        foreach (['## Design System', '## App Spec Web', '## Env & Config', '## Security'] as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }
}