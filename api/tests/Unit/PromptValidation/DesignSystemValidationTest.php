<?php

namespace Tests\Unit\PromptValidation;

use App\Services\AiOutputParser;
use PHPUnit\Framework\TestCase;

class DesignSystemValidationTest extends TestCase
{
    private AiOutputParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AiOutputParser;
    }

    private function buildValidDesignSystem(): string
    {
        return <<<'MD'
# Design System — TestApp

## 0. Pin the Subject
- Domain spesifik: POS untuk retail
- Audience konkret: owner warung
- Page's single job: catat transaksi cepat

## 1. Design Philosophy
- clinical-precise untuk owner warung
- Anti-stock: "modern minimalist", "clean interface", "user-friendly"
- Persona: Budi Santoso — Owner UMKM

## 2. Token System

```css
@theme {
  --color-ink: #1a1d1b;
  --color-paper: #f8faf9;
  --color-brand: #10b981;
  --color-accent: #06b6d4;
  --color-warn: #f59e0b;
  --font-display: 'Space Grotesk', serif;
  --font-body: 'Inter', sans-serif;
  --font-mono: 'JetBrains Mono', monospace;
  --space-xs: clamp(0.5rem, 0.46rem + 0.22vw, 0.63rem);
  --space-sm: clamp(0.75rem, 0.68rem + 0.33vw, 0.94rem);
  --space-md: clamp(1rem, 0.91rem + 0.43vw, 1.25rem);
  --radius-sm: 0.25rem;
}
```

## 3. Signature Element

### Screen 1: Dashboard
- **Pattern**: Asymmetric grid with metric hero
- **ASCII Wireframe**: +-----+
- **Implementation hint**: Tailwind grid
- **Kenapa memorable**: Hero number dominan

### Screen 2: Login
- **Pattern**: Centered glass panel
- **ASCII Wireframe**: +-----+
- **Implementation hint**: Glass component
- **Kenapa memorable**: Different dari card-grid default

### Screen 3: Reports
- **Pattern**: Sticky chart header
- **ASCII Wireframe**: +-----+
- **Implementation hint**: Sticky position
- **Kenapa memorable**: Chart bukan table generik

## 4. Component Patterns

### ActionButton
- **Kapan pakai**: primary CTA
- **Visual cue unik**: pill dengan chevron anim
- **Props signature**: variant: primary | secondary

### MetricCard
- **Kapan pakai**: dashboard KPI
- **Visual cue unik**: number dominan + trend arrow

### StatusBadge
- **Kapan pakai**: status indicator
- **Visual cue unik**: dot + label horizontal

### Toast
- **Kapan pakai**: feedback non-blocking
- **Visual cue unik**: bottom slide dengan accent border

### FilterBar
- **Kapan pakai**: list dengan filter
- **Visual cue unik**: chip inline bukan dropdown

## 5. State Vocabulary
- Empty state: ilustrasi stroke + ajakan spesifik
- Loading state: skeleton match layout
- Error state: inline + actionable
- Success state: morph check icon

## 6. Anti-Pattern Checklist
- [ ] no blue-purple gradient
- [ ] no uniform 3-col card grid
- [ ] no centered hero
- [ ] no Inter-by-default
- [ ] no opacity 0.8 hover
- [ ] no box-shadow generik
- [ ] no Welcome to default

## 7. Layout Rhythm
- Section A: dense table
- Section B: spacious hero
- Section C: asymmetric grid

## 8. Motion Choreography
- Signature moment: page slide-up 300ms + accent morph 600ms
- Reduced-motion fallback: snap transition tanpa anim

## 9. Microcopy Voice
- Button: "Simpan transaksi"
- Empty: "Belum ada item — tambah sekarang"
- Error: "Gagal simpan — coba lagi"
- Tone: bahasa langsung, actionable, tanpa jargon

MD;
    }

    public function test_extracts_markdown_headings(): void
    {
        $headings = $this->parser->extractMarkdownHeadings($this->buildValidDesignSystem());
        $this->assertContains('## 0. Pin the Subject', $headings);
        $this->assertContains('## 9. Microcopy Voice', $headings);
        $this->assertGreaterThanOrEqual(10, count($headings));
    }

    public function test_extracts_css_code_fence(): void
    {
        $css = $this->parser->extractCodeFence($this->buildValidDesignSystem(), 'css');
        $this->assertNotNull($css);
        $this->assertStringContainsString('--color-ink', $css);
        $this->assertStringContainsString('--font-display', $css);
    }

    public function test_counts_color_vars(): void
    {
        $css = $this->parser->extractCodeFence($this->buildValidDesignSystem(), 'css');
        $matches = preg_match_all('/--color-[a-z0-9_-]+/i', $css);
        $this->assertGreaterThanOrEqual(4, $matches);
    }

    public function test_counts_font_vars(): void
    {
        $css = $this->parser->extractCodeFence($this->buildValidDesignSystem(), 'css');
        $matches = preg_match_all('/--font-[a-z0-9_-]+/i', $css);
        $this->assertGreaterThanOrEqual(2, $matches);
    }

    public function test_counts_screens_in_signature(): void
    {
        $screens = preg_match_all('/^###\s+Screen\s+\d+/m', $this->buildValidDesignSystem());
        $this->assertGreaterThanOrEqual(3, $screens);
    }

    public function test_counts_checklist_items(): void
    {
        $count = $this->parser->extractChecklistItems($this->buildValidDesignSystem());
        $this->assertGreaterThanOrEqual(7, $count);
    }
}
