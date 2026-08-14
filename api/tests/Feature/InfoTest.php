<?php

namespace Tests\Feature;

use Tests\TestCase;

class InfoTest extends TestCase
{
    public function test_version_endpoint_returns_expected_keys(): void
    {
        $response = $this->get('/api/version');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'version',
                'name',
            ])
            ->assertJsonMissing(['environment', 'commit', 'php', 'laravel', 'updated_at']);
    }

    public function test_version_endpoint_does_not_require_auth(): void
    {
        $response = $this->get('/api/version');
        $response->assertStatus(200);
    }

    public function test_version_reads_from_composer_extra(): void
    {
        $response = $this->get('/api/version');
        $body = $response->json();

        $this->assertNotEmpty($body['version']);
        $this->assertSame('laravel/laravel', $body['name']);
    }
}
