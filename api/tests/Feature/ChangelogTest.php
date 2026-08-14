<?php

namespace Tests\Feature;

use App\Http\Controllers\ChangelogController;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    public function test_changelog_endpoint_returns_expected_shape(): void
    {
        $response = $this->get('/api/changelog');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['version', 'date', 'highlights'],
                ],
            ]);
    }

    public function test_changelog_endpoint_does_not_require_auth(): void
    {
        $response = $this->get('/api/changelog');
        $response->assertStatus(200);
    }

    public function test_changelog_entries_are_non_empty(): void
    {
        $response = $this->get('/api/changelog');
        $body = $response->json();

        $this->assertNotEmpty($body['data']);
        foreach ($body['data'] as $entry) {
            $this->assertNotEmpty($entry['version']);
            $this->assertNotEmpty($entry['date']);
            $this->assertNotEmpty($entry['highlights']);
        }
    }

    public function test_entries_helper_returns_static_array(): void
    {
        $entries = ChangelogController::entries();

        $this->assertIsArray($entries);
        $this->assertGreaterThanOrEqual(2, count($entries));
        $this->assertSame('0.2.0', $entries[0]['version']);
    }
}
