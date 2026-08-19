<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class ValidateSectionOrderingTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    private function orderedArtifact(): string
    {
        return <<<'MD'
# Test Artifact

## 1. First
content

## 2. Second
content

## 3. Third
content

## 4. Fourth
content
MD;
    }

    private function outOfOrderArtifact(): string
    {
        return <<<'MD'
# Test Artifact

## 1. First
content

## 3. Third
content

## 2. Second
content

## 4. Fourth
content
MD;
    }

    private function missingArtifact(): string
    {
        return <<<'MD'
# Test Artifact

## 1. First
content

## 2. Second
content

## 4. Fourth
content
MD;
    }

    public function test_ordered_sections_pass(): void
    {
        $headings = $this->parser->extractMarkdownHeadings($this->orderedArtifact());
        $numbers = [];
        foreach ($headings as $h) {
            if (preg_match('/^##\s+(\d+)\./', $h, $m)) {
                $numbers[] = (int) $m[1];
            }
        }
        $expected = [1, 2, 3, 4];
        $actual = array_values(array_filter($numbers, fn ($n) => in_array($n, $expected, true)));
        $this->assertSame($expected, $actual);
    }

    public function test_out_of_order_sections_detected(): void
    {
        $headings = $this->parser->extractMarkdownHeadings($this->outOfOrderArtifact());
        $numbers = [];
        foreach ($headings as $h) {
            if (preg_match('/^##\s+(\d+)\./', $h, $m)) {
                $numbers[] = (int) $m[1];
            }
        }
        $expected = [1, 2, 3, 4];
        $actual = array_values(array_filter($numbers, fn ($n) => in_array($n, $expected, true)));
        $this->assertNotSame($expected, $actual);
    }

    public function test_missing_section_detected(): void
    {
        $headings = $this->parser->extractMarkdownHeadings($this->missingArtifact());
        $numbers = [];
        foreach ($headings as $h) {
            if (preg_match('/^##\s+(\d+)\./', $h, $m)) {
                $numbers[] = (int) $m[1];
            }
        }
        $expected = [1, 2, 3, 4];
        $actual = array_values(array_filter($numbers, fn ($n) => in_array($n, $expected, true)));
        $this->assertNotSame($expected, $actual);
    }
}
