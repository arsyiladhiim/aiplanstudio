<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_requires_admin(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->getJson('/api/admin/health')
            ->assertStatus(403);
    }

    public function test_health_returns_ok_when_all_checks_pass(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AiProvider::create([
            'name' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test1234abcd',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'checks' => ['db', 'redis', 'ai_provider', 'storage'],
                'checked_at',
            ]);

        $body = $response->json();
        $this->assertContains($body['status'], ['ok', 'degraded']);
    }

    public function test_health_reports_no_active_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/admin/health');
        $body = $response->json();

        $this->assertFalse($body['checks']['ai_provider']['ok']);
        $this->assertSame('No active provider', $body['checks']['ai_provider']['error']);
    }
}
