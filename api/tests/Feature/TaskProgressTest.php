<?php

namespace Tests\Feature;

use App\Models\PhaseProgress;
use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\TaskProgress;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TaskProgressTest extends TestCase
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
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'phases' => [
                // CP-44 CP-03: sub-item nyata agar task_key lolos validasi keanggotaan fase.
                [
                    'key' => 'fase1_setup', 'title' => 'Fase 1 Setup',
                    'tasks' => [],
                    'halaman' => [['key' => 'fase1_setup_halaman_1', 'title' => 'Halaman 1'], ['key' => 'fase1_setup_halaman_2', 'title' => 'Settings']],
                    'fitur' => [['key' => 'fase1_setup_fitur_1', 'title' => 'Fitur 1', 'func' => '-']],
                    'menu' => [['key' => 'fase1_setup_menu_1', 'title' => 'Menu 1', 'parent' => '-']],
                    'flow' => [['key' => 'fase1_setup_flow_1', 'title' => 'Flow 1', 'steps' => '-']],
                    'api' => [['key' => 'fase1_setup_api_1', 'endpoint' => '/x', 'method' => 'GET', 'desc' => '-']],
                    'prompt' => '',
                ],
            ],
        ]);
        $result = ProjectApiToken::generate($this->project, 'test');
        $this->token = $result['token'];
        $this->secret = $result['secret'];
    }

    private function webhook(array $body): TestResponse
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

    public function test_webhook_creates_task_progress(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_halaman_1',
            'task_type' => 'halaman',
            'title' => 'Dashboard Page',
            'status' => 'done',
            'output' => 'rendered',
        ])->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'phase_key' => 'fase1_setup',
                'task_key' => 'fase1_setup_halaman_1',
                'status' => 'done',
            ]);

        $this->assertDatabaseHas('task_progress', [
            'task_key' => 'fase1_setup_halaman_1',
            'task_type' => 'halaman',
            'title' => 'Dashboard Page',
            'status' => 'done',
        ]);
    }

    public function test_webhook_updates_existing_task_progress(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_fitur_1',
            'task_type' => 'fitur',
            'title' => 'Auth Login',
            'status' => 'running',
        ])->assertStatus(200);

        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_fitur_1',
            'task_type' => 'fitur',
            'title' => 'Auth Login Updated',
            'status' => 'done',
            'output' => 'completed',
        ])->assertStatus(200);

        $this->assertDatabaseHas('task_progress', [
            'task_key' => 'fase1_setup_fitur_1',
            'status' => 'done',
            'output' => 'completed',
        ]);

        $count = TaskProgress::where('task_key', 'fase1_setup_fitur_1')->count();
        $this->assertEquals(1, $count, 'Task progress should be upserted, not duplicated');
    }

    public function test_webhook_without_task_key_ignores_task_progress(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'status' => 'done',
        ])->assertStatus(200)
            ->assertJsonMissing(['task_key']);

        $this->assertDatabaseMissing('task_progress', [
            'phase_progress_id' => optional(PhaseProgress::where('version_id', $this->version->id)->first())->id,
        ]);
    }

    public function test_toggle_task_done(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_flow_1',
            'task_type' => 'flow',
            'title' => 'User Registration Flow',
            'status' => 'running',
        ])->assertStatus(200);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$this->version->id}/tasks/fase1_setup_flow_1", [
                'done' => true,
            ])
            ->assertStatus(200)
            ->assertJson(['status' => 'done']);

        $this->assertDatabaseHas('task_progress', [
            'task_key' => 'fase1_setup_flow_1',
            'status' => 'done',
        ]);
    }

    public function test_toggle_task_undone(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_api_1',
            'task_type' => 'api',
            'title' => 'GET /users',
            'status' => 'done',
        ])->assertStatus(200);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$this->version->id}/tasks/fase1_setup_api_1", [
                'done' => false,
            ])
            ->assertStatus(200)
            ->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('task_progress', [
            'task_key' => 'fase1_setup_api_1',
            'status' => 'pending',
        ]);
    }

    public function test_toggle_task_404_for_unknown_task(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/versions/{$this->version->id}/tasks/nonexistent_task", [
                'done' => true,
            ])
            ->assertStatus(404);
    }

    public function test_toggle_task_unauthorized_for_other_user(): void
    {
        $other = User::factory()->create();

        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_menu_1',
            'task_type' => 'menu',
            'title' => 'Sidebar Menu',
            'status' => 'running',
        ])->assertStatus(200);

        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/versions/{$this->version->id}/tasks/fase1_setup_menu_1", [
                'done' => true,
            ])
            ->assertStatus(404);
    }

    public function test_task_progress_cascade_delete_with_phase_progress(): void
    {
        $this->webhook([
            'version_id' => $this->version->id,
            'phase_key' => 'fase1_setup',
            'task_key' => 'fase1_setup_halaman_2',
            'task_type' => 'halaman',
            'title' => 'Settings Page',
            'status' => 'done',
        ])->assertStatus(200);

        $progress = PhaseProgress::where('version_id', $this->version->id)->first();
        $taskId = TaskProgress::where('phase_progress_id', $progress->id)->first()->id;

        $progress->delete();

        $this->assertDatabaseMissing('task_progress', ['id' => $taskId]);
    }
}
