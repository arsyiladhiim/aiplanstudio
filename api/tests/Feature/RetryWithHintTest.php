<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetryWithHintTest extends TestCase
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
            'stage_status' => Version::defaultStageStatus(),
        ]);
    }

    public function test_record_stage_error_persists_message(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'recordStageError');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'prd', 'prd: section heading hilang — ## 2. User Stories. Stage ditandai error.');
        $this->version->refresh();

        $this->assertSame('prd: section heading hilang — ## 2. User Stories. Stage ditandai error.', ($this->version->stage_errors)['prd']);
    }

    public function test_clear_stage_error_removes_message(): void
    {
        $this->version->stage_errors = ['prd' => 'some error'];
        $this->version->save();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'clearStageError');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'prd');
        $this->version->refresh();
        $this->assertArrayNotHasKey('prd', $this->version->stage_errors ?? []);
    }

    public function test_retry_and_validate_regenerates_until_success(): void
    {
        $this->version->stage_errors = [];
        $this->version->save();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);

        // Direct: valid content passes on first attempt without throwing.
        $valid = "# Analisa\n\n## 1. Intent Summary\nR\n## 2. User Personas\nP\n## 3. Core Problem\nM\n## 4. Success Metrics\nS\n## 5. Anti-Goals\nA\n## 6. Daftar Halaman\n- X\n\n".str_repeat('Konten spesifik produk lokal. ', 40);

        $ref = new \ReflectionMethod($runner, 'retryAndValidate');
        $ref->setAccessible(true);
        $result = $ref->invoke($runner, 'analisa', $valid);
        $this->assertSame($valid, $result);

        // Invalid content that cannot pass validation → must throw after retries.
        $this->expectException(\RuntimeException::class);
        $ref->invoke($runner, 'prd', 'too short');
    }

    public function test_error_hint_injected_into_run_stage(): void
    {
        $this->version->stage_errors = ['prd' => 'prd: section 7 (Differentiation) terlalu pendek.'];
        $this->version->save();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);

        $messagesRef = new \ReflectionMethod($runner, 'buildMessages');
        $messagesRef->setAccessible(true);
        $hintRef = new \ReflectionMethod($runner, 'injectRetryHint');
        $hintRef->setAccessible(true);

        $messages = $messagesRef->invoke($runner, 'prd');
        $hinted = $hintRef->invoke($runner, $messages, 'prd');
        $system = $hinted[0]['content'] ?? '';
        $this->assertStringContainsString('DORONGAN PERBAIKAN', $system);
        $this->assertStringContainsString('Differentiation', $system);
    }
}