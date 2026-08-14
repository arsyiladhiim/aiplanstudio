<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    private string $token;

    private string $secret;

    private string $tokenSecretHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'phases' => [
                ['key' => 'fase1_setup', 'title' => 'Fase 1 Setup', 'tasks' => [], 'prompt' => ''],
                ['key' => 'fase2_front', 'title' => 'Fase 2 Front', 'tasks' => [], 'prompt' => ''],
                ['key' => 'fase3_backend', 'title' => 'Fase 3 Backend', 'tasks' => [], 'prompt' => ''],
                ['key' => 'fase4_feature', 'title' => 'Fase 4 Fitur', 'tasks' => [], 'prompt' => ''],
                ['key' => 'fase5_deploy', 'title' => 'Fase 5 Deploy', 'tasks' => [], 'prompt' => ''],
            ],
        ]);

        $result = \App\Models\ProjectApiToken::generate($this->project, 'test');
        $this->token = $result['token'];
        $this->secret = $result['secret'];
        $this->tokenSecretHash = hash('sha256', $this->secret);
    }

    private function webhook(array $body): \Illuminate\Testing\TestResponse
    {
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$bodyJson, $this->secret);

        return $this->call(
            'POST',
            '/api/webhooks/phase-complete',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
                'HTTP_X_TOKEN_SECRET' => $this->secret,
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );
    }

    public function test_webhook_accepts_real_phase_key(): void
    {
        $response = $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'status' => 'done',
            'output' => 'ok',
        ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'phase_key' => 'fase1_setup']);
        $this->assertDatabaseHas('phase_progress', [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'status' => 'done',
            'done' => true,
        ]);
    }

    public function test_webhook_maps_phase_1_to_real_key(): void
    {
        $response = $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'phase-2',
            'status' => 'running',
            'output' => 'mulai',
        ]);

        $response->assertStatus(200)
            ->assertJson(['ok' => true, 'phase_key' => 'fase2_front']);
        $this->assertDatabaseHas('phase_progress', [
            'version_id' => $this->version->id,
            'phase_key' => 'fase2_front',
            'status' => 'running',
        ]);
    }

    public function test_webhook_rejects_unknown_phase_key(): void
    {
        $response = $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'phase-x',
            'status' => 'done',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Phase key tidak valid. Gunakan salah satu: fase1_setup, fase2_front, fase3_backend, fase4_feature, fase5_deploy (atau phase-1..phase-5).']);
    }

    public function test_webhook_rejects_invalid_token(): void
    {
        $body = [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
        ];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        $response = $this->call(
            'POST',
            '/api/webhooks/phase-complete',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
                'HTTP_X_TOKEN_SECRET' => $this->secret,
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => 'a'.str_repeat('b', 63),
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );

        $response->assertStatus(401);
    }

    public function test_webhook_rejects_missing_token_secret_header(): void
    {
        $body = ['version_id' => $this->version->id, 'phase_key' => 'fase1_setup'];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$bodyJson, $this->secret);

        $response = $this->call(
            'POST',
            '/api/webhooks/phase-complete',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );

        $response->assertStatus(401)
            ->assertJsonFragment(['message' => 'Header X-Token-Secret wajib diisi untuk route webhook.']);
    }
}