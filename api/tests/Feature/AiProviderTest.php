<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_masked_key_masks_middle_characters(): void
    {
        $provider = AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-key-12345678',
            'model' => 'gpt-4o',
        ]);

        $masked = $provider->maskedKey();
        $this->assertStringStartsWith('sk-', $masked);
        $this->assertStringEndsWith('5678', $masked);
        $this->assertStringContainsString('••••••', $masked);
        $this->assertStringNotContainsString('test-key', $masked);
    }

    public function test_masked_key_returns_dots_for_short_key(): void
    {
        $provider = AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'short',
            'model' => 'gpt-4o',
        ]);

        $this->assertSame('•••••', $provider->maskedKey());
    }

    public function test_masked_key_returns_empty_for_empty_key(): void
    {
        $provider = AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);

        $this->assertSame('', $provider->maskedKey());
    }

    public function test_provider_settings_response_includes_masked_key(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-secret-key-9999',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/settings/provider');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('api_key_masked', $data[0]);
        $this->assertStringNotContainsString('secret-key', $data[0]['api_key_masked']);
    }

    public function test_prompt_files_exist_for_all_stages(): void
    {
        $stages = ['analisa', 'prd', 'architecture', 'erd', 'api_contract', 'phases', 'standards', 'master', 'agents', 'pertanyaan', 'pertanyaan_mobile', 'phases_mobile'];
        foreach ($stages as $stage) {
            $path = __DIR__ . "/../../app/Prompts/{$stage}.php";
            $this->assertFileExists($path, "Prompt file untuk stage {$stage} tidak ditemukan");
        }
        $this->assertFileExists(__DIR__ . "/../../app/Prompts/helpers.php");
    }
}
