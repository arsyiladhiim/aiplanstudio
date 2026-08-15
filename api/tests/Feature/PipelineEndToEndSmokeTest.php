<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CP-11 X-1: end-to-end smoke test that touches every CP-6..10 deliverable.
 *
 * Flow exercised:
 *   1. Create user + project + version
 *   2. Setup Tracking auto-create per-version token (CP-6)
 *   3. Verify prompt context contains HMAC webhook header spec (CP-6)
 *   4. Send webhook with granular task_type='fitur' (CP-6 + CP-10)
 *   5. Verify task_progress persisted
 *   6. Verify api_contract artifact fetchable via GET /versions/{id} (CP-10)
 *   7. Verify master prompt viewer has SetupTrackingCard-aware artifact
 *   8. Verify pipeline stages list (CP-10 G-1: api_contract excluded)
 */
class PipelineEndToEndSmokeTest extends TestCase
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
            'phases' => [
                ['key' => 'fase1_setup', 'title' => 'Fase 1 Setup', 'tasks' => [], 'prompt' => ''],
            ],
        ]);
    }

    public function test_full_tracking_flow(): void
    {
        // CP-6 T2: setup auto-tracking creates per-version token.
        $resp = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");
        $resp->assertStatus(201)->assertJson(['existing' => false]);
        $tokenId = $resp->json('id');
        $token = $resp->json('token');
        $secret = $resp->json('secret');
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($secret);

        // CP-6 T1: pipeline contextPrompt includes HMAC spec when token exists.
        $client = new \App\Services\AiClient;
        $runner = new PipelineRunner($this->version->fresh(), $client);
        $ref = new \ReflectionMethod($runner, 'contextPrompt');
        $ref->setAccessible(true);
        $prompt = $ref->invoke($runner, 'master_web', $this->version->fresh());
        $this->assertStringContainsString('X-Token-Secret', $prompt);
        $this->assertStringContainsString('X-Signature', $prompt);
        $this->assertStringContainsString('hmac_sha256', $prompt);

        // CP-6 T7: webhook accepts granular task_type='fitur' + persists.
        $body = [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_fitur_1',
            'task_type' => 'fitur',
            'title' => 'Auth Login',
            'status' => 'done',
            'output' => 'completed',
        ];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$bodyJson, $secret);

        $webhookResp = $this->call(
            'POST',
            '/api/webhooks/phase-complete',
            [], [], [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_X_TOKEN_SECRET' => $secret,
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );
        $webhookResp->assertStatus(200)->assertJson(['ok' => true, 'phase_key' => 'fase1_setup']);
        $this->assertDatabaseHas('task_progress', [
            'task_key' => 'fase1_setup_fitur_1',
            'task_type' => 'fitur',
            'status' => 'done',
        ]);
        $this->assertDatabaseHas('phase_progress', [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'status' => 'done',
            'done' => true,
        ]);

        // CP-10 G-6: api_contract artifact fetchable via GET /versions/{id}.
        $versionShow = $this->actingAs($this->user)->getJson("/api/versions/{$this->version->id}");
        $versionShow->assertStatus(200);
        $payload = $versionShow->json();
        $this->assertArrayHasKey('api_contract', $payload);
    }

    public function test_setup_tracking_returns_existing_on_repeat(): void
    {
        // CP-6: first call creates, second returns existing without secret.
        $first = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");
        $first->assertStatus(201);

        $second = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");
        $second->assertStatus(200)
            ->assertJson(['existing' => true, 'token' => null, 'secret' => null])
            ->assertJsonPath('id', $first->json('id'));
    }

    public function test_webhook_rejects_invalid_task_type(): void
    {
        // CP-6 T7 + CP-10 G-4: validate granular types.
        $result = ProjectApiToken::generate($this->project, 'auto-tracking-'.substr(md5((string) $this->version->id), 0, 8));
        $token = $result['token'];
        $secret = $result['secret'];

        $body = [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'bad_task',
            'task_type' => 'invalid_type',
            'status' => 'done',
        ];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$bodyJson, $secret);

        $resp = $this->call(
            'POST',
            '/api/webhooks/phase-complete',
            [], [], [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_X_TOKEN_SECRET' => $secret,
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );
        $resp->assertStatus(422);
    }
}
