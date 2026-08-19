<?php

namespace Tests\Unit\PromptValidation;

use PHPUnit\Framework\TestCase;

class ApiContractCoerceTest extends TestCase
{
    private function normalize(array $endpoints): array
    {
        return array_map(function ($item) {
            if (array_key_exists('auth', $item)) {
                if (is_bool($item['auth'])) {
                    $item['auth'] = $item['auth'] ? 'required' : 'none';
                } elseif ($item['auth'] === null) {
                    $item['auth'] = 'none';
                }
            }
            if (isset($item['path']) && ! str_starts_with($item['path'], '/')) {
                $item['path'] = '/'.ltrim($item['path'], '/');
            }

            return $item;
        }, $endpoints);
    }

    public function test_bool_auth_coerced_to_string(): void
    {
        $normalized = $this->normalize([
            ['resource' => 'u', 'method' => 'GET', 'path' => '/users', 'auth' => true, 'description' => 'x'],
            ['resource' => 'u', 'method' => 'GET', 'path' => '/public', 'auth' => false, 'description' => 'x'],
        ]);
        $this->assertSame('required', $normalized[0]['auth']);
        $this->assertSame('none', $normalized[1]['auth']);
    }

    public function test_null_auth_coerced(): void
    {
        $normalized = $this->normalize([
            ['resource' => 'u', 'method' => 'GET', 'path' => '/users', 'auth' => null, 'description' => 'x'],
        ]);
        $this->assertSame('none', $normalized[0]['auth']);
    }

    public function test_path_prefix_added(): void
    {
        $normalized = $this->normalize([
            ['resource' => 'u', 'method' => 'GET', 'path' => 'users', 'auth' => 'session', 'description' => 'x'],
        ]);
        $this->assertSame('/users', $normalized[0]['path']);
    }

    public function test_prd_bullet_characters(): void
    {
        $body = "• Integrasi POS lokal\n* Offline-first sync\n- Pricing transparan";
        $bullets = preg_match_all('/^\s*[-*•]\s+/mu', $body);
        $this->assertGreaterThanOrEqual(3, $bullets);
    }
}