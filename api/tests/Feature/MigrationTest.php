<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_admin(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)
            ->getJson('/api/admin/migrations')
            ->assertStatus(403);
    }

    public function test_returns_applied_and_pending_counts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/api/admin/migrations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'pending',
                'applied_count',
                'pending_count',
                'checked_at',
            ]);

        $body = $response->json();
        $this->assertIsInt($body['applied_count']);
        $this->assertIsInt($body['pending_count']);
        $this->assertGreaterThan(0, $body['applied_count']);
    }
}
