<?php

namespace Tests\Feature;

use Tests\TestCase;

class RequestContextTest extends TestCase
{
    public function test_response_includes_request_id_header(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
    }

    public function test_inbound_request_id_is_echoed_back(): void
    {
        $response = $this->withHeaders(['X-Request-ID' => 'abc-123-test'])->get('/api/health');

        $response->assertStatus(200);
        $this->assertSame('abc-123-test', $response->headers->get('X-Request-ID'));
    }

    public function test_log_context_populated_for_authenticated_request(): void
    {
        \Illuminate\Support\Facades\Log::shouldReceive('withContext')
            ->once()
            ->with(\Mockery::on(function (array $ctx) {
                return isset($ctx['request_id'])
                    && $ctx['method'] === 'GET'
                    && $ctx['route'] === 'api/health'
                    && array_key_exists('user_id', $ctx);
            }));

        $this->get('/api/health');
    }
}
