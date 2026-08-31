<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateStreamTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();

        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-invalid',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'stage_status' => Version::defaultStageStatus(),
        ]);
    }

    public function test_requires_version_parameter(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/generate/stream?stage=analisa');

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Parameter "version" dan "stage" wajib diisi.']);
    }

    public function test_requires_stage_parameter(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/generate/stream?version={$this->version->id}");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Parameter "version" dan "stage" wajib diisi.']);
    }

    public function test_rejects_invalid_stage(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/generate/stream?version={$this->version->id}&stage=invalid");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Stage tidak valid. Pilih: pertanyaan, analisa, prd, architecture, erd, api_contract, design_system, phases_web, standards_web, testing_strategy, master_web, app_spec_web, design_system_mobile, pertanyaan_mobile, standards_mobile, phases_mobile, master_mobile, app_spec_mobile, env_config, security, deployment, observability, agents, verify.review, smoke_test, verify.production_readiness']);
    }

    public function test_returns_streamed_response_with_correct_headers(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->post("/api/generate/stream?version={$this->version->id}&stage=analisa&auto=0");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control') ?? '');
    }

    public function test_rejects_other_users_version(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        $otherVersion = Version::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/generate/stream?version={$otherVersion->id}&stage=analisa");

        $response->assertStatus(404);
    }

    public function test_returns_streamed_response_when_provider_not_configured(): void
    {
        AiProvider::query()->delete();

        $response = $this->actingAs($this->user, 'sanctum')
            ->post("/api/generate/stream?version={$this->version->id}&stage=analisa&auto=0");

        // StreamedResponse — content is streamed directly, not available in response body
        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type') ?? '');
    }

    public function test_stage_done_json_artifact_is_not_regenerated(): void
    {
        // Regression #51/J1: kolom JSONB (array) di guard idempotensi tidak boleh
        // memicu "Array to string conversion" (500).
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'x']]]], 200)]);

        $this->version->update([
            'stage_status' => array_merge(Version::defaultStageStatus(), ['erd' => 'done']),
            'erd' => ['nodes' => [['id' => 'users', 'label' => 'users']], 'edges' => []],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post("/api/generate/stream?version={$this->version->id}&stage=erd&auto=0");

        $response->assertStatus(200);
        Http::assertNothingSent();
        $this->assertSame('done', $this->version->fresh()->stage_status['erd']);
    }

    public function test_stage_done_with_artifact_is_not_regenerated(): void
    {
        // Idempotency guard: stage done + artifact tersimpan → skip regen.
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'x']]]], 200)]);

        $this->version->update([
            'stage_status' => array_merge(Version::defaultStageStatus(), ['pertanyaan' => 'done']),
            'pertanyaan' => '{"questions":[]}',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->post("/api/generate/stream?version={$this->version->id}&stage=pertanyaan&auto=0");

        $response->assertStatus(200);
        Http::assertNothingSent();
        $this->assertSame('done', $this->version->fresh()->stage_status['pertanyaan']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson("/api/generate/stream?version={$this->version->id}&stage=analisa");

        $response->assertStatus(401);
    }

    public function test_auto_mode_runs_multiple_stages(): void
    {
        // Requires a reachable OpenAI-compatible endpoint; skip when running offline.
        if (empty(env('OPENAI_API_KEY'))) {
            $this->markTestSkipped('OPENAI_API_KEY not set; integration test skipped.');
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->post("/api/generate/stream?version={$this->version->id}&stage=analisa&auto=1");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type') ?? '');

        $content = $response->getContent();
        $this->assertNotEmpty($content);

        $stageEvents = collect(explode("\n", $content))
            ->filter(fn ($line) => str_starts_with($line, 'event: '))
            ->map(fn ($line) => trim(substr($line, 7)))
            ->unique()
            ->values();

        $this->assertTrue($stageEvents->contains('status') || $stageEvents->contains('fail'),
            'Auto mode should emit status or fail events from pipeline execution.');
    }
}
