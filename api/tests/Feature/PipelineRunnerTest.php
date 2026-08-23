<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\PhaseProgress;
use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\AiOutputParser;
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
            'standards_web', 'phases_web', 'master_web',
            'pertanyaan_mobile',
            'standards_mobile', 'phases_mobile', 'master_mobile',
            'env_config', 'security', 'deployment', 'observability',
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
            'design_system',
            'phases_web', 'standards_web', 'testing_strategy', 'master_web',
            'app_spec_web',
            'design_system_mobile',
            'pertanyaan_mobile',
            'standards_mobile', 'phases_mobile', 'master_mobile',
            'app_spec_mobile',
            'env_config', 'security', 'deployment', 'observability',
            'agents',
            'verify.review', 'smoke_test', 'verify.production_readiness',
        ];
        $const = (new \ReflectionClass(Version::class))->getConstant('ALL_STAGES');
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
        $this->assertStringContainsString('senior business analyst', $prompt);
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
            'standards_web', 'phases_web', 'master_web',
            'pertanyaan_mobile',
            'standards_mobile', 'phases_mobile', 'master_mobile',
            'env_config', 'security', 'deployment', 'observability',
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

        $content = "# Analisa: Test\n## 1. Intent Summary\nTest summary.\n## 2. User Personas\n- Persona 1: Test\n## 3. Core Problem\n- JTBD-1: When X, I want Y, so I can Z.\n## 4. Success Metrics\n- North Star: MAU\n## 5. Anti-Goals\n- None\n## 6. Daftar Halaman\n- Login: halaman login";

        $ref->invoke($runner, 'analisa', $content);

        $this->version->refresh();
        $this->assertSame($content, $this->version->analysis);
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
        $content = "Berikut ERD:\n```json\n{\n\"nodes\": [{\"id\": \"users\", \"label\": \"users\", \"fields\": [\"id\", \"name\"]}],\n\"edges\": [{\"from\": \"posts\", \"to\": \"users\", \"relation\": \"belongs_to\"}],\n\"api_contract\": [{\"resource\": \"users\", \"method\": \"GET\", \"path\": \"/users\", \"description\": \"list users\", \"auth\": \"session\"}]\n}\n```";

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
        $content = "TABEL: users | id, name\n{\"api_contract\": [{\"resource\": \"users\", \"method\": \"POST\", \"path\": \"/users\", \"description\": \"create\", \"auth\": \"session\"}]}";

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
        $content = "```json\n{\"api_contract\": [{\"resource\": \"health\", \"method\": \"GET\", \"path\": \"/ping\", \"description\": \"ping\", \"auth\": \"none\"}]}\n```";

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
        $content = '[{"resource":"users","method":"GET","path":"/users","description":"List user","auth":"session"}]';
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
        $content = '{"base_url":"/api","endpoints":[{"resource":"auth","method":"POST","path":"/auth/login","description":"login","auth":"none"}]}';
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
        $content = "Berikut adalah contract:\n```json\n[{\"resource\":\"health\",\"method\":\"GET\",\"path\":\"/ping\",\"description\":\"ping\",\"auth\":\"none\"}]\n```\nSemoga membantu.";
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
        $content = "[{resource:'health',method:'GET',path:'/healthz',description:'health',auth:'none'}]";
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
        $opts = '[{"key":"A","text":"a"},{"key":"B","text":"b"},{"key":"C","text":"c"},{"key":"D","text":"d"},{"key":"E","text":"lainnya"}]';
        $content = '{"ambiguities":["a"],"questions":[{"id":"q1","question":"Q?","options":'.$opts.'},{"id":"q2","question":"Q?","options":'.$opts.'},{"id":"q3","question":"Q?","options":'.$opts.'}]}';
        $parser = new AiOutputParser;

        $this->assertSame(3, $parser->mcqCount($content));
    }

    public function test_mcq_count_returns_zero_for_non_json(): void
    {
        $parser = new AiOutputParser;

        $this->assertSame(0, $parser->mcqCount('bukan json'));
    }

    public function test_mcq_count_returns_zero_without_questions_key(): void
    {
        $content = '{"foo":"bar"}';
        $parser = new AiOutputParser;

        $this->assertSame(0, $parser->mcqCount($content));
    }

    public function test_save_pertanyaan_stores_clean_json_when_valid(): void
    {
        $opts = [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b'], ['key' => 'C', 'text' => 'c'], ['key' => 'D', 'text' => 'd'], ['key' => 'E', 'text' => 'lainnya']];
        $questions = array_map(fn ($i) => ['id' => "q{$i}", 'question' => 'Q?', 'options' => $opts], range(1, 5));
        $content = "Berikut pertanyaan:\n```json\n".json_encode(['ambiguities' => ['a'], 'questions' => $questions])."\n```";
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'pertanyaan', $content);
        $this->version->refresh();

        $decoded = json_decode($this->version->pertanyaan, true);
        $this->assertIsArray($decoded);
        $this->assertSame(5, count($decoded['questions'] ?? []));
    }

    public function test_save_pertanyaan_stores_raw_when_invalid(): void
    {
        $content = 'ini bukan json sama sekali';
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'pertanyaan', $content);
        $this->version->refresh();

        $this->assertSame('ini bukan json sama sekali', $this->version->pertanyaan);
    }

    public function test_mcq_retry_constants(): void
    {
        $this->assertGreaterThanOrEqual(3, (new \ReflectionClass(PipelineRunner::class))->getConstant('MAX_MCQ_RETRIES'));
        $this->assertLessThanOrEqual(20, (new \ReflectionClass(PipelineRunner::class))->getConstant('MAX_MCQ_RETRIES'));
        $this->assertSame(5, (new \ReflectionClass(PipelineRunner::class))->getConstant('MIN_MCQ_QUESTIONS'));
        $this->assertSame(10, (new \ReflectionClass(PipelineRunner::class))->getConstant('MAX_MCQ_QUESTIONS'));
    }

    public function test_master_web_tracking_block_shows_when_token_exists(): void
    {
        // CP-6: tracking token dibuat via Setup Tracking UI, bukan auto-generate.
        // Test ini verify: ketika token ADA di DB → tracking block muncul + token terlihat di prompt.
        $expectedName = 'auto-tracking-'.substr(md5((string) $this->version->id), 0, 8);
        $result = ProjectApiToken::generate($this->project, $expectedName);
        $this->version->refresh();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'master_web', $this->version);

        $this->assertStringContainsString('WEBHOOK TRACKING', $prompt);
        $this->assertStringContainsString('X-Token-Secret', $prompt);
        $this->assertStringContainsString('phase-complete', $prompt);
        // CP-6: tidak ada token plain auto-generated di versions.tracking_token
        $this->version->refresh();
        $this->assertNull($this->version->tracking_token);
        // ProjectApiToken dibuat hanya oleh explicit call, bukan auto-gen di PipelineRunner
        $this->assertSame(1, $this->project->apiTokens()->count());
    }

    public function test_master_web_tracking_block_skipped_when_no_token(): void
    {
        // CP-6/CP-29: tanpa token, URL + format webhook TETAP ditulis (agent tahu target),
        // plus instruksi agar agent minta user setup tracking sebelum membangun.
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'master_web', $this->version);

        $this->assertStringContainsString('phase-complete', $prompt);
        $this->assertStringContainsString('X-Token-Secret: <SECRET>', $prompt);
        $this->assertStringContainsString('Token tracking BELUM tersedia', $prompt);
        $this->assertStringContainsString('Setup Tracking', $prompt);
        $this->assertSame(0, $this->project->apiTokens()->count());
    }

    /** CP-44 CP-02: kredensial tersimpan terenkripsi dan disematkan ke master prompt + URL publik dipakai. */
    public function test_tracking_block_embeds_credentials_and_public_url(): void
    {
        config(['app.tracking_base_url' => 'https://tracking.example.com']);
        $expectedName = 'auto-tracking-'.substr(md5((string) $this->version->id), 0, 8);
        $result = ProjectApiToken::generate($this->project, $expectedName);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'master_web', $this->version);

        $this->assertStringContainsString('https://tracking.example.com/api/webhooks/phase-complete', $prompt);
        $this->assertStringContainsString('TRACKING CREDENTIALS', $prompt);
        $this->assertStringContainsString($result['token'], $prompt);
        $this->assertStringContainsString($result['secret'], $prompt);
        // Kontrak error handling CP-03 hadir di prompt.
        $this->assertStringContainsString('exponential backoff', $prompt);
        $this->assertStringContainsString('HTTP 409', $prompt);
        $this->assertStringNotContainsString('dan berhenti', $prompt);
    }

    /** CP-44 CP-02: tanpa TRACKING_BASE_URL, fallback ke APP_URL. */
    public function test_tracking_block_falls_back_to_app_url(): void
    {
        config(['app.tracking_base_url' => null]);
        config(['app.url' => 'http://localhost:8000']);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'master_web', $this->version);
        $this->assertStringContainsString('http://localhost:8000/api/webhooks/phase-complete', $prompt);
    }

    public function test_master_web_context_includes_phases_breakdown(): void
    {
        $this->version->update([
            'phases' => [
                ['key' => 'fase1_setup', 'title' => 'Fase 1 Setup', 'tasks' => [], 'prompt' => ''],
                ['key' => 'fase2_backend', 'title' => 'Fase 2 Backend', 'tasks' => [], 'prompt' => ''],
            ],
        ]);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'master_web', $this->version);

        $this->assertStringContainsString('Fase (dari stages phases_web', $prompt);
        $this->assertStringContainsString('fase1_setup', $prompt);
        $this->assertStringContainsString('fase2_backend', $prompt);
    }

    public function test_agents_context_includes_standards_and_erd(): void
    {
        $this->version->update([
            'standards' => 'STANDARDS.md web content',
            'erd' => ['nodes' => [], 'edges' => [], 'api_contract' => []],
        ]);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'agents', $this->version);

        $this->assertStringContainsString('Standards (web)', $prompt);
        $this->assertStringContainsString('API Contract', $prompt);
        $this->assertStringContainsString('Dokumen Operasional', $prompt);
    }

    public function test_pertanyaan_mobile_context_truncates_master_prompt(): void
    {
        $this->project->update(['target' => 'both']);
        $longMp = str_repeat('MASTER PROMPT BLOCK. ', 1000);
        $this->version->update([
            'master_prompt' => $longMp,
            'master_web' => $longMp,
            'erd' => ['nodes' => [], 'edges' => [], 'api_contract' => []],
        ]);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);

        $prompt = $ref->invoke($runner, 'pertanyaan_mobile', $this->version);

        $this->assertStringContainsString('Master Prompt Web (SUDAH SELESAI)', $prompt);
        $this->assertStringContainsString('[... truncated for context size ...]', $prompt);
        $this->assertLessThan(5000, strlen($prompt), 'pertanyaan_mobile context must stay < 5KB after truncation');
    }

    public function test_truncate_for_context_helper(): void
    {
        $short = 'hello';
        $this->assertSame('hello', PipelineRunner::truncateForContext($short, 100));

        $long = str_repeat('x', 5000);
        $truncated = PipelineRunner::truncateForContext($long, 100);
        $this->assertLessThanOrEqual(100 + 60, strlen($truncated));
        $this->assertStringContainsString('[... truncated', $truncated);
    }

    public function test_update_stage_status_syncs_phase_progress_table(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'updateStageStatus');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'analisa', 'running');
        $progress = PhaseProgress::where('version_id', $this->version->id)
            ->where('phase_key', 'analisa')->first();
        $this->assertNotNull($progress);
        $this->assertSame('running', $progress->status);
        $this->assertFalse($progress->done);
        $this->assertNotNull($progress->started_at);

        $ref->invoke($runner, 'analisa', 'done');
        $progress->refresh();
        $this->assertSame('done', $progress->status);
        $this->assertTrue($progress->done);
        $this->assertNotNull($progress->finished_at);

        $ref->invoke($runner, 'analisa', 'error');
        $progress->refresh();
        $this->assertSame('error', $progress->status);
        $this->assertFalse($progress->done);
        $this->assertNotNull($progress->finished_at);
    }

    public function test_mobile_skip_stage_marked_done_in_phase_progress(): void
    {
        $this->project->update(['target' => 'web']);
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'updateStageStatus');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'master_web', 'done');
        $ref->invoke($runner, 'pertanyaan_mobile', 'done');

        $progress = PhaseProgress::where('version_id', $this->version->id)
            ->where('phase_key', 'pertanyaan_mobile')->first();
        $this->assertNotNull($progress);
        $this->assertTrue($progress->done);
    }

    public function test_save_artifact_creates_snapshot_activity(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $analisaContent = "# Analisa: Test\n## 1. Intent Summary\nX.\n## 2. User Personas\n- A\n## 3. Core Problem\n- JTBD-1: Y\n## 4. Success Metrics\n- Z\n## 5. Anti-Goals\n- none\n## 6. Daftar Halaman\n- Login: x";

        $ref->invoke($runner, 'analisa', $analisaContent);

        $this->assertDatabaseHas('activities', [
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'action' => 'artifact_snapshot',
        ]);

        $ref->invoke($runner, 'analisa', $analisaContent."\n\nMore content.");
        $this->assertDatabaseCount('activities', 2);
    }

    public function test_retry_pertanyaan_returns_early_when_min_met(): void
    {
        $opts = [
            ['key' => 'A', 'text' => 'a'],
            ['key' => 'B', 'text' => 'b'],
            ['key' => 'C', 'text' => 'c'],
            ['key' => 'D', 'text' => 'd'],
            ['key' => 'E', 'text' => 'lainnya'],
        ];
        $validJson = json_encode([
            'ambiguities' => ['x'],
            'questions' => [
                ['id' => 'q1', 'question' => '?', 'options' => $opts],
                ['id' => 'q2', 'question' => '?', 'options' => $opts],
                ['id' => 'q3', 'question' => '?', 'options' => $opts],
                ['id' => 'q4', 'question' => '?', 'options' => $opts],
                ['id' => 'q5', 'question' => '?', 'options' => $opts],
            ],
        ]);

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'retryPertanyaanForMinimum');
        $ref->setAccessible(true);

        $start = microtime(true);
        $result = $ref->invoke($runner, $validJson);
        $elapsed = microtime(true) - $start;

        $this->assertSame($validJson, $result);
        $this->assertLessThan(0.1, $elapsed, 'Early-return must skip retry loop entirely.');
    }

    public function test_retry_pertanyaan_throws_after_exhausted_when_still_under_min(): void
    {
        $threeQuestions = json_encode([
            'ambiguities' => ['x'],
            'questions' => [
                ['id' => 'r1', 'question' => 'q?', 'options' => []],
                ['id' => 'r2', 'question' => 'q?', 'options' => []],
                ['id' => 'r3', 'question' => 'q?', 'options' => []],
            ],
        ]);

        // Stub AI client: selalu kembalikan 3 pertanyaan — memaksa retry loop sampai habis.
        $stub = new class extends AiClient
        {
            public bool $configured = true;

            public string $lastFinishReason = 'stop';

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function stream(array $messages, callable $onToken, ?int $maxTokens = null): string
            {
                $opts = '[{"key":"A","text":"a"},{"key":"B","text":"b"},{"key":"C","text":"c"},{"key":"D","text":"d"},{"key":"E","text":"lainnya"}]';
                $payload = sprintf('{"ambiguities":["x"],"questions":[{"id":"r1","question":"q?","options":%s},{"id":"r2","question":"q?","options":%s},{"id":"r3","question":"q?","options":%s}]}', $opts, $opts, $opts);
                $onToken($payload);

                return $payload;
            }

            public function complete(array $messages, ?int $maxTokens = null): string
            {
                return $this->stream($messages, fn () => null, $maxTokens);
            }
        };

        $runner = new PipelineRunner($this->version, $stub);
        $ref = new \ReflectionMethod($runner, 'retryPertanyaanForMinimum');
        $ref->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/hanya berisi 3 pertanyaan/');

        $ref->invoke($runner, $threeQuestions);
    }

    public function test_mcq_count_counts_plain_text_questions(): void
    {
        $parser = new AiOutputParser;

        $text = "1. Fitur apa yang paling penting?\n2. Siapa target user?\n3. Berapa estimasi budget?\n4. Kapan rilis?\n5. Platform apa saja?";
        $this->assertSame(5, $parser->mcqCount($text));

        $this->assertSame(0, $parser->mcqCount('Ini prosa biasa tanpa pertanyaan terstruktur.'));
    }

    public function test_mcq_count_finds_nested_questions_wrapper(): void
    {
        $parser = new AiOutputParser;

        $opts = '[{"key":"A","text":"a"},{"key":"B","text":"b"},{"key":"C","text":"c"},{"key":"D","text":"d"},{"key":"E","text":"lainnya"}]';
        $questions = implode(',', array_map(fn ($i) => '{"id":"q'.$i.'","question":"Q?","options":'.$opts.'}', range(1, 5)));
        $nested = '{"response":{"questions":['.$questions.']}}';
        $this->assertSame(5, $parser->mcqCount($nested));
    }

    public function test_retry_pertanyaan_resolves_for_text_output(): void
    {
        // Stub AI client yang mengembalikan teks (bukan JSON) — mcqCount fallback text = 5.
        $stub = new class extends AiClient
        {
            public string $lastFinishReason = 'stop';

            public function isConfigured(): bool
            {
                return true;
            }

            public function stream(array $messages, callable $onToken, ?int $maxTokens = null): string
            {
                $payload = "1. fitur?\n2. user?\n3. biaya?\n4. rilis?\n5. platform?";
                $onToken($payload);

                return $payload;
            }

            public function complete(array $messages, ?int $maxTokens = null): string
            {
                return $this->stream($messages, fn () => null, $maxTokens);
            }
        };

        $runner = new PipelineRunner($this->version, $stub);
        $ref = new \ReflectionMethod($runner, 'retryPertanyaanForMinimum');
        $ref->setAccessible(true);

        $result = $ref->invoke($runner, '1. a?', 'pertanyaan');

        $this->assertStringContainsString('fitur?', $result);
        $this->assertStringContainsString('platform?', $result);
    }

    public function test_mcq_valid_count_counts_only_well_formed_questions(): void
    {
        $parser = new AiOutputParser;

        $good = ['id' => 'q1', 'question' => 'Fitur apa?', 'options' => [
            ['key' => 'A', 'text' => 'a'],
            ['key' => 'B', 'text' => 'b'],
            ['key' => 'C', 'text' => 'c'],
            ['key' => 'D', 'text' => 'd'],
            ['key' => 'E', 'text' => 'lainnya'],
        ]];
        // rusak: id array, question missing; rusak: options < 4
        $brokenId = ['id' => ['x'], 'question' => '?', 'options' => $good['options']];
        $brokenQ = ['id' => 'q2', 'question' => ['nested'], 'options' => $good['options']];
        $brokenOpts = ['id' => 'q3', 'question' => 'Yakin?', 'options' => [['key' => 'A', 'text' => 'a']]];
        $brokenOptEmpty = ['id' => 'q4', 'question' => 'Z?', 'options' => [
            ['key' => 'A', 'text' => ''],
            ['key' => 'B', 'text' => 'b'],
            ['key' => 'C', 'text' => 'c'],
            ['key' => 'D', 'text' => 'd'],
        ]];

        $content = json_encode(['questions' => [$good, $brokenId, $brokenQ, $brokenOpts, $brokenOptEmpty]]);
        $this->assertSame(1, $parser->mcqValidCount($content));
        $this->assertSame(1, $parser->mcqCount($content));
    }

    public function test_save_artifact_pertanyaan_filters_invalid_questions(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $good = ['id' => 'q1', 'question' => 'Fitur apa?', 'options' => [
            ['key' => 'A', 'text' => 'a'],
            ['key' => 'B', 'text' => 'b'],
            ['key' => 'C', 'text' => 'c'],
            ['key' => 'D', 'text' => 'd'],
            ['key' => 'E', 'text' => 'lainnya'],
        ]];
        $broken = ['id' => ['x'], 'question' => ['nested'], 'options' => 'oops'];
        $fiveGood = array_map(fn ($i) => $good + ['id' => "q{$i}", 'question' => "Pertanyaan {$i}?"], range(1, 5));

        $payload = json_encode(['questions' => array_merge([$broken], $fiveGood)]);
        $ref->invoke($runner, 'pertanyaan', $payload);

        $this->version->refresh();
        $saved = json_decode((string) $this->version->pertanyaan, true);
        $this->assertCount(5, $saved['questions']);
        $this->assertSame('q1', $saved['questions'][0]['id']);
    }

    public function test_save_artifact_pertanyaan_throws_when_below_min_after_sanitize(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $broken = ['id' => ['x'], 'question' => ['nested'], 'options' => 'oops'];
        $payload = json_encode(['questions' => [$broken, $broken, $broken]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/pertanyaan valid < 5/');

        $ref->invoke($runner, 'pertanyaan', $payload);
    }
}
