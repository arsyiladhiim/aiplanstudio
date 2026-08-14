<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiClientSsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_ssrf_blocks_localhost(): void
    {
        $provider = AiProvider::create([
            'name' => 'SSRF Test',
            'base_url' => 'http://localhost:6379',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $client = new AiClient($provider);
        $result = $client->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('internal', $result['message']);
    }

    public function test_ssrf_blocks_docker_hostnames(): void
    {
        $provider = AiProvider::create([
            'name' => 'SSRF Test',
            'base_url' => 'http://db:5432',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $client = new AiClient($provider);
        $result = $client->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('internal', $result['message']);
    }

    public function test_ssrf_blocks_private_ip(): void
    {
        $provider = AiProvider::create([
            'name' => 'SSRF Test',
            'base_url' => 'http://192.168.1.1',
            'api_key' => 'sk-test',
            'model' => 'gpt-4',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);

        $client = new AiClient($provider);
        $result = $client->testConnection();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('internal', $result['message']);
    }
}
