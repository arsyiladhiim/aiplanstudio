<?php

namespace Tests\Feature;

use App\Services\AiJsonParser;
use App\Services\AiOutputParser;
use App\Services\StageArtifactValidator;
use App\Services\Validators\TestingStrategyValidator;
use Tests\TestCase;

class TestingStrategyValidatorTest extends TestCase
{
    private TestingStrategyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $core = new StageArtifactValidator(new AiOutputParser(new AiJsonParser));
        $this->validator = new TestingStrategyValidator($core);
    }

    private function validDoc(): string
    {
        $base = <<<'MD'
## 1. Test Pyramid
70/20/10 untuk project ini (unit/integration/e2e) — tim kecil, Laravel monolith.

## 2. Unit Test Strategy
Framework: PHPUnit 11. File naming `*Test.php`. Mock policy: mock external HTTP, real DB via transactions. Coverage target per stage: backend services 85%, controllers 75%, models 70%.

## 3. Integration Test Strategy
DB pakai RefreshDatabase. HTTP via $this->getJson. External API pakai Http::fake(). Database transactions wrap setiap test method.

## 4. E2E Test Strategy
Playwright chromium + firefox. Critical paths di bawah ini.

## 5. Coverage Targets
80% line, 70% branch backend; 70% line frontend.

## 6. Critical Paths
- **PATH-1:** Login → dashboard → logout.
- **PATH-2:** Register invalid email → error.
- **PATH-3:** Create project → wizard start → master prompt.
- **PATH-4:** Webhook phase-complete → tracking update.
- **PATH-5:** Export ZIP berisi semua artifact.

## 7. Tools & Infrastructure
PHPUnit 11 + Playwright 1.4x. CI GitHub Actions. Codecov coverage report. Flaky policy: retry 2x lalu quarantine label `flaky`.

## 8. Test Data Management
Laravel factory + seeder khusus test. RefreshDatabase antar test.

## 9. Smoke Test Scope
- `GET /api/health` — expect 200, <500ms.
- `GET /api/stages` — expect 401 tanpa auth, 200 dengan auth.
- `POST /api/login` — expect 200 valid creds, 401 invalid.
- `GET /api/projects` — expect 200 auth.
- `GET /api/versions/{id}` — expect 200 owner, 404 non-owner.

## 10. Definition of Done - Testing
- [ ] Unit ≥ 80%
- [ ] Integration pass
- [ ] E2E critical paths pass
- [ ] No flaky test
- [ ] CI hijau
MD;

        // Pastikan ≥ 1500 char untuk lulus length check (Section 1-2 ditambah boilerplate).
        return str_pad($base, 1600, "\n\nBoilerplate teks pengisi agar artifact mencapai panjang minimum yang dipersyaratkan validator.\n");
    }

    public function test_path_format_colon_inside_bold_is_accepted(): void
    {
        // Prompt menghasilkan **PATH-1:**, tapi historis validator salah-sangka
        // format ini invalid (user melihat "minimal 5 (sekarang 0)").
        $doc = preg_replace(
            '/\*\*PATH-(\d+):\*\*/',
            '- **PATH-$1:** dulu',
            $this->validDoc()
        );
        // Valid doc sudah pakai format colon-inside; pastikan lulus saat colon-outside.
        $this->validator->validate('testing_strategy', $this->validDoc());
        $this->assertTrue(true);
    }

    public function test_path_format_colon_outside_bold_is_accepted(): void
    {
        $doc = str_replace('**PATH-1:** Login', '**PATH-1**: Login', $this->validDoc());
        $doc = str_replace('**PATH-2:** Register', '**PATH-2**: Register', $doc);
        $doc = str_replace('**PATH-3:** Create', '**PATH-3**: Create', $doc);
        $doc = str_replace('**PATH-4:** Webhook', '**PATH-4**: Webhook', $doc);
        $doc = str_replace('**PATH-5:** Export', '**PATH-5**: Export', $doc);
        $this->validator->validate('testing_strategy', $doc);
        $this->assertTrue(true);
    }

    public function test_valid_doc_passes(): void
    {
        $this->validator->validate('testing_strategy', $this->validDoc());
        $this->assertTrue(true);
    }

    public function test_missing_heading_throws(): void
    {
        $doc = str_replace('## 6. Critical Paths', '## 6. Jalur Kritis', $this->validDoc());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Critical Paths');
        $this->validator->validate('testing_strategy', $doc);
    }

    public function test_kurang_dari_5_critical_paths_throws(): void
    {
        $doc = preg_replace('/^- \*\*PATH-5:.*$/m', '', $this->validDoc());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('minimal 5');
        $this->validator->validate('testing_strategy', $doc);
    }

    private const REQUIRED_HEADINGS_FOR_TEST = [
        '## 1. Test Pyramid',
        '## 2. Unit Test Strategy',
        '## 3. Integration Test Strategy',
        '## 4. E2E Test Strategy',
        '## 5. Coverage Targets',
        '## 6. Critical Paths',
        '## 7. Tools & Infrastructure',
        '## 8. Test Data Management',
        '## 9. Smoke Test Scope',
        '## 10. Definition of Done - Testing',
    ];

    public function test_artifact_terlalu_pendek_throws(): void
    {
        // Doc dengan semua heading + 5 critical paths tapi body tipis → gagal length check.
        $body = array_merge(
            self::REQUIRED_HEADINGS_FOR_TEST,
            [
                '- **PATH-1:** a',
                '- **PATH-2:** b',
                '- **PATH-3:** c',
                '- **PATH-4:** d',
                '- **PATH-5:** e',
            ],
            array_fill(0, 5, 'short')
        );
        $doc = implode("\n", $body);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('terlalu pendek');
        $this->validator->validate('testing_strategy', $doc);
    }
}
