<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineNewStagesTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();

        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-invalid',
            'model' => 'gpt-4o',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'member',
        ]);

        $this->project = $user->projects()->create([
            'title' => 'Test Project',
            'idea' => 'Aplikasi kasir sederhana',
            'target' => 'web',
        ]);

        $this->version = $this->project->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ]);
    }

    public function test_all_stages_includes_new_design_and_app_spec_stages(): void
    {
        $stages = Version::ALL_STAGES;
        $this->assertContains('design_system', $stages);
        $this->assertContains('design_system_mobile', $stages);
        $this->assertContains('app_spec_web', $stages);
        $this->assertContains('app_spec_mobile', $stages);
        $this->assertContains('api_contract', $stages);
    }

    public function test_default_status_includes_new_stages(): void
    {
        $status = Version::defaultStageStatus();
        $this->assertArrayHasKey('design_system', $status);
        $this->assertArrayHasKey('app_spec_web', $status);
        $this->assertArrayHasKey('design_system_mobile', $status);
        $this->assertArrayHasKey('app_spec_mobile', $status);
        $this->assertEquals('pending', $status['design_system']);
    }

    public function test_save_artifact_stores_design_system_with_validator_pass(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $content = str_repeat("# Design System — TestApp\n## 0. Pin the Subject\ndomain\n## 1. Design Philosophy\nphilosophy\n## 2. Token System\n```css\n@theme {\n  --color-ink: #000;\n  --color-paper: #fff;\n  --color-brand: #f00;\n  --color-accent: #0f0;\n  --font-display: 'X';\n  --font-body: 'Y';\n  --space-xs: 1px;\n  --space-sm: 2px;\n  --space-md: 3px;\n  --radius-sm: 1px;\n}\n```\n## 3. Signature Element\nA specific layout pattern that compresses transaction density vertically for warung owner quick scan. Each row shows item name price and quantity in a single horizontal line. The signature element is density-aware 3-column grid with vertical transaction timeline that avoids card stacking.\n### Screen 1: A\np\n### Screen 2: B\np\n### Screen 3: C\np\n## 4. Component Patterns\n### Btn\nx\n### Card\nx\n### Input\nx\n### Modal\nx\n### Toast\nx\n## 5. State Vocabulary\nempty\nloading\nerror\nsuccess\n## 6. Anti-Pattern Checklist\n- [ ] no gradient\n- [ ] no card grid\n- [ ] no centered hero\n- [ ] no Inter\n- [ ] no opacity hover\n- [ ] no shadow\n- [ ] no welcome\n## 7. Layout Rhythm\na\nb\nc\n## 8. Motion Choreography\nmom\nreduced\n## 9. Microcopy Voice\nbtn\nempty\nerror\ntone\n", 5);

        $ref->invoke($runner, 'design_system', $content);
        $this->version->refresh();
        $this->assertSame($content, $this->version->design_system);
    }

    public function test_save_artifact_rejects_design_system_without_css_fence(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $content = "# Design System\n## 0. Pin the Subject\nx\n## 1. Design Philosophy\nx\n## 2. Token System\nno code fence\n## 3. Signature Element\nx\n## 4. Component Patterns\nx\n## 5. State Vocabulary\nx\n## 6. Anti-Pattern Checklist\nx\n## 7. Layout Rhythm\nx\n## 8. Motion Choreography\nx\n## 9. Microcopy Voice\nx";

        $this->expectException(\RuntimeException::class);
        $ref->invoke($runner, 'design_system', $content);
    }

    public function test_save_artifact_stores_valid_app_spec_web_json(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $content = json_encode([
            'version' => '1',
            'generated_at' => '2026-08-18',
            'generated_from_stages' => ['analisa'],
            'halaman' => [
                ['key' => 'halaman_a', 'title' => 'A', 'route' => '/a', 'phase_owner' => 'fase1', 'description' => 'x', 'components_used' => ['comp_x'], 'design_signature' => 's'],
                ['key' => 'halaman_b', 'title' => 'B', 'route' => '/b', 'phase_owner' => 'fase1', 'description' => 'x', 'components_used' => ['comp_x'], 'design_signature' => 's'],
                ['key' => 'halaman_c', 'title' => 'C', 'route' => '/c', 'phase_owner' => 'fase1', 'description' => 'x', 'components_used' => ['comp_x'], 'design_signature' => 's'],
            ],
            'navigation' => [
                'primary_menu' => [
                    ['key' => 'menu_a', 'title' => 'A', 'icon' => 'a', 'route' => '/a'],
                    ['key' => 'menu_b', 'title' => 'B', 'icon' => 'b', 'route' => '/b'],
                ],
            ],
            'flows' => [
                ['key' => 'flow_x', 'title' => 'X', 'steps' => [
                    ['order' => 1, 'from' => 'halaman_a', 'action' => 'go', 'to' => 'halaman_b'],
                    ['order' => 2, 'from' => 'halaman_b', 'action' => 'go', 'to' => 'halaman_c'],
                ]],
            ],
            'components' => [
                ['key' => 'comp_x', 'title' => 'X', 'type' => 'primitive', 'used_in' => ['halaman_a', 'halaman_b', 'halaman_c'], 'props_signature' => 'interface X {}'],
                ['key' => 'comp_y', 'title' => 'Y', 'type' => 'primitive', 'used_in' => ['halaman_a'], 'props_signature' => 'interface Y {}'],
                ['key' => 'comp_z', 'title' => 'Z', 'type' => 'composite', 'used_in' => ['halaman_b'], 'props_signature' => 'interface Z {}'],
            ],
        ]);

        $ref->invoke($runner, 'app_spec_web', $content);
        $this->version->refresh();
        $this->assertIsArray($this->version->app_spec_web);
        $this->assertCount(3, $this->version->app_spec_web['halaman']);
    }

    public function test_save_artifact_rejects_app_spec_with_broken_cross_reference(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $content = json_encode([
            'version' => '1',
            'generated_from_stages' => ['analisa'],
            'halaman' => [
                ['key' => 'halaman_a', 'title' => 'A', 'route' => '/a', 'phase_owner' => 'fase1', 'description' => 'x', 'components_used' => ['comp_nonexistent'], 'design_signature' => 's'],
                ['key' => 'halaman_b', 'title' => 'B', 'route' => '/b', 'phase_owner' => 'fase1', 'description' => 'x', 'components_used' => [], 'design_signature' => 's'],
                ['key' => 'halaman_c', 'title' => 'C', 'route' => '/c', 'phase_owner' => 'fase1', 'description' => 'x', 'components_used' => [], 'design_signature' => 's'],
            ],
            'navigation' => ['primary_menu' => [['key' => 'm', 'title' => 'M', 'route' => '/']]],
            'flows' => [],
            'components' => [],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/comp_nonexistent/');
        $ref->invoke($runner, 'app_spec_web', $content);
    }
}
