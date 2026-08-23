<?php

namespace Tests\Feature;

use App\Models\AgentEvent;
use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CP-44 CP-07: Agent Event Protocol v1. */
class AgentEventTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    private string $token;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create(['project_id' => $this->project->id]);

        $result = ProjectApiToken::generate($this->project, 'test');
        $this->token = $result['token'];
        $this->secret = $result['secret'];
    }

    private function postEvent(array $body): \Illuminate\Testing\TestResponse
    {
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$bodyJson, $this->secret);

        return $this->call('POST', '/api/agent/events', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'HTTP_X_TOKEN_SECRET' => $this->secret,
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $bodyJson);
    }

    public function test_ingests_valid_event(): void
    {
        $resp = $this->postEvent([
            'version_id' => $this->version->id,
            'run_id' => 'run-1',
            'event_id' => 'evt-1',
            'event' => 'phase.started',
            'phase_key' => 'fase1_setup',
            'status' => 'running',
            'payload' => ['note' => 'mulai'],
        ]);

        $resp->assertStatus(201)->assertJsonFragment(['event_id' => 'evt-1']);
        $this->assertDatabaseHas('aiplanstudio_project.agent_events', ['event_id' => 'evt-1', 'event' => 'phase.started']);
    }

    public function test_rejects_unknown_event_type(): void
    {
        $this->postEvent([
            'version_id' => $this->version->id,
            'run_id' => 'run-1',
            'event_id' => 'evt-bad',
            'event' => 'file.deleted',
        ])->assertStatus(422);
    }

    public function test_duplicate_event_id_is_idempotent(): void
    {
        $body = [
            'version_id' => $this->version->id,
            'run_id' => 'run-1',
            'event_id' => 'evt-dup',
            'event' => 'heartbeat',
        ];
        $this->postEvent($body)->assertStatus(201);
        $second = $this->postEvent($body);
        $second->assertStatus(202)->assertJsonFragment(['duplicate' => true]);
        $this->assertSame(1, AgentEvent::where('event_id', 'evt-dup')->count());
    }

    public function test_phase_complete_writes_equivalent_agent_event(): void
    {
        // Adapter: phase-complete harus menghasilkan baris agent_events.
        $this->version->update(['phases' => [['key' => 'fase1_setup', 'title' => 'Fase 1', 'tasks' => [], 'prompt' => '']]]);

        $body = [
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'event_id' => 'wh-eq-1',
            'status' => 'done',
            'output' => 'selesai',
        ];
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $ts = (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$bodyJson, $this->secret);

        $resp = $this->call('POST', '/api/webhooks/phase-complete', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'HTTP_X_TOKEN_SECRET' => $this->secret,
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $bodyJson);

        $resp->assertStatus(200);
        $this->assertDatabaseHas('aiplanstudio_project.agent_events', [
            'event_id' => 'wh-eq-1',
            'event' => 'phase.completed',
            'phase_key' => 'fase1_setup',
        ]);
    }

    public function test_feed_requires_session_and_returns_events(): void
    {
        AgentEvent::create([
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'run_id' => 'r',
            'event_id' => 'feed-1',
            'event' => 'agent.started',
        ]);

        $this->getJson("/api/versions/{$this->version->id}/agent-events")->assertStatus(401);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$this->version->id}/agent-events")
            ->assertStatus(200)
            ->assertJsonFragment(['event_id' => 'feed-1']);
    }
}
