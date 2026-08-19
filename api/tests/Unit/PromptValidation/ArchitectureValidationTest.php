<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class ArchitectureValidationTest extends TestCase
{
    private function validArchitecture(): string
    {
        return <<<'MD'
# Architecture: DemoApp

## 1. Stack (with reasoning)
Backend: Laravel 13. Frontend: Next.js + TypeScript.

## 2. Module Boundaries
```
┌───────────────┐
│   Browser     │
└───────┬───────┘
        │ cookie
┌───────▼───────┐
│  Laravel API  │
└───────┬───────┘
        │ Eloquent
┌───────▼───────┐
│  PostgreSQL   │
└───────────────┘
```
Prinsip: backend stateless, DB constraints > app validation.

## 3. Data Flow
1. Browser POST → /api/projects
2. FormRequest validate → controller

## 4. Folder Structure
api/app, api/routes, web/src

## 5. Deployment Topology
Single VPS, Docker Compose, Cloudflare Tunnel.

## 6. Trade-offs
| Decision | Alternative | Why we chose this |
|----------|-------------|-------------------|
| Sanctum SPA | JWT Bearer | HttpOnly cookie aman |
| Direct (no BFF) | BFF proxy | Latensi minimal |
| Single VPS | Kubernetes | Biaya rendah |
| PostgreSQL | MySQL | JSONB + concurrency |
MD;
    }

    public function test_valid_architecture_has_ascii_diagram(): void
    {
        $content = $this->validArchitecture();
        $sections = preg_split("/(?=^##\s)/m", $content);
        $ascii = false;
        foreach ($sections as $sec) {
            if (preg_match('/Module Boundaries/i', $sec)) {
                $ascii = preg_match('/[│├└┌┐┘─┬┴┼]/u', $sec) === 1;

                break;
            }
        }
        $this->assertTrue($ascii, 'Module Boundaries wajib punya ASCII diagram');
    }

    public function test_valid_architecture_has_tradeoff_table(): void
    {
        $content = $this->validArchitecture();
        $sections = preg_split("/(?=^##\s)/m", $content);
        $tradeoff = '';
        foreach ($sections as $sec) {
            if (preg_match('/Trade-?offs?/i', $sec)) {
                $tradeoff = $sec;
            }
        }
        $rows = preg_match_all('/^\s*\|.*\|/m', $tradeoff);
        $this->assertGreaterThanOrEqual(4, $rows, 'Trade-offs wajib ≥4 baris tabel');
    }

    public function test_missing_ascii_detected(): void
    {
        $content = str_replace("```\n┌───────────────┐\n│   Browser     │\n└───────┬───────┘\n        │ cookie\n┌───────▼───────┐\n│  Laravel API  │\n└───────┬───────┘\n        │ Eloquent\n┌───────▼───────┐\n│  PostgreSQL   │\n└───────────────┘\n```", '', $this->validArchitecture());
        $this->assertStringNotContainsString('│', $content);
    }

    public function test_placeholder_unfilled_detected(): void
    {
        $content = str_replace('<NAMA_PROYEK>', '<NAMA_PROYEK>', $this->validArchitecture());
        // inject placeholder ke stack section
        $content = str_replace('Backend: Laravel 13.', 'Backend: <STACK_PLACEHOLDER>.', $content);
        $placeholders = preg_match_all('/<[A-Z][A-Z0-9_]*>/', $content);
        $this->assertGreaterThan(0, $placeholders);
    }
}