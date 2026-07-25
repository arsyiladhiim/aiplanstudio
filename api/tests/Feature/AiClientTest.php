<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-key-for-mocking',
            'model' => 'gpt-4o',
        ]);
    }

    public function test_is_configured_returns_true_when_provider_has_key(): void
    {
        $client = new AiClient();
        $this->assertTrue($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_empty(): void
    {
        AiProvider::truncate();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);
        $client = new AiClient();
        $this->assertFalse($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_no_provider(): void
    {
        AiProvider::truncate();
        $client = new AiClient();
        $this->assertFalse($client->isConfigured());
    }

    public function test_test_connection_returns_fail_when_not_configured(): void
    {
        AiProvider::truncate();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);
        $client = new AiClient();
        $result = $client->testConnection();
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('belum', $result['message']);
    }

    public function test_stream_throws_when_not_configured(): void
    {
        AiProvider::truncate();
        AiProvider::create([
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'model' => 'gpt-4o',
        ]);
        $client = new AiClient();

        $this->expectException(\RuntimeException::class);
        $client->stream([['role' => 'user', 'content' => 'Hi']], fn($t) => null);
    }
}
