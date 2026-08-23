<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
                [
                    'key' => 'fase1_setup', 'title' => 'Fase 1 Setup',
                    'tasks' => ['setup_repo'],
                    'halaman' => [['key' => 'pg_login', 'title' => 'Login']],
                    'menu' => [['key' => 'mn_main', 'title' => 'Main Menu', 'parent' => '-']],
                    'fitur' => [['key' => 'ft_auth', 'title' => 'Auth', 'func' => '-']],
                    'flow' => [['key' => 'fl_login', 'title' => 'Login Flow', 'steps' => '-']],
                    'api' => [['key' => 'api_login', 'endpoint' => '/login', 'method' => 'POST', 'desc' => '-']],
                    'prompt' => '',
                ],
                ['key' => 'fase2_front', 'title' => 'Fase 2 Front', 'tasks' => [], 'halaman' => [], 'menu' => [], 'fitur' => [], 'flow' => [], 'api' => [], 'prompt' => ''],
                ['key' => 'fase3_backend', 'title' => 'Fase 3 Backend', 'tasks' => [], 'halaman' => [], 'menu' => [], 'fitur' => [], 'flow' => [], 'api' => [], 'prompt' => ''],
                ['key' => 'fase4_feature', 'title' => 'Fase 4 Fitur', 'tasks' => [], 'halaman' => [], 'menu' => [], 'fitur' => [], 'flow' => [], 'api' => [], 'prompt' => ''],
                ['key' => 'fase5_deploy', 'title' => 'Fase 5 Deploy', 'tasks' => [], 'halaman' => [], 'menu' => [], 'fitur' => [], 'flow' => [], 'api' => [], 'prompt' => ''],
            ],
        ]);

        $result = ProjectApiToken::generate($this->project, 'test');
        $this->token = $result['token'];
        $this->secret = $result['secret'];
        $this->tokenSecretHash = hash('sha256', $this->secret);
    }

    private function webhook(array $body, ?string $forcedTimestamp = null): TestResponse
    {
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        // CP-44: timestamp bisa dipaksa agar test replay deterministik (tak lintas detik).
        $timestamp = $forcedTimestamp ?? (string) time();
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

    public function test_webhook_rejects_duplicate_replay(): void
    {
        $body = [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'status' => 'done',
        ];

        // First call succeeds
        $ts = (string) time();
        $first = $this->webhook($body, $ts);
        $first->assertStatus(200);

        // Replay with same timestamp+signature must be rejected
        $second = $this->webhook($body, $ts);
        $second->assertStatus(409)
            ->assertJsonFragment(['message' => 'Webhook duplikat terdeteksi. Permintaan sudah diproses.']);
    }

    public function test_webhook_persists_all_granular_task_types(): void
    {
        // CP-44 CP-03: task_key kini harus anggota fase — pakai key nyata dari fixture.
        $keys = ['halaman' => 'pg_login', 'menu' => 'mn_main', 'fitur' => 'ft_auth', 'flow' => 'fl_login', 'api' => 'api_login'];
        foreach ($keys as $type => $key) {
            $response = $this->webhook([
                'version_id' => $this->version->id,
                'phase_key' => 'fase1_setup',
                'task_key' => $key,
                'task_type' => $type,
                'title' => "Sub item {$type}",
                'status' => 'done',
                'output' => "output {$type}",
            ]);
            $response->assertStatus(200);
            $this->assertDatabaseHas('task_progress', [
                'task_key' => $key,
                'task_type' => $type,
                'status' => 'done',
            ]);
        }
    }

    public function test_webhook_rejects_invalid_task_type(): void
    {
        $response = $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'sub_bad',
            'task_type' => 'bogus',
            'status' => 'done',
        ]);
        $response->assertStatus(422);
    }

    /** CP-44 CP-03: task_key harus milik phase yang dirujuk. */
    public function test_webhook_rejects_task_key_from_other_phase(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase2_front',
            'task_key' => 'pg_login',
            'task_type' => 'halaman',
            'status' => 'done',
        ])->assertStatus(422);
    }

    public function test_webhook_accepts_sub_item_task_key_of_phase(): void
    {
        $resp = $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'pg_login',
            'task_type' => 'halaman',
            'title' => 'Login',
            'status' => 'done',
        ]);
        $resp->assertStatus(200)->assertJsonFragment(['task_key' => 'pg_login']);
    }

    /** CP-44 CP-03: event_id opsional tercatat di metadata Activity. */
    public function test_webhook_records_event_id_in_activity(): void
    {
        $resp = $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'event_id' => 'fase1_setup:done:1700000000',
            'status' => 'done',
        ]);
        $resp->assertStatus(200)->assertJsonFragment(['event_id' => 'fase1_setup:done:1700000000']);

        $activity = Activity::where('project_id', $this->project->id)
            ->where('action', Activity::ACTION_WEBHOOK_RECEIVED)
            ->latest('id')
            ->first();
        $this->assertNotNull($activity);
        $this->assertSame('fase1_setup:done:1700000000', $activity->metadata['event_id'] ?? null);
    }
}
