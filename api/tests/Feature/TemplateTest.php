<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Models\Version;
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

    public function test_member_can_instantiate_template_with_seed(): void
    {
        $template = Template::factory()->create([
            'name' => 'SaaS Boilerplate',
            'target' => 'web',
            'seed' => ['idea' => 'SaaS multi-tenant dengan billing.'],
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/templates/{$template->id}/instantiate", []);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'SaaS Boilerplate')
            ->assertJsonPath('idea', 'SaaS multi-tenant dengan billing.')
            ->assertJsonPath('target', 'web');

        $project = Project::firstOrFail();
        $this->assertSame($this->member->id, $project->user_id);
        $this->assertDatabaseHas('versions', ['project_id' => $project->id, 'version_no' => 1]);
        $this->assertDatabaseHas('activities', ['project_id' => $project->id, 'user_id' => $this->member->id]);
    }

    public function test_instantiate_allows_override_of_title_idea_target_stack(): void
    {
        $template = Template::factory()->create([
            'name' => 'Default Name',
            'target' => 'web',
            'seed' => ['idea' => 'Default idea.'],
        ]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/templates/{$template->id}/instantiate", [
                'title' => 'Custom Title',
                'idea' => 'Custom idea.',
                'target' => 'both',
                'stack' => 'Laravel+Next',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Custom Title')
            ->assertJsonPath('idea', 'Custom idea.')
            ->assertJsonPath('target', 'both')
            ->assertJsonPath('stack', 'Laravel+Next');
    }

    public function test_instantiate_fails_when_seed_has_no_idea_and_no_override(): void
    {
        $template = Template::factory()->create(['seed' => null]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson("/api/templates/{$template->id}/instantiate", []);

        $response->assertStatus(422);
    }

    public function test_instantiate_returns_404_for_missing_template(): void
    {
        $response = $this->actingAs($this->member, 'sanctum')
            ->postJson('/api/templates/9999/instantiate', []);

        $response->assertStatus(404);
    }

    public function test_instantiate_requires_authentication(): void
    {
        $template = Template::factory()->create(['seed' => ['idea' => 'x']]);

        $response = $this->postJson("/api/templates/{$template->id}/instantiate", []);

        $response->assertStatus(401);
    }

    public function test_admin_can_update_template(): void
    {
        $template = Template::factory()->create([
            'name' => 'Original',
            'description' => 'Original desc',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/templates/{$template->id}", [
                'name' => 'Updated',
                'description' => 'Updated desc',
                'target' => 'both',
                'seed' => ['idea' => 'New idea.'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Updated')
            ->assertJsonPath('description', 'Updated desc')
            ->assertJsonPath('target', 'both');

        $this->assertDatabaseHas('templates', ['id' => $template->id, 'name' => 'Updated']);
    }

    public function test_member_cannot_update_template(): void
    {
        $template = Template::factory()->create();

        $response = $this->actingAs($this->member, 'sanctum')
            ->patchJson("/api/templates/{$template->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_update_returns_404_for_missing_template(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson('/api/templates/9999', ['name' => 'x']);

        $response->assertStatus(404);
    }

    public function test_index_scopes_user_templates_plus_global(): void
    {
        Template::factory()->create(['name' => 'Global', 'user_id' => null]);
        Template::factory()->create(['name' => 'Mine', 'user_id' => $this->member->id]);
        Template::factory()->create(['name' => 'Other', 'user_id' => $this->admin->id]);

        $response = $this->actingAs($this->member, 'sanctum')
            ->getJson('/api/templates');

        $response->assertStatus(200);
        $names = collect($response->json())->pluck('name')->all();
        $this->assertContains('Global', $names);
        $this->assertContains('Mine', $names);
        $this->assertNotContains('Other', $names);
    }
}
