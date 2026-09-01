<?php

namespace App\Services;

/**
 * CP-44 CP-06: validator artifact per stage — diekstrak dari PipelineRunner
 * tanpa mengubah perilaku. Murni memvalidasi konten (markdown/JSON/keyword).
 */
class StageArtifactValidator
{
    /** Tiap grup adalah OR — minimal 1 keyword dalam grup wajib muncul (case-insensitive). */
    public const STAGE_REQUIRED_KEYWORDS = [
        'design_system' => [['signature', 'elemen khas'], ['anti-pattern', 'anti-pola'], ['token']],
        'design_system_mobile' => [['Material'], ['ThemeData'], ['signature']],
        'prd' => [['user story', 'user stories', 'user story'], ['acceptance', 'penerimaan'], ['functional requirement', 'kebutuhan fungsional']],
        'architecture' => [['stack', 'teknologi'], ['module', 'boundary', 'batas'], ['trade-off', 'tradeoff', 'keputusan']],
        'standards_web' => [['TypeScript', 'typescript', 'ts'], ['lint'], ['convention', 'konvensi']],
        'standards_mobile' => [['Dart', 'dart'], ['lint'], ['convention', 'konvensi']],
        'security' => [['autentikasi', 'authentication'], ['otorisasi', 'authorization', 'role'], ['owasp', 'checklist', 'keamanan']],
        'observability' => [['logging', 'log'], ['monitoring', 'pemantauan'], ['health check', 'healthcheck', 'kesehatan']],
        'env_config' => [['environment', 'lingkungan'], ['variable', 'variabel'], ['configuration', 'konfigurasi']],
        'deployment' => [['Docker', 'docker'], ['rollback']],
        'agents' => [['AGENTS.md', 'agents'], ['agent'], ['instruction', 'instruksi']],
    ];

    public const GENERIC_PATTERNS = [
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

    public function __construct(private readonly AiOutputParser $outputParser) {}

    public function validateMarkdownArtifact(string $stage, string $content, array $mustHaveHeadings): void
    {
        $headings = $this->outputParser->extractMarkdownHeadings($content);
        $missing = [];
        foreach ($mustHaveHeadings as $required) {
            $found = false;
            foreach ($headings as $h) {
                if (str_starts_with($h, $required)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $required;
            }
        }

        if (! empty($missing)) {
            throw new \RuntimeException($stage.': section heading hilang — '.implode(', ', $missing).'. Stage ditandai error.');
        }

        $this->assertSectionOrdering($stage, $mustHaveHeadings, $headings);
        $this->assertRequiredKeywords($stage, $content);
    }

    public function assertRequiredKeywords(string $stage, string $content): void
    {
        $groups = self::STAGE_REQUIRED_KEYWORDS[$stage] ?? [];
        foreach ($groups as $group) {
            $hit = false;
            foreach ($group as $kw) {
                if (mb_stripos($content, $kw) !== false) {
                    $hit = true;

                    break;
                }
            }
            if (! $hit) {
                throw new \RuntimeException(
                    $stage.': missing required keyword group (wajib salah satu: '.implode(' | ', $group).'). Stage ditandai error.'
                );
            }
        }
    }

    public function detectGenericOutput(string $stage, string $content): void
    {
        $strict = env('GENERIC_GUARD_STRICT', 'true') !== 'false';
        if (! $strict) {
            return;
        }
        foreach (self::GENERIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $content)) {
                \Log::warning("Generic output detected in {$stage}", [
                    'stage' => $stage,
                    'pattern' => $pattern,
                    'preview' => mb_substr($content, 0, 200),
                ]);
                throw new \RuntimeException(
                    "{$stage}: output terindikasi template generik (pattern: {$pattern}). ".
                    'Regenerate dengan diferensiasi spesifik untuk produk ini.'
                );
            }
        }
    }

    public function assertApiContractSchema(array $contract): void
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        foreach ($contract as $i => $item) {
            if (! is_array($item)) {
                throw new \RuntimeException("api_contract[$i]: bukan array — struktur invalid.");
            }
            foreach (['resource', 'method', 'path', 'auth', 'description'] as $field) {
                if (! isset($item[$field]) || ! is_string($item[$field]) || trim($item[$field]) === '') {
                    throw new \RuntimeException("api_contract[$i]: field '$field' wajib ada dan non-empty.");
                }
            }
            $method = strtoupper($item['method']);
            if (! in_array($method, $allowedMethods, true)) {
                throw new \RuntimeException("api_contract[$i]: method '{$item['method']}' invalid (allowed: ".implode(',', $allowedMethods).').');
            }
            if (! str_starts_with($item['path'], '/')) {
                throw new \RuntimeException("api_contract[$i]: path '{$item['path']}' harus mulai dengan '/'.");
            }
        }
    }

    public function assertSectionOrdering(string $stage, array $mustHaveHeadings, array $foundHeadings): void
    {
        $expectedNumbers = [];
        foreach ($mustHaveHeadings as $heading) {
            if (preg_match('/^##\s+(\d+)\./', $heading, $m)) {
                $expectedNumbers[(int) $m[1]] = true;
            }
        }
        if (empty($expectedNumbers)) {
            return;
        }

        $seen = [];
        $actualOrder = [];
        foreach ($foundHeadings as $h) {
            if (preg_match('/^##\s+(\d+)\./', $h, $m)) {
                $n = (int) $m[1];
                if (isset($expectedNumbers[$n]) && ! isset($seen[$n])) {
                    $seen[$n] = true;
                    $actualOrder[] = $n;
                }
            }
        }

        $expected = array_keys($expectedNumbers);
        if ($actualOrder !== $expected) {
            throw new \RuntimeException(
                $stage.': section ordering invalid — expected '.
                implode(',', $expected).' but got '.
                implode(',', $actualOrder).'. Stage ditandai error.'
            );
        }
    }

    public function validateDesignSystemSectionRules(string $stage, string $content): void
    {
        // Section 2: Token System — css fence (web) atau dart fence (Flutter).
        $cssFence = $this->outputParser->extractCodeFence($content, 'css');
        $dartFence = $this->outputParser->extractCodeFence($content, 'dart');
        if ($cssFence === null && $dartFence === null) {
            throw new \RuntimeException($stage.': Section 2 (Token System) WAJIB punya code fence (```css untuk web atau ```dart untuk Flutter). Stage ditandai error.');
        }

        if ($dartFence !== null && $cssFence === null) {
            $colors = preg_match_all('/Color\s*\(\s*0x|Colors\./i', $dartFence);
            if ($colors < 4) {
                throw new \RuntimeException($stage.': Section 2 (dart) WAJIB punya minimal 4 definisi warna (Color(0x…)/Colors.*). Saat ini: '.$colors.'. Stage ditandai error.');
            }
            $fonts = preg_match_all('/TextStyle|fontFamily|GoogleFonts/i', $dartFence);
            if ($fonts < 2) {
                throw new \RuntimeException($stage.': Section 2 (dart) WAJIB punya minimal 2 definisi font (TextStyle/GoogleFonts). Saat ini: '.$fonts.'. Stage ditandai error.');
            }
        } else {
            $colorVars = preg_match_all('/--color-[a-z0-9_-]+/i', $cssFence ?? '');
            if ($colorVars < 4) {
                throw new \RuntimeException($stage.': Section 2 (Token System) WAJIB punya minimal 4 variabel --color-*. Saat ini: '.$colorVars.'. Stage ditandai error.');
            }

            $fontVars = preg_match_all('/--font-[a-z0-9_-]+/i', $cssFence ?? '');
            if ($fontVars < 2) {
                throw new \RuntimeException($stage.': Section 2 (Token System) WAJIB punya minimal 2 variabel --font-*. Saat ini: '.$fontVars.'. Stage ditandai error.');
            }
        }

        // Section 3: Signature Element — must have ≥3 screens (### Screen N: ...)
        $screens = preg_match_all('/^###\s+Screen\s+\d+/m', $content);
        if ($screens < 3) {
            throw new \RuntimeException($stage.': Section 3 (Signature Element) WAJIB punya minimal 3 screen (### Screen N: ...). Stage ditandai error.');
        }

        // Section 4: Component Patterns — must have ≥5 components (### heading ATAU bullet list - **Name**)
        $section4 = '';
        if (preg_match('/##\s*4\.\s*Component Patterns(.*?)(?=##\s*\d+\.)/s', $content, $m4)) {
            $section4 = $m4[1];
        }
        $componentHeadings = preg_match_all('/^###\s+[A-Za-z0-9][\w\s\-–—:()\/.,+&§]*$/m', $section4);
        $componentBullets = preg_match_all('/^-\s*(\*\*)?[A-Za-z][\w\s\-–—:()\/,.]/m', $section4);
        $components = $componentHeadings + $componentBullets;
        if ($components < 5) {
            throw new \RuntimeException($stage.': Section 4 (Component Patterns) WAJIB punya minimal 5 komponen (### Nama atau - Nama). Stage ditandai error.');
        }

        // Section 6: Anti-Pattern Checklist — must have ≥7 items
        $checklist = $this->outputParser->extractChecklistItems($content);
        if ($checklist < 7) {
            throw new \RuntimeException($stage.': Section 6 (Anti-Pattern Checklist) WAJIB punya minimal 7 item (- [ ]). Stage ditandai error.');
        }

        // Signature Element — must be specific (≥300 char) and avoid generic phrases without justification.
        $this->assertSignatureElement($stage, $content);

        // Minimum length
        if (strlen(trim($content)) < 2500) {
            throw new \RuntimeException($stage.': panjang output terlalu pendek ('.strlen(trim($content)).' chars, minimal 2500). Stage ditandai error.');
        }
    }

    public function validateArchitectureSectionRules(string $content): void
    {
        $sections = preg_split("/(?=^##\s)/m", $content);

        $asciiFound = false;
        $tradeoffSection = '';
        foreach ($sections as $sec) {
            if (! $asciiFound && preg_match('/Module Boundaries/i', $sec)) {
                // box-drawing chars atau indented ASCII blocks (│ ├ └ ┌ ─)
                $asciiFound = preg_match('/[│├└┌┐┘─┬┴┼]/u', $sec) === 1;
            }
            if (preg_match('/Trade-?offs?/i', $sec)) {
                $tradeoffSection = $sec;
            }
        }

        if (! $asciiFound) {
            throw new \RuntimeException('architecture: Section Module Boundaries WAJIB memuat ASCII diagram (``` box-drawing). Stage ditandai error.');
        }

        $tableRows = preg_match_all('/^\s*\|.*\|/m', $tradeoffSection);
        if ($tableRows < 4) {
            throw new \RuntimeException('architecture: Section Trade-offs WAJIB tabel markdown minimal 4 baris (header + separator + ≥2 data). Saat ini: '.$tableRows.'. Stage ditandai error.');
        }

        $placeholders = preg_match_all('/<[A-Z][A-Z0-9_]*>/', $content);
        if ($placeholders > 0) {
            throw new \RuntimeException('architecture: masih ada placeholder <...> unfilled ('.($placeholders).'). Stage ditandai error.');
        }
    }

    public function validateSecuritySectionRules(string $content): void
    {
        $checklist = $this->outputParser->extractChecklistItems($content);
        if ($checklist < 6) {
            throw new \RuntimeException('security: Section Checklist WAJIB punya minimal 6 item (- [ ] / - [x]). Saat ini: '.$checklist.'. Stage ditandai error.');
        }

        $placeholders = preg_match_all('/<[A-Z][A-Z0-9_]*>/', $content);
        if ($placeholders > 0) {
            throw new \RuntimeException('security: masih ada placeholder <...> unfilled ('.($placeholders).'). Stage ditandai error.');
        }
    }

    public function assertSignatureElement(string $stage, string $content): void
    {
        $sections = preg_split('/(?=^##\s\d+\.)/m', $content);
        $signatureSection = '';
        foreach ($sections as $s) {
            if (preg_match('/^##\s+\d+\.\s+Signature Element/im', $s)) {
                $signatureSection = $s;

                break;
            }
        }
        if ($signatureSection === '') {
            throw new \RuntimeException("{$stage}: section Signature Element wajib ada. Stage ditandai error.");
        }

        $body = trim(preg_replace('/^##\s+\d+\.\s+Signature Element\s*$/m', '', $signatureSection));
        $bodyLen = mb_strlen($body);

        $genericSignatures = ['glassmorphism', 'neumorphism', 'material design', 'flat design', 'minimalist'];
        $matched = [];
        foreach ($genericSignatures as $sig) {
            if (stripos($body, $sig) !== false) {
                $matched[] = $sig;
            }
        }

        if ($matched !== [] && $bodyLen < 400) {
            throw new \RuntimeException(
                "{$stage}: Signature Element memakai frasa generik (".implode(', ', $matched).
                ') tanpa diferensiasi. Total section wajib ≥400 char dengan alasan spesifik.'
            );
        }

        if ($bodyLen < 300) {
            throw new \RuntimeException(
                "{$stage}: Signature Element terlalu pendek ({$bodyLen} char, minimal 300). ".
                'Tambahkan diferensiasi spesifik untuk produk ini.'
            );
        }
    }

    public function validatePrdSectionRules(string $content): void
    {
        $usCount = preg_match_all('/\*\*US-\d+:\*\*/', $content);
        if ($usCount < 5 || $usCount > 15) {
            throw new \RuntimeException('prd: jumlah User Story (US-XX) harus 5-15. Saat ini: '.$usCount.'. Stage ditandai error.');
        }

        // Check AC has Given/When/Then
        $acCount = preg_match_all('/\*\*Acceptance Criteria:\*\*/', $content);
        if ($acCount < 5) {
            throw new \RuntimeException('prd: minimal 5 section "**Acceptance Criteria:**" harus ada. Stage ditandai error.');
        }

        $givenCount = preg_match_all('/^\s*-\s+Given\s+/m', $content);
        if ($givenCount < 5) {
            throw new \RuntimeException('prd: minimal 5 baris "- Given ..." harus ada. Stage ditandai error.');
        }

        $whenCount = preg_match_all('/^\s*-\s+When\s+/m', $content);
        if ($whenCount < 5) {
            throw new \RuntimeException('prd: minimal 5 baris "- When ..." harus ada. Stage ditandai error.');
        }

        $thenCount = preg_match_all('/^\s*-\s+Then\s+/m', $content);
        if ($thenCount < 5) {
            throw new \RuntimeException('prd: minimal 5 baris "- Then ..." harus ada. Stage ditandai error.');
        }

        // Section 7: Differentiation — 3 specific differentiators, no generic phrases.
        $this->assertPrdDifferentiation($content);
    }

    public function assertPrdDifferentiation(string $content): void
    {
        $sections = preg_split('/(?=^##\s+\d+\.)/m', $content);
        $diffSection = '';
        foreach ($sections as $s) {
            if (preg_match('/^##\s+\d+\.\s+Differentiation/im', $s)) {
                $diffSection = $s;

                break;
            }
        }
        if ($diffSection === '') {
            throw new \RuntimeException('prd: section "## 7. Differentiation" WAJIB ada dengan 3 poin spesifik. Stage ditandai error.');
        }

        $body = trim(preg_replace('/^##\s+\d+\.\s+Differentiation\s*$/m', '', $diffSection));
        $bodyLen = mb_strlen($body);
        if ($bodyLen < 200) {
            throw new \RuntimeException("prd: Section Differentiation terlalu pendek ({$bodyLen} char, minimal 200). Stage ditandai error.");
        }

        $bullets = preg_match_all('/^\s*[-*•]\s+/mu', $body);
        if ($bullets < 3) {
            throw new \RuntimeException("prd: Section Differentiation wajib punya ≥3 bullet poin. Saat ini: {$bullets}. Stage ditandai error.");
        }

        // Reject generic phrases in differentiation
        foreach (self::GENERIC_PATTERNS as $pattern) {
            if (preg_match($pattern, $body)) {
                throw new \RuntimeException(
                    "prd: Section Differentiation mengandung frasa generik (pattern: {$pattern}). ".
                    'Wajib spesifik ke produk — hindari template.'
                );
            }
        }
    }

    public function validateEnvConfigSectionRules(string $content): void
    {
        $envBlock = $this->outputParser->extractCodeFence($content, 'env')
            ?? $this->outputParser->extractCodeFencePrefix($content, 'env')
            ?? $this->outputParser->extractCodeFence($content, 'dotenv')
            ?? $this->outputParser->extractCodeFencePrefix($content, 'dotenv')
            ?? $this->outputParser->extractCodeFence($content, 'bash')
            ?? $this->outputParser->extractCodeFencePrefix($content, 'bash');

        // Backend vars yang WAJIB ada
        $requiredVars = ['APP_KEY', 'DB_PASSWORD', 'APP_URL', 'SESSION_DOMAIN'];
        $foundAnyBackend = false;
        foreach ($requiredVars as $rv) {
            if (stripos($content, $rv) !== false) {
                $foundAnyBackend = true;
                break;
            }
        }

        if (! $foundAnyBackend) {
            throw new \RuntimeException('env_config: variabel backend wajib (APP_KEY, DB_PASSWORD, APP_URL, SESSION_DOMAIN) tidak ditemukan. Stage ditandai error.');
        }

        if ($envBlock === null) {
            throw new \RuntimeException('env_config: code fence .env.example (```env atau ```bash atau ```dotenv) tidak ditemukan. Stage ditandai error.');
        }

        $vars = $this->outputParser->extractEnvVars($envBlock);
        if (count($vars) < 8) {
            throw new \RuntimeException('env_config: .env.example WAJIB punya minimal 8 variabel. Saat ini: '.count($vars).'. Stage ditandai error.');
        }
    }

    public function validateStandardsSectionRules(string $stage, string $content): void
    {
        $requiredSnippets = $stage === 'standards_mobile'
            ? ['dart']
            : ['php', 'tsx', 'sql'];

        foreach ($requiredSnippets as $lang) {
            $fence = $this->outputParser->extractCodeFence($content, $lang)
                ?? $this->outputParser->extractCodeFencePrefix($content, $lang);
            if ($fence === null) {
                throw new \RuntimeException($stage.': code fence bahasa '.$lang.' tidak ditemukan. Stage ditandai error.');
            }
        }

        // Hard rules ≥10 — terima format angka, bullet (- / *), atau checklist (angka / - [ ])
        $numberedRules = preg_match_all('/^\s*(?:\d+\.|-|\*|-\s*\[[ xX]\])/m', $content);
        if ($numberedRules < 10) {
            throw new \RuntimeException($stage.': Hard Rules list (numbered/bullet/checklist) minimal 10 item. Saat ini: '.$numberedRules.'. Stage ditandai error.');
        }
    }

    public function validateAgentsSectionRules(string $content): void
    {
        $numberedRules = preg_match_all('/^\s*(?:\d+\.|-|\*|-\s*\[[ xX]\])/m', $content);
        if ($numberedRules < 10) {
            throw new \RuntimeException('agents: Hard Rules list (numbered/bullet/checklist) minimal 10 item. Saat ini: '.$numberedRules.'. Stage ditandai error.');
        }

        // File structure blocks
        $codeBlock = $this->outputParser->extractCodeFence($content, '')
            ?? $this->outputParser->extractCodeFence($content, 'plain')
            ?? $this->outputParser->extractCodeFence($content, 'text');
        if ($codeBlock === null) {
            // fallback: cari backtick block generic
            if (preg_match('/```\n([\s\S]+?)\n```/', $content, $m)) {
                $codeBlock = $m[1];
            }
        }

        if ($codeBlock === null) {
            throw new \RuntimeException('agents: code block file structure tidak ditemukan. Stage ditandai error.');
        }
    }

    public function normalizeApiContract(array $endpoints): array
    {
        return array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }
            if (array_key_exists('auth', $item)) {
                if (is_bool($item['auth'])) {
                    $item['auth'] = $item['auth'] ? 'required' : 'none';
                } elseif ($item['auth'] === null || $item['auth'] === '') {
                    $item['auth'] = 'none';
                } elseif (is_string($item['auth'])) {
                    $item['auth'] = trim($item['auth']);
                }
            }
            if (isset($item['path']) && is_string($item['path']) && ! str_starts_with($item['path'], '/')) {
                $item['path'] = '/'.ltrim($item['path'], '/');
            }

            return $item;
        }, $endpoints);
    }
}
