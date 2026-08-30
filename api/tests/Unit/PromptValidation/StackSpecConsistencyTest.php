<?php

namespace Tests\Unit\PromptValidation;

use App\Support\StackSpec;
use PHPUnit\Framework\TestCase;

/**
 * Mengunci versi stack output prompt: PHP 8.4, Laravel 13, Next.js 16,
 * PostgreSQL 18, Node 24 — dicek lintas file prompt + context builder.
 */
class StackSpecConsistencyTest extends TestCase
{
    public function test_stack_spec_values(): void
    {
        $this->assertSame('8.4', StackSpec::PHP);
        $this->assertStringContainsString('Laravel 13', StackSpec::web());
        $this->assertStringContainsString('Next.js 16', StackSpec::web());
        $this->assertStringContainsString('PostgreSQL 18', StackSpec::web());
        $this->assertStringContainsString('PHP 8.4', StackSpec::web());
    }

    public function test_helpers_use_stack_spec(): void
    {
        require_once __DIR__.'/../../../app/Prompts/helpers.php';

        $this->assertStringContainsString(StackSpec::POSTGRES, platformSuffix('web'));
        $this->assertStringContainsString(StackSpec::POSTGRES, platformSuffix('both'));
        $this->assertStringContainsString('Laravel 13', techStackShort('both'));
        $this->assertStringContainsString('PostgreSQL 18', techStackShort('both'));
    }

    public function test_no_stale_versions_in_prompts_and_context(): void
    {
        $dir = __DIR__.'/../../../app/Prompts';
        $files = glob($dir.'/*.php');
        $files[] = __DIR__.'/../../../app/Services/StageContextBuilder.php';

        foreach ($files as $f) {
            $src = file_get_contents($f);
            $this->assertStringNotContainsString('Laravel 11', $src, basename($f));
            $this->assertStringNotContainsString('Laravel 12', $src, basename($f));
            $this->assertStringNotContainsString('Next.js 14', $src, basename($f));
            $this->assertStringNotContainsString('Next.js 15', $src, basename($f));
            $this->assertStringNotContainsString('PostgreSQL 16', $src, basename($f));
            $this->assertStringNotContainsString('PHP 8.3 ', $src, basename($f));
        }
    }
}
