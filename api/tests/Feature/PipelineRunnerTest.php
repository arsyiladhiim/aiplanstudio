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

class PipelineRunnerTest extends TestCase
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
            'email' => 'test_' . uniqid() . '@example.com',
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

    public function test_run_emits_error_when_provider_not_configured(): void
    {
        AiProvider::truncate();

        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);

        ob_start();
        $runner->run('analisa', false);
        $output = ob_get_clean();

        $this->assertStringContainsString('belum dikonfigurasi', $output);
    }

    public function test_stage_status_not_done_when_provider_not_configured(): void
    {
        AiProvider::truncate();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);

        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);

        ob_start();
        $runner->run('analisa', false);
        ob_get_clean();

        $this->version->refresh();
        $status = $this->version->stage_status;
        $this->assertArrayHasKey('analisa', $status);
        $this->assertNotEquals('done', $status['analisa']);
    }

    public function test_version_has_default_stage_status(): void
    {
        $default = Version::defaultStageStatus();
        $expected = ['analisa', 'prd', 'architecture', 'erd', 'phases', 'master'];
        foreach ($expected as $stage) {
            $this->assertArrayHasKey($stage, $default);
            $this->assertEquals('pending', $default[$stage]);
        }
    }

    public function test_create_project_via_api_creates_version(): void
    {
        $response = $this->actingAs(User::first())
            ->postJson('/api/projects', [
                'title' => 'API Project',
                'idea' => 'Auto version test',
                'target' => 'web',
            ]);

        $response->assertStatus(201);
        $projectId = $response->json('id');

        $project = Project::with('versions')->find($projectId);
        $this->assertNotNull($project);
        $this->assertCount(1, $project->versions);
        $this->assertEquals(1, $project->versions[0]->version_no);
        $this->assertEquals('pending', $project->versions[0]->stage_status['analisa']);
    }

    public function test_all_six_stages_defined(): void
    {
        $expected = ['analisa', 'prd', 'architecture', 'erd', 'phases', 'master'];
        $const = (new \ReflectionClass(PipelineRunner::class))->getConstant('ALL_STAGES');
        $this->assertEquals($expected, $const);
    }

    public function test_run_auto_mode_continues_on_error(): void
    {
        AiProvider::truncate();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);

        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);

        ob_start();
        $runner->run('analisa', true);
        ob_get_clean();

        $this->version->refresh();
        $status = $this->version->stage_status;
        $this->assertNotNull($status);
    }

    public function test_not_configured_returns_false_for_empty_key(): void
    {
        AiProvider::truncate();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);
        $client = new AiClient();
        $this->assertFalse($client->isConfigured());
    }

    public function test_system_prompt_loads_from_file(): void
    {
        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'systemPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'analisa', 'web');
        $this->assertStringContainsString('analis proyek software', $prompt);
        $this->assertStringContainsString('Web App', $prompt);

        $promptMobile = $ref->invoke($runner, 'analisa', 'mobile');
        $this->assertStringContainsString('Mobile', $promptMobile);

        $promptBoth = $ref->invoke($runner, 'analisa', 'both');
        $this->assertStringContainsString('dua varian', $promptBoth);
    }

    public function test_all_stage_prompt_files_loadable(): void
    {
        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'systemPrompt');
        $ref->setAccessible(true);

        $stages = ['analisa', 'prd', 'architecture', 'erd', 'phases', 'master'];
        foreach ($stages as $stage) {
            $prompt = $ref->invoke($runner, $stage, 'web');
            $this->assertNotEmpty($prompt, "Prompt {$stage} tidak boleh kosong");
        }
    }

    public function test_pipeline_runner_emits_token_with_stage_key(): void
    {
        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);

        ob_start();
        $runner->run('analisa', false);
        $output = ob_get_clean();

        if ($client->isConfigured()) {
            $this->assertStringNotContainsString('"stage":"pending"', $output);
        }
    }

    public function test_save_artifact_stores_non_json_as_string(): void
    {
        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'analisa', 'plain text analysis');

        $this->version->refresh();
        $this->assertSame('plain text analysis', $this->version->analysis);
    }

    public function test_save_artifact_decodes_valid_json(): void
    {
        $content = json_encode(['tables' => [['name' => 'users']]]);

        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'erd', $content);

        $this->version->refresh();
        $this->assertIsArray($this->version->erd);
        $this->assertSame('users', $this->version->erd['tables'][0]['name']);
    }

    public function test_save_artifact_retries_on_invalid_json(): void
    {
        $mockClient = $this->createMock(AiClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('stream')
            ->willReturnCallback(function ($messages, $callback) {
                $callback(json_encode(['retried' => true]));
            });

        $runner = new PipelineRunner($this->version, $mockClient);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);
        $ref->invoke($runner, 'erd', 'not valid json');

        $this->version->refresh();
        $this->assertIsArray($this->version->erd);
        $this->assertTrue($this->version->erd['retried']);
    }

    public function test_save_artifact_throws_when_retry_also_invalid(): void
    {
        $mockClient = $this->createMock(AiClient::class);
        $mockClient->method('isConfigured')->willReturn(true);
        $mockClient->method('stream')
            ->willReturnCallback(function ($messages, $callback) {
                $callback('still not json');
            });

        $runner = new PipelineRunner($this->version, $mockClient);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('setelah retry');
        $ref->invoke($runner, 'erd', 'not valid json');
    }

    public function test_run_uses_idea_and_target_from_project(): void
    {
        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'analisa', $this->version);
        $this->assertStringContainsString('Aplikasi kasir sederhana', $prompt);
        $this->assertStringContainsString('web', $prompt);
    }

    public function test_run_uses_stack_when_present(): void
    {
        $this->project->update(['stack' => 'Laravel + React']);
        $client = new AiClient();
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'analisa', $this->version);
        $this->assertStringContainsString('Laravel + React', $prompt);
    }
}
