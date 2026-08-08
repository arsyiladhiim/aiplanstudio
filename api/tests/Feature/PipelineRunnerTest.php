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

    private function runner(AiClient $client): array
    {
        $stream = fopen('php://memory', 'w+');

        return [new PipelineRunner($this->version, $client, $stream), $stream];
    }

    private function streamContents($stream): string
    {
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    public function test_run_emits_error_when_provider_not_configured(): void
    {
        AiProvider::query()->delete();

        [$runner, $stream] = $this->runner(new AiClient);
        $runner->run('analisa', false);
        $output = $this->streamContents($stream);

        $this->assertStringContainsString('belum dikonfigurasi', $output);
    }

    public function test_stage_status_not_done_when_provider_not_configured(): void
    {
        AiProvider::query()->delete();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);

        [$runner, $stream] = $this->runner(new AiClient);
        $runner->run('analisa', false);
        $this->streamContents($stream);

        $this->version->refresh();
        $status = $this->version->stage_status;
        $this->assertArrayHasKey('analisa', $status);
        $this->assertNotEquals('done', $status['analisa']);
    }

    public function test_version_has_default_stage_status(): void
    {
        $default = Version::defaultStageStatus();
        $expected = [
            'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
            'phases_web', 'standards_web', 'master_web',
            'pertanyaan_mobile',
            'phases_mobile', 'standards_mobile', 'master_mobile',
            'agents',
        ];
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

    public function test_all_stages_defined(): void
    {
        $expected = [
            'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
            'phases_web', 'standards_web', 'master_web',
            'pertanyaan_mobile',
            'phases_mobile', 'standards_mobile', 'master_mobile',
            'agents',
        ];
        $const = (new \ReflectionClass(PipelineRunner::class))->getConstant('ALL_STAGES');
        $this->assertEquals($expected, $const);
    }

    public function test_run_auto_mode_continues_on_error(): void
    {
        AiProvider::query()->delete();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);

        [$runner, $stream] = $this->runner(new AiClient);
        $runner->run('analisa', true);
        $this->streamContents($stream);

        $this->version->refresh();
        $status = $this->version->stage_status;
        $this->assertNotNull($status);
    }

    public function test_not_configured_returns_false_for_empty_key(): void
    {
        AiProvider::query()->delete();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);
        $client = new AiClient;
        $this->assertFalse($client->isConfigured());
    }

    public function test_system_prompt_loads_from_file(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'systemPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'analisa', 'web');
        $this->assertStringContainsString('analis proyek software', $prompt);
        $this->assertStringContainsString('Web App', $prompt);

        $promptMobile = $ref->invoke($runner, 'analisa', 'mobile');
        $this->assertStringContainsString('Mobile', $promptMobile);

        $promptBoth = $ref->invoke($runner, 'analisa', 'both');
        $this->assertStringContainsString('Web dan Mobile Android', $promptBoth);
    }

    public function test_all_stage_prompt_files_loadable(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'systemPrompt');
        $ref->setAccessible(true);

        $stages = [
            'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
            'phases_web', 'standards_web', 'master_web',
            'pertanyaan_mobile',
            'phases_mobile', 'standards_mobile', 'master_mobile',
            'agents',
        ];
        foreach ($stages as $stage) {
            $prompt = $ref->invoke($runner, $stage, 'web');
            $this->assertNotEmpty($prompt, "Prompt {$stage} tidak boleh kosong");
        }
    }

    public function test_pipeline_runner_emits_token_with_stage_key(): void
    {
        $client = new AiClient;
        [$runner, $stream] = $this->runner($client);

        $runner->run('analisa', false);
        $output = $this->streamContents($stream);

        $this->assertIsString($output);

        if ($client->isConfigured()) {
            $this->assertStringNotContainsString('"stage":"pending"', $output);
        }
    }

    public function test_save_artifact_stores_non_json_as_string(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'analisa', 'plain text analysis');

        $this->version->refresh();
        $this->assertSame('plain text analysis', $this->version->analysis);
    }

    public function test_save_artifact_parses_erd_lines(): void
    {
        $content = "TABEL: users | id, name, email\nRELASI: posts -> users | belongs_to\nAPI: GET | /users | list users | true";

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'erd', $content);

        $this->version->refresh();
        $this->assertIsArray($this->version->erd);
        $this->assertSame('users', $this->version->erd['nodes'][0]['id']);
        $this->assertSame('posts', $this->version->erd['edges'][0]['from']);
        $this->assertSame('GET', $this->version->erd['api_contract'][0]['method']);
    }

    public function test_save_artifact_throws_when_erd_parse_fails(): void
    {
        $runner = new PipelineRunner($this->version, new AiClient);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gagal parse');
        $ref->invoke($runner, 'erd', 'not valid erd content');
    }

    public function test_save_artifact_parses_erd_json_block(): void
    {
        $content = "Berikut ERD:\n```json\n{\n\"nodes\": [{\"id\": \"users\", \"label\": \"users\", \"fields\": [\"id\", \"name\"]}],\n\"edges\": [{\"from\": \"posts\", \"to\": \"users\", \"relation\": \"belongs_to\"}],\n\"api_contract\": [{\"method\": \"GET\", \"path\": \"/users\", \"description\": \"list users\", \"auth\": true}]\n}\n```";

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'erd', $content);

        $this->version->refresh();
        $this->assertIsArray($this->version->erd);
        $this->assertSame('users', $this->version->erd['nodes'][0]['id']);
        $this->assertSame('belongs_to', $this->version->erd['edges'][0]['relation']);
        $this->assertSame('GET', $this->version->erd['api_contract'][0]['method']);
    }

    public function test_save_artifact_fills_missing_api_contract_from_json(): void
    {
        $content = "TABEL: users | id, name\n{\"api_contract\": [{\"method\": \"POST\", \"path\": \"/users\", \"description\": \"create\", \"auth\": true}]}";

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'erd', $content);

        $this->version->refresh();
        $this->assertSame('users', $this->version->erd['nodes'][0]['id']);
        $this->assertSame('POST', $this->version->erd['api_contract'][0]['method']);
        $this->assertSame('/users', $this->version->erd['api_contract'][0]['path']);
    }

    public function test_save_artifact_throws_when_erd_json_has_no_nodes(): void
    {
        $content = "```json\n{\"api_contract\": [{\"method\": \"GET\", \"path\": \"/ping\", \"description\": \"ping\", \"auth\": false}]}\n```";

        $runner = new PipelineRunner($this->version, new AiClient);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gagal parse');
        $ref->invoke($runner, 'erd', $content);
    }

    public function test_run_uses_idea_and_target_from_project(): void
    {
        $client = new AiClient;
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
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'analisa', $this->version);
        $this->assertStringContainsString('Laravel + React', $prompt);
    }

    public function test_api_contract_save_accepts_plain_array(): void
    {
        $content = '[{"method":"GET","path":"/users","description":"List user","auth":true}]';
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'api_contract', $content);
        $this->version->refresh();

        $this->assertIsArray($this->version->api_contract);
        $this->assertSame('GET', $this->version->api_contract[0]['method']);
        $this->assertSame('/users', $this->version->api_contract[0]['path']);
    }

    public function test_api_contract_save_accepts_wrapped_object_endpoints(): void
    {
        $content = '{"base_url":"/api","endpoints":[{"method":"POST","path":"/auth/login","description":"login","auth":false}]}';
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'api_contract', $content);
        $this->version->refresh();

        $this->assertIsArray($this->version->api_contract);
        $this->assertSame('POST', $this->version->api_contract[0]['method']);
    }

    public function test_api_contract_save_handles_prose_and_fence_wrap(): void
    {
        $content = "Berikut adalah contract:\n```json\n[{\"method\":\"GET\",\"path\":\"/ping\",\"description\":\"ping\",\"auth\":false}]\n```\nSemoga membantu.";
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'api_contract', $content);
        $this->version->refresh();

        $this->assertIsArray($this->version->api_contract);
        $this->assertSame('GET', $this->version->api_contract[0]['method']);
    }

    public function test_api_contract_save_handles_unquoted_and_single_quotes(): void
    {
        $content = "[{method:'GET',path:'/healthz',description:'health',auth:false}]";
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'api_contract', $content);
        $this->version->refresh();

        $this->assertIsArray($this->version->api_contract);
        $this->assertSame('GET', $this->version->api_contract[0]['method']);
    }

    public function test_api_contract_save_throws_when_invalid_json(): void
    {
        $content = 'ini bukan json sama sekali';
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON tidak valid');
        $ref->invoke($runner, 'api_contract', $content);
    }

    public function test_mcq_count_returns_question_count(): void
    {
        $content = '{"ambiguities":["a"],"questions":[{"id":"q1"},{"id":"q2"},{"id":"q3"}]}';
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'mcqCount');
        $ref->setAccessible(true);

        $this->assertSame(3, $ref->invoke($runner, $content));
    }

    public function test_mcq_count_returns_zero_for_non_json(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'mcqCount');
        $ref->setAccessible(true);

        $this->assertSame(0, $ref->invoke($runner, 'bukan json'));
    }

    public function test_mcq_count_returns_zero_without_questions_key(): void
    {
        $content = '{"foo":"bar"}';
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'mcqCount');
        $ref->setAccessible(true);

        $this->assertSame(0, $ref->invoke($runner, $content));
    }
}
