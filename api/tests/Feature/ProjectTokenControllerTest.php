<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTokenControllerTest extends TestCase
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
        $this->version = Version::factory()->create(['project_id' => $this->project->id]);
    }

    public function test_auto_tracking_creates_token_on_first_call(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'token', 'secret', 'existing', 'message'])
            ->assertJson(['existing' => false]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEmpty($response->json('secret'));
    }

    public function test_auto_tracking_returns_existing_on_second_call(): void
    {
        $first = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");
        $firstId = $first->json('id');

        $second = $this->actingAs($this->user)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");

        $second->assertStatus(200)
            ->assertJson(['id' => $firstId, 'existing' => true, 'token' => null, 'secret' => null]);
    }

    public function test_auto_tracking_rejects_other_users_project(): void
    {
        $other = User::factory()->create();
        $response = $this->actingAs($other)
            ->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");

        $response->assertStatus(404);
    }

    public function test_auto_tracking_requires_auth(): void
    {
        $response = $this->postJson("/api/projects/{$this->project->id}/versions/{$this->version->id}/tokens/auto-tracking");
        $response->assertStatus(401);
    }
}
