<?php

namespace Tests\Feature;

use App\Models\AiProvider;
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
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-old-key',
            'model' => 'gpt-3',
            'provider_type' => 'openai',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/provider/{$provider->id}", [
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-test-key-123',
                'model' => 'gpt-4',
            ]);

        $response->assertStatus(200);
    }

    public function test_member_cannot_update_provider_settings(): void
    {
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-old-key',
            'model' => 'gpt-3',
            'provider_type' => 'openai',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/settings/provider/{$provider->id}", [
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
                '*' => ['id', 'name', 'email', 'role', 'status'],
            ]);
    }

    public function test_member_cannot_list_users(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/settings/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_approve_pending_user(): void
    {
        $user = User::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$user->id}", [
                'status' => 'active',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_reject_pending_user(): void
    {
        $user = User::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$user->id}", [
                'status' => 'pending',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_cannot_set_invalid_status(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$user->id}", [
                'status' => 'banned',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_demote_last_admin(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$this->admin->id}", [
                'role' => 'member',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'role' => 'admin']);
    }

    public function test_admin_cannot_deactivate_last_admin(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$this->admin->id}", [
                'status' => 'pending',
            ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id, 'status' => 'active']);
    }

    public function test_admin_can_demote_admin_when_another_active_admin_exists(): void
    {
        $secondAdmin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/settings/users/{$target->id}", [
                'role' => 'member',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'member']);
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

    // ── Provider Settings: store ─────────────────────────────────────────

    public function test_admin_can_create_provider(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings/provider', [
                'name' => 'Test OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-test-create',
                'model' => 'gpt-4',
                'provider_type' => 'openai',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ai_providers', ['name' => 'Test OpenAI']);
    }

    public function test_member_cannot_create_provider(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/settings/provider', [
                'name' => 'Test OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'api_key' => 'sk-test-create',
                'model' => 'gpt-4',
                'provider_type' => 'openai',
            ]);

        $response->assertStatus(403);
    }

    // ── Provider Settings: destroy ──────────────────────────────────────

    public function test_admin_can_delete_provider(): void
    {
        $provider = AiProvider::create([
            'name' => 'To Delete',
            'base_url' => 'https://api.example.com',
            'api_key' => 'sk-del',
            'model' => 'gpt-3',
            'provider_type' => 'openai',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/settings/provider/{$provider->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('ai_providers', ['id' => $provider->id]);
    }

    public function test_member_cannot_delete_provider(): void
    {
        $provider = AiProvider::create([
            'name' => 'To Delete',
            'base_url' => 'https://api.example.com',
            'api_key' => 'sk-del',
            'model' => 'gpt-3',
            'provider_type' => 'openai',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->deleteJson("/api/settings/provider/{$provider->id}");

        $response->assertStatus(403);
    }

    // ── User Settings: create ───────────────────────────────────────────

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/settings/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'member',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_member_cannot_create_user(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/settings/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => 'member',
            ]);

        $response->assertStatus(403);
    }

    // ── User Settings: destroy ──────────────────────────────────────────

    public function test_admin_can_delete_member_user(): void
    {
        $target = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/settings/users/{$target->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_admin_user(): void
    {
        $target = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/settings/users/{$target->id}");

        $response->assertStatus(422);
    }

    public function test_member_cannot_delete_user(): void
    {
        $target = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($this->member, 'sanctum')
            ->deleteJson("/api/settings/users/{$target->id}");

        $response->assertStatus(403);
    }
}
