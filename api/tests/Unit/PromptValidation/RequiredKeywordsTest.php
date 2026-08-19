<?php

namespace Tests\Unit\PromptValidation;

use PHPUnit\Framework\TestCase;

class RequiredKeywordsTest extends TestCase
{
    private const KEYWORDS = [
        'design_system'        => ['signature', 'anti-pattern', 'token'],
        'design_system_mobile' => ['Material', 'ThemeData', 'signature'],
        'prd'                  => ['user story', 'acceptance', 'functional requirement'],
        'architecture'         => ['folder structure', 'tech stack', 'pattern'],
        'standards_web'        => ['TypeScript', 'lint', 'convention'],
        'standards_mobile'     => ['Dart', 'lint', 'convention'],
        'security'             => ['OWASP', 'authentication', 'authorization'],
        'observability'        => ['logging', 'monitoring', 'health check'],
        'env_config'           => ['environment', 'variable', 'configuration'],
        'deployment'           => ['Docker', 'rollback'],
        'agents'               => ['AGENTS.md', 'agent', 'instruction'],
    ];

    private function containsAll(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (mb_stripos($haystack, $n) === false) {
                return false;
            }
        }

        return true;
    }

    public function test_all_keywords_present_passes(): void
    {
        $content = 'This document covers signature element and anti-pattern checklist with token system.';
        $this->assertTrue($this->containsAll($content, self::KEYWORDS['design_system']));
    }

    public function test_missing_keyword_detected(): void
    {
        $content = 'This covers signature element and token system.';
        $missing = array_filter(self::KEYWORDS['design_system'], fn ($k) => mb_stripos($content, $k) === false);
        $this->assertContains('anti-pattern', array_values($missing));
    }

    public function test_case_insensitive_match(): void
    {
        $content = 'OWASP top 10 with Authentication and Authorization rules.';
        $this->assertTrue($this->containsAll($content, self::KEYWORDS['security']));
    }
}
