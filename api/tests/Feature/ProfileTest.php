<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    public function test_show_returns_accent_color(): void
    {
        $user = User::factory()->create(['accent_color' => '#7c3aed']);
        $response = $this->actingAs($user, 'sanctum')->get('/api/settings/profile');

        $response->assertStatus(200)
            ->assertJson(['accent_color' => '#7c3aed']);
    }

    public function test_update_accent_color_hex(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->patch('/api/settings/profile', [
            'accent_color' => '#06b6d4',
        ]);

        $response->assertStatus(200)
            ->assertJson(['accent_color' => '#06b6d4']);
        $this->assertSame('#06b6d4', $user->fresh()->accent_color);
    }

    public function test_update_accent_color_short_hex(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->patch('/api/settings/profile', [
            'accent_color' => '#abc',
        ]);

        $response->assertStatus(200);
        $this->assertSame('#abc', $user->fresh()->accent_color);
    }

    public function test_update_accent_color_can_be_cleared(): void
    {
        $user = User::factory()->create(['accent_color' => '#7c3aed']);
        $response = $this->actingAs($user, 'sanctum')->patch('/api/settings/profile', [
            'accent_color' => null,
        ]);

        $response->assertStatus(200);
        $this->assertNull($user->fresh()->accent_color);
    }

    public function test_update_rejects_invalid_accent_color(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')->patch('/api/settings/profile', [
            'accent_color' => 'not-a-color',
        ]);

        $response->assertStatus(422);
    }
}
