<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\AiOutputParser;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumeResilienceTest extends TestCase
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
            'stage_status' => Version::defaultStageStatus(),
        ]);
    }

    // R2 — api_contract JSON gagal → fallback ke ERD-embedded api_contract (schema tetap)
    public function test_api_contract_falls_back_to_erd_when_json_invalid(): void
    {
        $this->version->erd = [
            'nodes' => ['id' => 'x'],
            'edges' => [],
            'api_contract' => [
                ['resource' => 'users', 'method' => 'GET', 'path' => '/users', 'description' => 'list', 'auth' => 'required'],
            ],
        ];
        $this->version->save();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $ref->invoke($runner, 'api_contract', 'ini bukan JSON sama sekali');
        $this->version->refresh();
        $this->assertIsArray($this->version->api_contract);
        $this->assertSame('users', $this->version->api_contract[0]['resource']);
    }

    // R3 — phases parse tanpa separator ---  (split di baris FASE:)
    public function test_phases_parsed_without_separator_dashes(): void
    {
        $parser = new AiOutputParser;
        $content = "FASE: p1 | Fase Satu\nTASK: buat login\nHALAMAN: home | Home | landing\nFASE: p2 | Fase Dua\nTASK: buat api";
        $phases = $parser->parsePhasesText($content);
        $this->assertNotNull($phases);
        $this->assertCount(2, $phases);
    }

    // R4 — orphan running di-reset ke pending saat run() dipanggil
    public function test_running_orphan_reset_on_run_start(): void
    {
        $this->version->stage_status = array_merge($this->version->stage_status ?? [], ['prd' => 'running']);
        $this->version->save();

        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'run');
        $ref->setAccessible(true);

        // Tanpa provider terkonfigurasi → run() berhenti setelah cleanup.
        $ref->invoke($runner, 'analisa', false);
        $this->version->refresh();
        $this->assertSame('pending', ($this->version->stage_status)['prd']);
    }

    // R5 — MCQ fallback teks: cukup → JSON; kurang → null
    public function test_mcq_text_fallback_builds_questions(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'buildQuestionsFromText');
        $ref->setAccessible(true);

        $text = "1. Mode otomatis atau manual?\n2. Channel prioritas?\n3. Notifikasi instan?\n4. Offline perlu?\n5. Bahasa microcopy?\n6. Skala tim?";
        $res = $ref->invoke($runner, $text);
        $this->assertNotNull($res);
        $this->assertCount(6, $res);
        $this->assertSame('mq1', $res[0]['id']);
        $this->assertSame([
            ['key' => 'A', 'text' => 'Ya'],
            ['key' => 'B', 'text' => 'Tidak'],
            ['key' => 'C', 'text' => 'Tidak yakin'],
            ['key' => 'D', 'text' => 'Butuh diskusi'],
        ], $res[0]['options']);
    }

    public function test_mcq_text_fallback_requires_minimum(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'buildQuestionsFromText');
        $ref->setAccessible(true);

        $res = $ref->invoke($runner, '1. Hanya satu pertanyaan di sini?');
        $this->assertNull($res);
    }
}
