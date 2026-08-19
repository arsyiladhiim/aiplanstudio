<?php

namespace Tests\Unit\PromptValidation;

use PHPUnit\Framework\TestCase;

class PrdDifferentiationTest extends TestCase
{
    private const GENERIC_PATTERNS = [
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

    private function section(string $body): array
    {
        $bodyLen = mb_strlen($body);
        $bullets = preg_match_all('/^\s*-\s+/m', $body);
        $hasGeneric = null;
        foreach (self::GENERIC_PATTERNS as $p) {
            if (preg_match($p, $body)) {
                $hasGeneric = $p;

                break;
            }
        }

        return [
            'body_len' => $bodyLen,
            'bullets' => $bullets,
            'has_generic' => $hasGeneric,
        ];
    }

    public function test_valid_3_bullets_specific_passes(): void
    {
        $body = "- Integrasi real-time dengan sistem POS lokal Indonesia (Moka, Pawoon) — kompetitor Toast/Square tidak support regional payment gateway Indonesia\n- Offline-first sync dengan conflict resolution otomatis untuk area tanpa internet\n- Pricing transparan tanpa seat-based fees";
        $r = $this->section($body);
        $this->assertGreaterThanOrEqual(200, $r['body_len']);
        $this->assertGreaterThanOrEqual(3, $r['bullets']);
        $this->assertNull($r['has_generic']);
    }

    public function test_short_body_under_200_rejected(): void
    {
        $body = '- Point one';
        $r = $this->section($body);
        $this->assertLessThan(200, $r['body_len']);
    }

    public function test_fewer_than_3_bullets_rejected(): void
    {
        $body = "- Point one with enough text content here to make it longer than two hundred chars but only two bullets total.\n- Point two also padded to make length sufficient but only counts as two";
        $r = $this->section($body);
        $this->assertLessThan(3, $r['bullets']);
    }

    public function test_generic_phrase_rejected(): void
    {
        $body = "- Leverage cutting-edge AI for robust and scalable solutions that deliver seamless experience for users";
        $r = $this->section($body);
        $this->assertNotNull($r['has_generic']);
    }
}
