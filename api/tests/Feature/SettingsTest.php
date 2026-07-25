<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->member = User::factory()->create(['role' => 'member']);
    }

    public function test_admin_can_view_provider_settings(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/settings/provider');

        $response->assertStatus(200);
        // Response may be null or have provider data
        if ($response->json()) {
            $response->assertJsonStructure(['base_url', 'model', 'api_key_masked']);
        }
    }

    public function test_member_cannot_view_provider_settings(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/settings/provider');

        $response->assertStatus(403);
    }

    public function test_admin_can_update_provider_settings(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/settings/provider', [
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-test-key-123',
                'model' => 'gpt-4',
            ]);

        $response->assertStatus(200);
    }

    public function test_member_cannot_update_provider_settings(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->putJson('/api/settings/provider', [
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-test-key-123',
                'model' => 'gpt-4',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/settings/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'email', 'role']
            ]);
    }

    public function test_member_cannot_list_users(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/settings/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user_role(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$user->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    public function test_member_cannot_update_user_role(): void
    {
        $user = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/settings/users/{$user->id}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(403);
    }
}
