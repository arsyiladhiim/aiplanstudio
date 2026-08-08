<?php

namespace Tests\Unit;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_is_admin_returns_true_for_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_user_is_admin_returns_false_for_member_role(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $this->assertFalse($member->isAdmin());
    }

    public function test_ai_provider_masked_key_masks_middle(): void
    {
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-abcdefghijklmnop',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
        ]);

        $masked = $provider->maskedKey();
        $this->assertStringStartsWith('sk-', $masked);
        $this->assertStringEndsWith('mnop', $masked);
        $this->assertStringContainsString('••••••', $masked);
    }

    public function test_ai_provider_masked_key_returns_empty_for_empty_key(): void
    {
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
        ]);

        $this->assertSame('', $provider->maskedKey());
    }

    public function test_ai_provider_auth_headers_openai(): void
    {
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
        ]);

        $headers = $provider->authHeaders();
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertSame('Bearer sk-test', $headers['Authorization']);
    }

    public function test_ai_provider_auth_headers_anthropic(): void
    {
        $provider = AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://api.anthropic.com/v1',
            'api_key' => 'sk-ant-test',
            'model' => 'claude-3-opus',
            'provider_type' => 'anthropic',
        ]);

        $headers = $provider->authHeaders();
        $this->assertArrayHasKey('x-api-key', $headers);
        $this->assertSame('sk-ant-test', $headers['x-api-key']);
        $this->assertArrayHasKey('anthropic-version', $headers);
    }

    public function test_ai_provider_current_returns_active_provider(): void
    {
        AiProvider::create([
            'name' => 'Inactive',
            'base_url' => 'https://example.com',
            'api_key' => 'key1',
            'model' => 'gpt-3',
            'provider_type' => 'openai',
            'is_active' => false,
        ]);

        $active = AiProvider::create([
            'name' => 'Active',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'key2',
            'model' => 'gpt-4',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $current = AiProvider::current();
        $this->assertNotNull($current);
        $this->assertSame($active->id, $current->id);
    }

    public function test_ai_provider_current_returns_null_when_no_active(): void
    {
        AiProvider::create([
            'name' => 'Test',
            'base_url' => 'https://example.com',
            'api_key' => 'key',
            'model' => 'gpt-3',
            'provider_type' => 'openai',
            'is_active' => false,
        ]);

        $this->assertNull(AiProvider::current());
    }

    public function test_project_next_version_no_starts_at_one(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->assertSame(1, $project->nextVersionNo());
    }

    public function test_project_next_version_no_increments(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $project->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        $this->assertSame(2, $project->nextVersionNo());
    }

    public function test_version_default_stage_status_returns_all_stages(): void
    {
        $status = Version::defaultStageStatus();

        $expected = ['pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract', 'phases_web', 'standards_web', 'master_web', 'pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile', 'agents'];
        foreach ($expected as $stage) {
            $this->assertArrayHasKey($stage, $status);
            $this->assertSame('pending', $status[$stage]);
        }
    }

    public function test_ai_provider_chat_endpoint_differs_by_type(): void
    {
        $openai = AiProvider::create([
            'name' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'key',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
        ]);

        $anthropic = AiProvider::create([
            'name' => 'Anthropic',
            'base_url' => 'https://api.anthropic.com',
            'api_key' => 'key',
            'model' => 'claude-3',
            'provider_type' => 'anthropic',
        ]);

        $this->assertStringContainsString('/chat/completions', $openai->chatEndpoint());
        $this->assertStringContainsString('/messages', $anthropic->chatEndpoint());
    }
}
