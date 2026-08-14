<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSettingsTest extends TestCase
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

    public function test_set_active_provider(): void
    {
        $other = AiProvider::create([
            'name' => 'Other',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-other',
            'model' => 'gpt-4',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $provider = AiProvider::create([
            'name' => 'Active Target',
            'base_url' => 'https://api.anthropic.com',
            'api_key' => 'sk-ant',
            'model' => 'claude-3',
            'provider_type' => 'anthropic',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/settings/provider/{$provider->id}/set-active");

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_providers', [
            'id' => $provider->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('ai_providers', [
            'id' => $other->id,
            'is_active' => false,
        ]);
    }

    public function test_member_cannot_set_active(): void
    {
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'provider_type' => 'openai',
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/settings/provider/{$provider->id}/set-active");

        $response->assertStatus(403);
    }
}
