# 34 — Landing Page Branding & Promo Overhaul — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-18
> **Completed:** 2026-08-19
> **Scope:** Restructure landing page untuk branding & promo, sembunyikan detail dapur (22 stages, technical flow), tampilkan benefit-driven showcase
> **File:** `web/src/app/page.tsx`

---

## Objective

Landing page saat ini terlalu teknis — menampilkan 22 stages dengan deskripsi detail ("Wizard 22 Tahap", "Cara Kerja", hero preview 22-card terminal). Ini **rahasia dapur** yang harus disembunyikan dari publik. Restructure fokus ke:

1. **Branding**: Hero kuat dengan value prop + tagline
2. **Benefit showcase**: Apa yang user dapat (bukan bagaimana caranya)
3. **Use case / social proof**: Untuk siapa produk ini + hasil nyata
4. **Pricing tier**: Free / Pro / Team (kalau applicable)

---

## Current State (Audit)

Landing page existing (`web/src/app/page.tsx`) punya section:
1. **Nav** — OK (brand + nav links + auth buttons)
2. **Hero** — headline OK, tapi description masih sebut "PRD, ERD, hingga master prompt"
3. **Hero preview card** — terminal mockup dengan 22 stage numbered → **RAHASIA DAPUR** ❌
4. **Section Fitur** — 6 feature cards termasuk "Wizard 22 Tahap" dengan detail teknis → **RAHASIA DAPUR** ❌
5. **Section Cara Kerja** — 22 stage list dengan deskripsi per stage → **RAHASIA DAPUR** ❌
6. **Section Platform** — Web App vs Mobile App cards (OK sebagai benefit)
7. **CTA** — OK

**Issue:** Section 3, 4, 5 terlalu teknis — customer tidak perlu tau internal pipeline detail.

---

## Target Structure (Restructured)

### Section baru:
1. **Nav** (existing, OK)
2. **Hero** — refined headline + tagline tanpa sebut "PRD, ERD, master prompt"
3. **Use case showcase** — 3 use case cards (Solo dev, Indie hacker, Agency) dengan benefit per segment
4. **Visual artifact gallery** — 3-4 mockup preview (PRD screenshot, ERD diagram, Design System tokens, Master Prompt snippet) — visual, bukan teknis
5. **Benefit grid** — 6 benefit cards (no mention "22 stages", "API Contract", etc) — fokus outcome
6. **Testimonial / Social proof** — placeholder cards dengan nama + quote + role
7. **Pricing tier** — Free / Pro (placeholder, belum ada payment)
8. **CTA** — refined
9. **Footer** — existing, OK

---

## Phase L — Implementation

### L1 — Hero restructure (hide internal jargon)
**File:** `web/src/app/page.tsx` (Hero section)
**What:** Replace description yang sebut "PRD, ERD, master prompt" dengan benefit-driven copy.
**Implementation:**
- Headline tetap: "Dari satu ide jadi dokumentasi & prompt siap-pakai"
- Subheadline baru: "Cukup jelaskan idemu. AI kami menyusun blueprint lengkap, ERD, dan prompt siap-pakai untuk disuapkan ke AI coding agent favoritmu. Untuk Web, Mobile, atau keduanya."
- **Remove**: "PRD, ERD, hingga master prompt yang saling nyambung"
- **Add**: Visual badge "Untuk Web + Mobile" + "Bawa AI sendiri"

**Verification:**
- [ ] No mention "22 stages" / "wizard 22 tahap" di hero
- [ ] TypeScript clean
- [ ] Update checkpoint

### L2 — Replace section "Cara Kerja" dengan benefit-driven flow
**File:** `web/src/app/page.tsx` (replace section "Cara Kerja")
**What:** Section "Cara Kerja" menampilkan 22 stages dengan deskripsi teknis. Ganti dengan 3-4 step high-level benefit:
- Step 1: "Jelaskan idemu" (visual: form mockup)
- Step 2: "AI klarifikasi kebutuhan" (visual: chat MCQ)
- Step 3: "Blueprint lengkap keluar" (visual: stack of documents)
- Step 4: "Prompt siap-pakai ke AI agent" (visual: copy-to-clipboard)

**TIDAK sebut**: nama stages spesifik (PRD, ERD, design system, dll). Fokus outcome.

**Implementation:**
- Replace existing `<section id="alur">` dengan new benefit-driven flow
- 4 step cards dengan icon + title + 1-sentence benefit
- Visual: gunakan emoji/illustration sederhana, bukan nama teknis
- Background pattern subtle untuk distinguish dari feature section

**Verification:**
- [ ] No stage names di flow section
- [ ] Browser test: visual hierarchy jelas, mobile-responsive
- [ ] Update checkpoint

### L3 — Replace section "Fitur" dengan benefit grid
**File:** `web/src/app/page.tsx` (replace section "Fitur")
**What:** 6 feature cards saat ini termasuk "Wizard 22 Tahap" (terlalu teknis), "ERD Otomatis" (cukup benefit). Restructure jadi 6 benefit cards:
1. **Cepat** — "Dari ide ke blueprint dalam hitungan menit, bukan hari"
2. **Terstruktur** — "Dokumen lengkap yang siap diimplementasi, bukan prompt kosong"
3. **AI-agnostic** — "Hasilkan prompt untuk Claude, GPT, atau Cursor — kamu pilih"
4. **Web + Mobile** — "Satu project, dua track. Konsistensi terjaga."
5. **Iteratif** — "Buat Versi 2, 3 tanpa kehilangan riwayat"
6. **Tanpa biaya** — "Bawa AI provider sendiri, key aman di server"

**Replace**:
- "Wizard 22 Tahap" → "Cepat" (jangan sebut angka stages)
- "ERD Otomatis" → "Terstruktur"
- "Prompt Nyambung" → "AI-agnostic"
- "Tracking Progress" → "Iteratif"
- "Versioning" → tetap (sudah benefit)
- "Bawa AI Sendiri" → "Tanpa biaya"

**Verification:**
- [ ] No mention "22" / "PRD" / "ERD" / "wizard" / "22 Tahap" di feature section
- [ ] Browser test: clean benefit grid
- [ ] Update checkpoint

### L4 — Add Use Case section (for who)
**File:** `web/src/app/page.tsx` (new section between Hero and Showcase)
**What:** Tambah section "Untuk Siapa" dengan 3 use case cards:
1. **Solo Developer** — Icon + title + 1-sentence benefit
2. **Indie Hacker** — Icon + title + 1-sentence benefit
3. **Agency / Tim Kecil** — Icon + title + 1-sentence benefit

**Implementation:**
- Section dengan badge "Built for..."
- 3 cards dengan lucide icon (User, Rocket, Users)
- Visual: gradient background subtle

**Verification:**
- [ ] TypeScript clean
- [ ] Browser test: section visible + responsive
- [ ] Update checkpoint

### L5 — Add Visual Artifact Gallery (replaces hero preview card)
**File:** `web/src/app/page.tsx` (replace terminal preview card)
**What:** Terminal mockup dengan 22 stages terlalu teknis. Ganti dengan **3 visual artifact preview cards**:
1. **PRD Preview** — snippet markdown dengan heading + bullet list (fictional example)
2. **ERD Preview** — simplified React Flow diagram dengan 3-4 nodes (fictional)
3. **Design System Preview** — token cards (color swatches, font samples) fictional

**Tidak sebut**: "Stage 1", "Stage 2", atau nama stage apapun. Visual artifact = hasil, bukan proses.

**Implementation:**
- 3 cards dengan mockup visual
- Real-feeling content tapi fictional (fokus visual, bukan data)
- Tab atau carousel kalau overflow mobile
- Use real `<Card>` component untuk konsistensi

**Verification:**
- [ ] No stage names
- [ ] Browser test: visual gallery scroll smooth
- [ ] Update checkpoint

### L6 — Add Testimonial / Social Proof section
**File:** `web/src/app/page.tsx` (new section)
**What:** Tambah section "Kata Mereka" dengan 3 testimonial cards (placeholder, fictional tapi plausible):
1. Nama + role + company (fictional)
2. Quote singkat tentang value yang didapat
3. Avatar placeholder (initial circle)

**Implementation:**
- Section dengan badge "Testimonial"
- 3 cards dengan `<Card>` component
- Avatar circle dengan initial
- Quote dengan tailwind typography

**Verification:**
- [ ] TypeScript clean
- [ ] Browser test: section visible + responsive
- [ ] Update checkpoint

### L7 — Refine CTA + Footer
**File:** `web/src/app/page.tsx` (CTA + Footer)
**What:** CTA section refine copy dari "Berhenti menulis dokumen manual. Mulai bangun." → lebih benefit-driven. Footer tetap OK.

**Implementation:**
- CTA headline: "Mulai bangun idemu sekarang"
- CTA subline: "Gratis, tanpa kartu kredit. Bawa AI provider sendiri."
- 2 buttons: "Mulai Sekarang" + "Lihat Demo Dashboard"

**Verification:**
- [ ] No mention "22" atau stage teknis
- [ ] Update checkpoint

---

## Checkpoint Tracker

### Phase L — Landing Page Restructure ✅
- [x] L1 — Hero restructure (hide internal jargon) — subheadline benefit-driven tanpa "PRD/ERD/master prompt"
- [x] L2 — Replace section "Cara Kerja" dengan benefit-driven flow — 4 step high-level (Jelaskan → Klarifikasi → Blueprint → Eksekusi)
- [x] L3 — Replace section "Fitur" dengan benefit grid — 6 cards (Cepat, Lengkap, AI-agnostic, Web+Mobile, Iteratif, Bawa AI)
- [x] L4 — Add Use Case section (Untuk Siapa) — Solo Developer / Indie Hacker / Tim Kecil
- [x] L5 — Add Visual Artifact Gallery (replaces hero preview) — 3 preview cards (Dokumentasi, Skema & API, Prompt) tanpa nama stage
- [x] L6 — Add Testimonial / Social Proof section — 3 quotes dengan avatar initial
- [x] L7 — Refine CTA + Footer — CTA "Mulai bangun idemu sekarang" benefit-driven

---

## Verification Workflow

Setiap task:
1. Implementasi code
2. Run typecheck (`npx tsc --noEmit`)
3. Run lint (`npm run lint`)
4. Browser test via MCP Playwright
5. **Jika ada issue**: fix saat itu juga, re-verify
6. Update checkpoint di dokumen ini
7. Lanjut ke task berikutnya

---

## Anti-patterns to Avoid

❌ **JANGAN**:
- Sebut angka stages spesifik (22, 16, etc) di landing public
- Sebut nama stages (PRD, ERD, Design System, App Spec) di section benefit
- Tampilkan terminal mockup dengan stage list
- Pakai jargon teknis yang customer tidak paham
- Klaim yang tidak bisa dibuktikan ("leverage cutting-edge AI")

✅ **BOLEH**:
- Sebut output types (dokumentasi, blueprint, prompt) — ini benefit
- Visual artifact preview — ini showcase hasil
- Use case cards — ini segmentasi user
- Testimonial dengan value konkret
- Tech stack di section platform (Web + Mobile)

---

## File Inventory (Predicted)

**Modified:**
- `web/src/app/page.tsx` — full restructure

**New components (optional):**
- `web/src/components/landing/UseCaseSection.tsx`
- `web/src/components/landing/ArtifactGallery.tsx`
- `web/src/components/landing/TestimonialSection.tsx`
- `web/src/components/landing/BenefitFlow.tsx` (replace Cara Kerja)

Atau inline semua di `page.tsx` untuk simplicity.

**Docs:**
- `docs/34-landing-page-branding.md` — dokumen ini

---

## Estimated Effort

| Phase | Tasks | Est. time |
|-------|-------|-----------|
| L | 7 tasks | 2-3 jam |
| **Total** | **7 tasks** | **2-3 jam** |

---

## Risks & Mitigations

1. **Terlalu generic marketing copy** — landing jadi bland tanpa diferensiasi. Mitigasi: fokus benefit konkret (misal "AI-agnostic — Claude, GPT, atau Cursor"), bukan vague claim.
2. **Testimonial placeholder terasa fake** — placeholder names bisa bikin skeptis. Mitigasi: gunakan nama generic (Budi, Citra, Dani — Indonesian context) + disclaimer "Beta tester" atau pakai design pattern yang honest.
3. **Visual artifact gallery terlalu mirip dashboard** — orang bingung apakah ini real atau mock. Mitigasi: kasih label "Sample output" atau "Preview" subtle.

---

## Final Verification

Setelah semua task selesai:
- [ ] `npx tsc --noEmit` clean
- [ ] `npm run lint` clean
- [ ] Browser test: landing page baru responsive (mobile + desktop)
- [ ] Browser test: no mention "22 stages", "PRD", "ERD", "wizard 22 tahap" di landing public
- [ ] Browser test: benefit-driven copy jelas
- [ ] Screenshot simpan di `.playwright-mcp/` untuk record
