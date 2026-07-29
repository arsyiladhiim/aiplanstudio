<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    use RefreshDatabase;

    private AiProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test-invalid',
            'model' => 'gpt-4o',
            'provider_type' => 'openai',
            'is_active' => true,
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

    public function test_stream_sends_post_to_provider_endpoint(): void
    {
        Http::fake();

        $client = new AiClient();
        try {
            $client->stream([['role' => 'user', 'content' => 'Hi']], fn($t) => null);
        } catch (\Throwable) {
        }

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'chat/completions')
                && $request->method() === 'POST';
        });
    }

    public function test_stream_parses_sse_response(): void
    {
        Http::fake([
            $this->provider->chatEndpoint() => Http::response(
                "data: " . json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]) . "\n\ndata: [DONE]\n\n",
            ),
            '*' => Http::response('', 500),
        ]);

        $client = new AiClient();
        $output = '';
        $client->stream([['role' => 'user', 'content' => 'Hi']], function (string $delta) use (&$output) {
            $output .= $delta;
        });

        $this->assertSame('Hello', $output);
    }

    public function test_stream_throws_on_http_error(): void
    {
        Http::fake([
            '*' => Http::response('{"error":{"message":"Bad request"}}', 400),
        ]);

        $client = new AiClient();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Bad request');

        $client->stream([['role' => 'user', 'content' => 'Hi']], fn($t) => null);
    }
}
