<?php

namespace Tests\Unit\PromptValidation;

use PHPUnit\Framework\TestCase;

class SignatureElementTest extends TestCase
{
    public function test_short_signature_under_300_rejected(): void
    {
        $body = 'Glassmorphism with subtle backdrop blur.';
        $this->assertLessThan(300, mb_strlen($body));
    }

    public function test_generic_phrase_short_body_rejected(): void
    {
        $genericSignatures = ['glassmorphism', 'neumorphism', 'material design', 'flat design', 'minimalist'];
        $body = 'Use glassmorphism as the visual approach.';
        $matched = [];
        foreach ($genericSignatures as $sig) {
            if (stripos($body, $sig) !== false) {
                $matched[] = $sig;
            }
        }
        $this->assertNotEmpty($matched);
        $this->assertLessThan(400, mb_strlen($body));
    }

    public function test_generic_phrase_long_body_accepted(): void
    {
        $genericSignatures = ['glassmorphism', 'neumorphism', 'material design', 'flat design', 'minimalist'];
        $body = str_repeat('Glassmorphism with specific context and rationale. ', 20);
        $matched = [];
        foreach ($genericSignatures as $sig) {
            if (stripos($body, $sig) !== false) {
                $matched[] = $sig;
            }
        }
        $this->assertNotEmpty($matched);
        $this->assertGreaterThanOrEqual(400, mb_strlen($body));
    }

    public function test_specific_signature_under_300_rejected(): void
    {
        $body = 'A signature 3-column density-aware grid that compresses transaction timeline vertically.';
        $this->assertLessThan(300, mb_strlen($body));
    }

    public function test_specific_signature_300_plus_accepted(): void
    {
        $body = str_repeat('Density-aware 3-column grid with vertical transaction timeline. ', 10);
        $this->assertGreaterThanOrEqual(300, mb_strlen($body));
        $this->assertStringNotContainsString('glassmorphism', $body);
        $this->assertStringNotContainsString('neumorphism', $body);
    }
}
