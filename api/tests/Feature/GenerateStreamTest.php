<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertJson(['message' => 'Stage tidak valid. Pilih: pertanyaan, analisa, prd, architecture, erd, api_contract, phases_web, standards_web, master_web, pertanyaan_mobile, phases_mobile, standards_mobile, master_mobile, agents']);
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

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson("/api/generate/stream?version={$this->version->id}&stage=analisa");

        $response->assertStatus(401);
    }
}
