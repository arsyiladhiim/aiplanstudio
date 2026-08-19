<?php

return fn (string $target) => 'Anda senior product designer dengan track record anti-template. Buat DESIGN SYSTEM dalam format Markdown yang jadi single source of truth untuk seluruh visual decisions aplikasi web. AI coding agent WAJIB baca dokumen ini sebelum generate komponen UI manapun.

REFERENSI SKILL WAJIB:
- "ui-ux-original" — design yang punya point of view, anti-AI-generic.
- "web-designer" — keputusan MOOD → PALETTE → TYPE → LAYOUT → MOTION → SIGNATURE.

OUTPUT HANYA Markdown. Mulai dari `# Design System — <NAMA_PROYEK>`. TIDAK ada markdown fence pembungkus. TIDAK ada intro/closing.

# Design System — <NAMA_PROYEK>

## 0. Pin the Subject
- Domain spesifik: <1 kalimat domain app ini, BUKAN "aplikasi web generik">
- Audience konkret: <1 kalimat siapa user, demografis + konteks pakai>
- Page\'s single job: <1 kalimat job 1 halaman paling kritis>

## 1. Design Philosophy
- 1 kalimat value/vibe (e.g. "clinical-precise untuk medical staff", "warm-pragmatic untuk orang tua", "high-density untuk power user")
- Anti-stock phrases: HARUS sebutkan 3 frasa yang JANGAN dipakai (e.g. "JANGAN \'modern minimalist\'", "JANGAN \'clean interface\' tanpa elaborasi", "JANGAN \'user-friendly\' sebagai klaim kosong")
- Rujuk langsung ke nama persona dari stage analisa (WAJIB ada nama spesifik, bukan "user umum")

## 2. Token System
WAJIB definisikan via tailwind @theme atau CSS variables. Output sebagai code fence ```css (BUKAN markdown wrapper). Pilih 4-6 warna NAMED (bukan primary/secondary generic) yang punya MAKNA sesuai app:

```css
@theme {
  /* Warna dengan makna spesifik — BUKAN primary/secondary generic */
  --color-<nama-makna-1>: #<hex>; /* contoh: --color-ink: #1a1d1b; untuk text utama */
  --color-<nama-makna-2>: #<hex>;
  --color-<nama-makna-3>: #<hex>;
  --color-<nama-makna-4>: #<hex>;
  --color-<nama-makna-5>: #<hex>;

  /* Type — WAJIB beda dari Inter-by-default. Pilih display + body + mono */
  --font-display: \'<Nama Font Google Fonts>\', serif;
  --font-body: \'<Nama Font Google Fonts>\', sans-serif;
  --font-mono: \'<Nama Font Google Fonts>\', monospace;

  /* Spacing scale 4-step — WAJIB clamp() untuk fluid */
  --space-xs: clamp(0.5rem, 0.46rem + 0.22vw, 0.63rem);
  --space-sm: clamp(0.75rem, 0.68rem + 0.33vw, 0.94rem);
  --space-md: clamp(1rem, 0.91rem + 0.43vw, 1.25rem);
  --space-lg: clamp(2rem, 1.83rem + 0.87vw, 2.5rem);

  /* Radius 2-3 nilai — variasi, BUKAN rounded-full untuk semua */
  --radius-sm: 0.25rem;
  --radius-md: 0.5rem;
  --radius-lg: 1rem;

  /* Shadow 3 level dengan warna spesifik */
  --shadow-subtle: 0 1px 2px rgba(0,0,0,0.04);
  --shadow-medium: 0 4px 12px rgba(0,0,0,0.08);
  --shadow-prominent: 0 12px 32px rgba(0,0,0,0.12);

  /* Motion — 3 durasi + 2 easing */
  --motion-instant: 80ms;
  --motion-snappy: 180ms;
  --motion-considered: 320ms;
  --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
  --ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);
}
```

WAJIB: --color-* ≥4, --font-* ≥2, --space-* ≥3, --radius-* ≥1.

## 3. Signature Element
Untuk 3-5 screen utama dari analisa.stage_6_halaman, definisikan SIGNATURE pattern unik (bukan stock):

### Screen 1: <Nama Screen>
- **Pattern**: <nama signature unik, e.g. "Floating Dock with Haptic Cursor", "Split Pane Asymmetric", "Sticky Context Bar", "Inline Floating Action">
- **ASCII Wireframe**: 5-8 baris ASCII art
- **Implementation hint**: 1-2 line code/approach (komponen atau library)
- **Kenapa memorable**: 1 kalimat rasional — apa yang membuat screen ini berbeda dari kompetitor

### Screen 2: <Nama Screen>
(structure sama, total minimal 3 screens)

### Screen 3: <Nama Screen>
(structure sama)

## 4. Component Patterns
5-8 komponen UI custom yang BUKAN copy dari shadcn/Tailwind UI stock. Untuk tiap komponen:
- **Nama**: <Nama Komponen>
- **Kapan pakai**: <1 kalimat use case spesifik>
- **Visual cue unik**: <apa yang membedakan dari stock library — twist signature>
- **Props signature (TypeScript)**: <interface 2-5 lines>

## 5. State Vocabulary
4 state dengan treatment SPESIFIK sesuai domain app (bukan generic):
- **Empty state**: <visual cue + microcopy spesifik, BUKAN "No data" generik>
- **Loading state**: <skeleton shape sesuai layout, BUKAN spinner default>
- **Error state**: <inline + actionable, BUKAN toast generik>
- **Success state**: <micro-interaction signature, misal morph icon atau sound pattern>

## 6. Anti-Pattern Checklist
WAJIB ≥7 item dengan `- [ ]` (hard constraint untuk AI agent):
- [ ] <anti-pattern spesifik untuk app ini — bukan generic AI cliché>
- [ ] <anti-pattern 2>
- [ ] <anti-pattern 3>
- [ ] <anti-pattern 4>
- [ ] <anti-pattern 5>
- [ ] <anti-pattern 6>
- [ ] <anti-pattern 7>

Referensi anti-pattern: blue→purple gradient, uniform card grid 3-kolom, centered-everything hero, Inter-by-default, opacity 0.8 hover, box-shadow 0 4px 6px pada semua card, identical section treatments, generic "Welcome to ..." hero, rounded-full pada SEMUA button, stock placeholder image generik, emoji sebagai feature icons (🚀 ✨ 💡 ⚡).

## 7. Layout Rhythm
3 section treatment berbeda (bukan uniform):
- Section A: <pattern, e.g. "dense data table → spacious hero → asymmetric feature grid">
- Section B: <pattern berbeda>
- Section C: <pattern berbeda lagi>

WAJIB ada rhythm/alternation. BUKAN "card grid 3-kolom seragam" di semua section.

## 8. Motion Choreography
- 1 orchestrated signature moment: <deskripsi signature animation, e.g. "page transition: content slide-up 300ms + accent shape morph 600ms">
- Reduced-motion fallback: <apa yang terjadi kalau prefers-reduced-motion aktif — WAJIB ada>

## 9. Microcopy Voice
- 3 contoh microcopy untuk app ini: <button labels, empty messages, error messages>
- Tone guideline: <1 kalimat konsisten untuk seluruh copy>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
VERIFY sebelum respond:
1. 9 heading "## N." ada (0-9).
2. Section 2 code fence ```css ada, dengan ≥4 --color-*, ≥2 --font-*, ≥3 --space-*, ≥1 --radius-*.
3. Section 3 minimal 3 screens (### Screen N: ...).
4. Section 4 minimal 5 komponen.
5. Section 6 checklist `- [ ]` minimal 7 item.
6. Panjang total ≥2500 chars.
7. Tidak ada placeholder unfilled `<...>` selain `<NAMA_PROYEK>` di heading.

'.platformSuffix($target);
