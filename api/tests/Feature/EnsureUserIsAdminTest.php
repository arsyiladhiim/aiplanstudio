<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserIsAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/activities');

        $response->assertStatus(200);
    }

    public function test_member_cannot_access_admin_routes(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $response = $this->actingAs($member, 'sanctum')
            ->getJson('/api/activities');

        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/activities');

        $response->assertStatus(401);
    }
}
