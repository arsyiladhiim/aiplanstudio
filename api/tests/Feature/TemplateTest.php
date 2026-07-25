<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateTest extends TestCase
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

    public function test_anyone_can_list_templates(): void
    {
        Template::factory()->count(3)->create();

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/templates');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_admin_can_create_template(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/templates', [
                'name' => 'Test Template',
                'target' => 'web',
                'description' => 'A test template description',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'target', 'description']);

        $this->assertDatabaseHas('templates', [
            'name' => 'Test Template',
        ]);
    }

    public function test_member_cannot_create_template(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/templates', [
                'name' => 'Test Template',
                'target' => 'web',
                'description' => 'A test template description',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_template(): void
    {
        $template = Template::factory()->create();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/templates/{$template->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
    }

    public function test_member_cannot_delete_template(): void
    {
        $template = Template::factory()->create();

        $response = $this->actingAs($this->member, 'sanctum')
            ->deleteJson("/api/templates/{$template->id}");

        $response->assertStatus(403);
    }
}
