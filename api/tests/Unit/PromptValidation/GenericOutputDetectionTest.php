<?php

namespace Tests\Unit\PromptValidation;

use App\Services\PipelineRunner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GenericOutputDetectionTest extends TestCase
{
    private const PATTERNS = [
        '/lorem ipsum/i',
        '/in today\'s digital age/i',
        '/leverage cutting[- ]edge/i',
        '/revolutionary (platform|solution|app)/i',
        '/game[- ]changer approach/i',
        '/blue[- ]purple gradient/i',
        '/Inter as (the )?default font/i',
        '/modern (clean|minimal) (UI|interface)/i',
        '/robust (and|&)? scalable/i',
        '/seamless(ly)? (integrated|experience)/i',
    ];

    private function matchesAny(string $content): ?string
    {
        foreach (self::PATTERNS as $p) {
            if (preg_match($p, $content)) {
                return $p;
            }
        }

        return null;
    }

    public function test_patterns_constant_populated(): void
    {
        $ref = new ReflectionClass(PipelineRunner::class);
        $this->assertTrue($ref->hasConstant('GENERIC_PATTERNS'));
        $c = $ref->getConstant('GENERIC_PATTERNS');
        $this->assertCount(10, $c);
    }

    public function test_valid_original_output_passes(): void
    {
        $content = 'Aplikasi kasir spesifik untuk warung tradisional Indonesia dengan stok harian dan laporan PDF.';
        $this->assertNull($this->matchesAny($content));
    }

    public function test_lorem_ipsum_detected(): void
    {
        $content = 'Lorem ipsum dolor sit amet consectetur adipiscing elit.';
        $this->assertSame('/lorem ipsum/i', $this->matchesAny($content));
    }

    public function test_blue_purple_gradient_detected(): void
    {
        $content = 'Use blue-purple gradient as the primary brand visual.';
        $this->assertSame('/blue[- ]purple gradient/i', $this->matchesAny($content));
    }

    public function test_leverage_cutting_edge_detected(): void
    {
        $content = 'We leverage cutting-edge AI to deliver robust and scalable solutions.';
        $matches = array_filter([
            $this->matchesAny('We leverage cutting-edge AI'),
            $this->matchesAny('robust and scalable solutions'),
        ]);
        $this->assertNotEmpty($matches);
    }
}
