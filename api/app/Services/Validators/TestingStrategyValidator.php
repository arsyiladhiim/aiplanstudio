<?php

namespace App\Services\Validators;

use App\Services\StageArtifactValidator;

/**
 * CP-46.C — validator untuk `testing_strategy` artifact.
 * 9 heading wajib + panjang minimum 1500 char.
 */
class TestingStrategyValidator
{
    private StageArtifactValidator $core;

    public function __construct(StageArtifactValidator $core)
    {
        $this->core = $core;
    }

    /** @var array<int, string> */
    public const REQUIRED_HEADINGS = [
        '## 1. Test Pyramid',
        '## 2. Unit Test Strategy',
        '## 3. Integration Test Strategy',
        '## 4. E2E Test Strategy',
        '## 5. Coverage Targets',
        '## 6. Critical Paths',
        '## 7. Tools & Infrastructure',
        '## 8. Test Data Management',
        '## 9. Smoke Test Scope',
        '## 10. Definition of Done',
    ];

    public function validate(string $stage, string $content): void
    {
        // Heading check dulu agar pesan error spesifik.
        $this->core->validateMarkdownArtifact($stage, $content, self::REQUIRED_HEADINGS);

        // Critical Paths minimal 5 — cek sebelum length agar pesan error akurat.
        // Terima kedua bentuk penulisan: **PATH-N**: (prompt) dan **PATH-N:** (gaya alternatif).
        // Fallback: hitung baris list yang diawali PATH-N di section Critical Paths.
        preg_match_all('/\*\*PATH-\d+:\*\*/', $content, $m);
        $count = count($m[0]);
        if ($count < 5) {
            preg_match_all('/\*\*PATH-\d+\*\*:/', $content, $m);
            $count = count($m[0]);
        }
        if ($count < 5) {
            // Longgar: nomor PATH tanpa bold (penulis AI yang tidak konsisten format).
            preg_match_all('/PATH-\d+\s*:/', $content, $m);
            $count = count($m[0]);
        }
        if ($count < 5) {
            throw new \RuntimeException($stage.': Critical Paths minimal 5 (sekarang '.$count.'). Stage ditandai error.');
        }

        // Length check terakhir (semua heading + 5 critical paths sudah terpenuhi).
        if (strlen($content) < 1500) {
            throw new \RuntimeException($stage.': artifact terlalu pendek (min 1500 char, got '.strlen($content).'). Stage ditandai error.');
        }
    }
}
