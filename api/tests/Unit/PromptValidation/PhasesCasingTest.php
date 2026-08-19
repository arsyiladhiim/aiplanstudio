<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class PhasesCasingTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    public function test_mixed_case_phase_tokens_parsed(): void
    {
        $content = "FASE: p1 | Fase Satu\nTASK: buat login\nHALAMAN: home | Home | landing\n---\nfase: p2 | Fase Dua\ntask: buat api\n---";
        $phases = $this->parser->parsePhasesText($content);
        $this->assertNotNull($phases);
        $this->assertGreaterThanOrEqual(2, count($phases));
    }

    public function test_upper_tokens_parsed(): void
    {
        $content = "FASE: p1 | Satu\nAPI: u | /users | GET | list\n---";
        $phases = $this->parser->parsePhasesText($content);
        $this->assertNotNull($phases);
        $this->assertCount(1, $phases);
        $this->assertSame('u', ($phases[0]['api'][0] ?? [])['key'] ?? null);
    }
}