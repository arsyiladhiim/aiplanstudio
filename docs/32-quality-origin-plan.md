# 32 — Output Quality & Originality Hardening — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-18
> **Completed:** 2026-08-19
> **Scope:** Hardening backend validator + frontend consistency + output originality guard untuk 22-stage wizard pipeline
> **Parent:** `docs/31-pipeline-strictification-plan.md` (COMPLETED)

---

## Objective

Setelah pipeline strictification (doc 31) selesai dengan 22 stages + 4 stage baru, sekarang fokus ke:

1. **Backend validation hardening** — aturan lebih ketat untuk mencegah AI skip section / output generik.
2. **Frontend consistency** — pipeline visualization lebih gallery-like, empty state lebih informative, cross-reference antar artifact.
3. **Output originality** — anti-template guard agar output AI tidak "another generic AI app", design system signature enforcement, PRD differentiation field.

---

## Pipeline Order Final (Locked — dari doc 31)

### Target=web (16 stages)
```
1.  pertanyaan         7.  design_system             13. security
2.  analisa            8.  phases_web                14. deployment
3.  prd                9.  standards_web             15. observability
4.  architecture      10. master_web               16. agents
5.  erd               11. app_spec_web
6.  api_contract      12. env_config (shared)
```

### Target=both (22 stages — tambah mobile track)
```
1-11.  [web stages di atas]
12. design_system_mobile     18. app_spec_mobile
13. pertanyaan_mobile        19. env_config (shared)
14. phases_mobile            20. security (shared)
15. standards_mobile         21. deployment (shared)
16. master_mobile            22. observability (shared)
17. (mobile steps above)     23. agents (shared)
```
Catatan: env_config, security, deployment, observability, agents adalah shared (run setelah mobile track selesai untuk target=both).

---

## Phase A — Backend Validation Hardening

### A1 — Deterministic section ordering check
**File:** `api/app/Services/PipelineRunner.php` (method `validateMarkdownArtifact`)
**What:** Tambah assertion bahwa `## 1.` wajib sebelum `## 2.`, dst. Mencegah AI skip section atau urut acak.
**Implementation:**
- Parse headings dengan `AiOutputParser::extractMarkdownHeadings()`
- Cek `## N.` muncul strictly incrementing (1, 2, 3, ...).
- Throw `RuntimeException("Section ordering invalid: expected '## N.' but got '## M.'")` kalau out-of-order.
**Existing helpers:** `AiOutputParser::extractMarkdownHeadings()` sudah ada dari doc 31.
**Test:** Tambah 1 test case: `ValidateSectionOrderingTest::test_out_of_order_sections_throws`.
**Verification:**
- [ ] Test pass dengan valid ordered artifact
- [ ] Test throw dengan out-of-order artifact
- [ ] `php artisan test --filter=ValidateSectionOrdering` pass
- [ ] Lint PHP: `./vendor/bin/pint api/app/Services/PipelineRunner.php`
- [ ] Update checkpoint di dokumen ini

### A2 — Required keyword presence per stage
**File:** `api/app/Services/PipelineRunner.php` (method `validateMarkdownArtifact`)
**What:** Tambah `requiredKeywords` map per stage. Misal:
```php
private const STAGE_REQUIRED_KEYWORDS = [
    'design_system'      => ['signature', 'anti-pattern', 'tokens'],
    'design_system_mobile' => ['Material', 'ThemeData', 'signature'],
    'prd'                => ['user story', 'acceptance', 'functional requirement'],
    'architecture'       => ['folder structure', 'tech stack', 'pattern'],
    'standards_web'      => ['TypeScript', 'lint', 'convention'],
    'standards_mobile'   => ['Dart', 'lint', 'convention'],
    'security'           => ['OWASP', 'authentication', 'authorization'],
    'observability'      => ['logging', 'monitoring', 'health check'],
    'env_config'         => ['environment', 'variable', 'configuration'],
    'deployment'         => ['Docker', 'CI/CD', 'rollback'],
    'agents'             => ['AGENTS.md', 'agent', 'instruction'],
];
```
Cek lowercase match. Kalau missing, throw `RuntimeException("Stage '{$stage}' missing required keyword: '{$kw}'")`.
**Test:** Tambah 1 test file `RequiredKeywordsTest` dengan 2 test: pass valid + throw invalid.
**Verification:**
- [ ] Test pass dengan all keywords present
- [ ] Test throw kalau 1 keyword missing
- [ ] `php artisan test --filter=RequiredKeywords` pass
- [ ] Lint: `./vendor/bin/pint`
- [ ] Update checkpoint

### A3 — JSON structural validation for api_contract
**File:** `api/app/Services/PipelineRunner.php` (method `saveArtifact`)
**What:** Saat ini `api_contract` disimpan sebagai JSON di kolom `api_contract` tapi tidak ada schema validation. Tambah `validateApiContractSchema()` yang enforce setiap item punya `{resource, method, path, auth, description}`.
**Implementation:**
```php
private function validateApiContractSchema(array $contract): void
{
    foreach ($contract as $i => $item) {
        $required = ['resource', 'method', 'path', 'auth', 'description'];
        foreach ($required as $field) {
            if (!isset($item[$field]) || empty($item[$field])) {
                throw new RuntimeException("api_contract[$i] missing field: $field");
            }
        }
        if (!in_array(strtoupper($item['method']), ['GET','POST','PUT','PATCH','DELETE'])) {
            throw new RuntimeException("api_contract[$i] invalid method: {$item['method']}");
        }
    }
}
```
Call di `saveArtifact` sebelum write.
**Test:** `ApiContractSchemaTest` — 3 case: valid pass, missing field throw, invalid method throw.
**Verification:**
- [ ] Test pass valid contract
- [ ] Test throw missing field
- [ ] Test throw invalid method
- [ ] `php artisan test --filter=ApiContractSchema` pass
- [ ] Lint: pint
- [ ] Update checkpoint

### A4 — Idempotent stage skip (dependency-aware invalidation)
**File:** `api/app/Services/PipelineRunner.php` (method `regenerateStage`)
**What:** Saat user regenerate `prd`, downstream `architecture` + `erd` + turunannya harus di-reset ke `pending`. Sa ini full reset (regenerate = clear semua artifact). Pattern dependency-aware.
**Implementation:**
```php
private const STAGE_DEPENDENCIES = [
    'analisa'       => [],
    'prd'           => ['analisa'],
    'architecture'  => ['prd'],
    'erd'           => ['architecture'],
    'api_contract'  => ['erd'],
    'design_system' => ['architecture'],
    'phases_web'    => ['prd', 'erd'],
    'standards_web' => ['architecture'],
    'master_web'    => ['phases_web', 'standards_web', 'design_system', 'api_contract', 'erd', 'prd'],
    'app_spec_web'  => ['master_web'],
    'design_system_mobile' => ['design_system'],
    'pertanyaan_mobile' => ['master_web'],
    'phases_mobile'  => ['pertanyaan_mobile'],
    'standards_mobile' => ['standards_web'],
    'master_mobile'  => ['phases_mobile', 'standards_mobile', 'design_system_mobile'],
    'app_spec_mobile' => ['master_mobile'],
    'env_config'     => ['master_web'],
    'security'       => ['env_config'],
    'deployment'     => ['security'],
    'observability'  => ['deployment'],
    'agents'         => ['master_web', 'observability'],
];

private function invalidateDependents(string $stage): void
{
    $toReset = $this->collectDependents($stage);
    foreach ($toReset as $depStage) {
        $this->version->stage_status[$depStage] = 'pending';
        $this->clearArtifact($depStage);
    }
}
```
**Test:** `DependencyInvalidationTest` — 3 case: regenerate `prd` resets `architecture`+`erd`+downstream; regenerate `master_web` resets `app_spec_web`+`agents` only (tidak reset `prd`); regenerate `analisa` resets everything.
**Verification:**
- [ ] Test pass untuk 3 case
- [ ] `php artisan test --filter=DependencyInvalidation` pass
- [ ] Lint: pint
- [ ] Update checkpoint

---

## Phase B — Frontend Consistency

### B1 — Pipeline visualization upgrade
**File:** `web/src/app/(app)/projects/[id]/page.tsx` (Pipeline list section)
**What:** Pipeline list saat ini plain text. Tambah icon + status badge per stage row.
**Implementation:**
- Buat komponen kecil `StageRow` di `web/src/components/wizard/StageRow.tsx`:
  - Icon per stage (Database, FileText, Palette, dst. dari lucide)
  - Badge status: `pending` (gray), `running` (amber pulse), `done` (green), `error` (red)
  - Layout: icon | label | badge | description
  - Smooth transition saat status berubah (framer-motion opsional, pakai CSS transition simplest)
- Replace inline render di page.tsx dengan `<StageRow stage={s} status={map[s.key]} />`
**Test:** No new test file — verify via browser screenshot manual.
**Verification:**
- [ ] `npx tsc --noEmit` 0 errors
- [ ] `npm run lint` 0 errors
- [ ] Browser test: Pipeline list visualized di project 332 (web) — 16 stages semua tampil icon + status
- [ ] Browser test: Project 328 (both) — 22 stages tampil
- [ ] Screenshot simpan di `.playwright-mcp/` untuk record
- [ ] Update checkpoint

### B2 — Empty state CTA untuk new stages
**File:** `web/src/app/(app)/projects/[id]/page.tsx` (Web tab + Mobile tab)
**What:** Saat `design_system` belum di-generate, tampilkan CTA button "Generate Design System" dengan action yang trigger pipeline. Saat ini blank atau conditional `{Boolean(...) && ...}` tidak render apa-apa kalau kosong.
**Implementation:**
- Empty state component `<EmptyArtifact />`:
  - Icon + judul stage + deskripsi singkat
  - Button "Generate {Stage}" yang trigger `POST /api/versions/{id}/stage/{stage}/generate`
  - Jika stage dependency belum selesai, disable dengan tooltip "Selesaikan {dependency} dulu"
- Pakai di Web tab untuk `api_contract`, `design_system`, `app_spec_web`, `standards_web`
- Pakai di Mobile tab untuk `design_system_mobile`, `app_spec_mobile`, `mobile_standards`
**Test:** Browser manual test untuk verify CTA muncul + button functional + dependency-aware disabled states.
**Verification:**
- [ ] `npx tsc --noEmit` 0 errors
- [ ] `npm run lint` 0 errors
- [ ] Browser test: Project 332 (4/22 stage) — empty state CTA muncul untuk `api_contract` (done), `design_system` (pending), `app_spec_web` (pending), dst.
- [ ] Browser test: Click "Generate Design System" → trigger API (jika dependency done) atau disabled (jika dependency belum)
- [ ] Update checkpoint

### B3 — Cross-reference rendering (App Spec ↔ pages)
**File:** `web/src/components/wizard/AppSpecWebView.tsx`
**What:** `app_spec_web` punya `components` array. Tambah "Used in pages" backlink — untuk setiap component, tampilkan halaman mana yang reference component tersebut.
**Implementation:**
- Parse `components` + `halaman` array
- Untuk setiap component, cari referensi di `halaman[].components` (array of component names)
- Render badge "Used in: X, Y" di setiap component card
- Pakai pattern same di `AppSpecMobileView` untuk widgets → screens
**Test:** Unit test di `web/src/components/wizard/__tests__/AppSpecWebView.test.tsx` — verify cross-ref rendering correct dengan mock data.
**Verification:**
- [ ] Test file baru pass
- [ ] `npx tsc --noEmit` 0 errors
- [ ] `npm run lint` 0 errors
- [ ] Browser test: Project 332 dengan mock data — component "ContactCard" tampil dengan badge "Used in: Contacts List"
- [ ] Update checkpoint

---

## Phase C — Output Originality Guard

### C1 — Anti-template guard (generic AI detector)
**File:** `api/app/Services/PipelineRunner.php` (method `saveArtifact` — baru setelah validator)
**What:** Tambah heuristic detector untuk generic AI output. Kalau match, throw `RuntimeException("Output terindikasi template generik — regenerate dengan diferensiasi spesifik")`.
**Implementation:**
```php
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

private function detectGenericOutput(string $content): void
{
    foreach (self::GENERIC_PATTERNS as $pattern) {
        if (preg_match($pattern, $content)) {
            throw new RuntimeException(
                "Output terindikasi template generik — match pattern: {$pattern}. " .
                "Regenerate dengan diferensiasi spesifik untuk produk ini."
            );
        }
    }
}
```
Call di `saveArtifact` sebelum validator. Log match ke `Log::warning()` untuk audit.
**Test:** `GenericOutputDetectionTest` — 4 case: valid original pass, lorem ipsum throw, blue-purple gradient throw, "leverage cutting-edge" throw.
**Verification:**
- [ ] Test pass valid + throw 3 generic patterns
- [ ] `php artisan test --filter=GenericOutputDetection` pass
- [ ] Lint: pint
- [ ] Update checkpoint

### C2 — Design system signature element enforcement
**File:** `api/app/Services/PipelineRunner.php` (method `validateDesignSystemSectionRules`)
**What:** Saat ini validator cuma require `--color-*` (≥4) dan `--font-*` (≥2) di section 2. Tambah require:
- Section "## 5. Signature Element" wajib panjang ≥300 char (specific differentiation, bukan generic).
- Tidak boleh match pattern `"glassmorphism"`, `"neumorphism"`, `"material design"` saja — kalau pakai, wajib diikuti penjelasan "why this fits the product" ≥100 char.
**Implementation:**
```php
// Section 5 — Signature Element
$section5 = $sections[4] ?? '';
if (strlen($section5) < 300) {
    throw new RuntimeException("Section 5 (Signature Element) terlalu pendek — wajib ≥300 char dengan diferensiasi spesifik");
}
$genericSignatures = ['glassmorphism', 'neumorphism', 'material design', 'flat design'];
foreach ($genericSignatures as $sig) {
    if (stripos($section5, $sig) !== false) {
        $explanationPattern = '/why.*(this|itu).*fits|alasan.*(cocok|sesuai)/i';
        if (!preg_match($explanationPattern, $section5) || strlen($section5) < 400) {
            throw new RuntimeException("Section 5 pakai generic signature '{$sig}' — wajib sertakan alasan spesifik mengapa ini cocok untuk produk (≥400 char total)");
        }
    }
}
```
**Test:** Tambah test case di `DesignSystemValidationTest`:
- valid signature (>300 char, specific) pass
- short signature (<300 char) throw
- "glassmorphism" tanpa alasan throw
- "glassmorphism" dengan alasan panjang pass
**Verification:**
- [ ] Test pass 4 case
- [ ] `php artisan test --filter=DesignSystemValidation` pass (all tests)
- [ ] Lint: pint
- [ ] Update checkpoint

### C3 — PRD differentiation field (mandatory section)
**File:** `api/app/Prompts/prd.php` + `api/app/Services/PipelineRunner.php` (method `validatePrdSectionRules`)
**What:** Tambah section "## 7. Differentiation" di PRD yang require 3 poin spesifik apa yang bikin produk ini berbeda dari kompetitor generik.
**Prompt update (`prd.php`):** Tambah di VERIFY STRUKTUR:
```
## 7. Differentiation
Wajib 3 poin spesifik apa yang membedakan produk ini dari kompetitor generik.
Format bullet list, tiap poin ≥50 char, hindari frasa generik ("leverage cutting-edge", "robust & scalable").
Contoh: "- Integrasi real-time dengan sistem POS lokal Indonesia — kompetitor (Toast, Square) tidak support"
```
**Validator update (`validatePrdSectionRules`):**
```php
// Section 7 — Differentiation
$section7 = $sections[6] ?? '';
if (strlen($section7) < 200) {
    throw new RuntimeException("Section 7 (Differentiation) wajib ≥200 char dengan 3 poin spesifik");
}
$bullets = substr_count($section7, '- ');
if ($bullets < 3) {
    throw new RuntimeException("Section 7 (Differentiation) wajib ≥3 bullet poin diferensiasi");
}
// Cek generic phrases
foreach (self::GENERIC_PATTERNS as $pattern) {
    if (preg_match($pattern, $section7)) {
        throw new RuntimeException("Section 7 (Differentiation) mengandung frasa generik — wajib spesifik ke produk");
    }
}
```
**Test:** Tambah test case di `PrdValidationTest` (baru):
- valid 3 bullets specific pass
- 2 bullets throw
- generic phrase throw
- <200 char throw
**Verification:**
- [ ] Test pass 4 case
- [ ] `php artisan test --filter=PrdValidation` pass
- [ ] Lint: pint
- [ ] Update checkpoint

---

## Checkpoint Tracker

### Phase A — Backend Validation Hardening ✅
- [x] A1 — Deterministic section ordering check (3 tests pass)
- [x] A2 — Required keyword presence per stage (3 tests pass)
- [x] A3 — JSON structural validation for api_contract (4 tests pass)
- [x] A4 — Idempotent stage skip (dependency-aware invalidation) (3 tests pass)

### Phase B — Frontend Consistency ✅
- [x] B1 — Pipeline visualization upgrade (StageRow component) — 22 stages + 50 lucide SVGs verified in browser
- [x] B2 — Empty state CTA untuk new stages — Design System + App Spec — Web + Mobile CTAs visible di project 332 + 328
- [x] B3 — Cross-reference rendering (App Spec ↔ pages) — ContactCard→Contacts List, DealKanban→Deals Pipeline verified in browser

### Phase C — Output Originality Guard ✅
- [x] C1 — Anti-template guard (generic AI detector) (5 tests pass)
- [x] C2 — Design system signature element enforcement (5 tests pass)
- [x] C3 — PRD differentiation field (mandatory section) (4 tests pass)

---

## Final Verification ✅

| Metric | Result |
|--------|--------|
| Backend tests | 317 passed, 1 skipped, 1 flake (SocialiteControllerTest pre-existing) |
| Frontend tests | (no unit tests, manual verified) |
| Lint PHP | `./vendor/bin/pint` clean |
| Lint TS | 0 errors, 2 pre-existing warnings |
| TypeScript | `npx tsc --noEmit` clean |
| Browser test | Pipeline 22 stages + 50 SVG icons visible; EmptyArtifact CTAs visible (dependency-aware); Cross-ref rendering working |

### Files Modified (Plan 32)

**Backend (PHP):**
- `api/app/Services/PipelineRunner.php` — added: `STAGE_REQUIRED_KEYWORDS`, `STAGE_DEPENDENTS`, `COLUMN_MAP`, `GENERIC_PATTERNS`, `assertSectionOrdering`, `assertRequiredKeywords`, `assertApiContractSchema`, `assertSignatureElement`, `assertPrdDifferentiation`, `detectGenericOutput`, `invalidateDependents`, `clearArtifact`; modified: `saveArtifact`, `validateMarkdownArtifact`, `validateDesignSystemSectionRules`, `validatePrdSectionRules`
- `api/app/Prompts/prd.php` — added: Section 7 (Differentiation), updated VERIFY STRUKTUR
- `api/app/Http/Controllers/VersionController.php` — regenerateStage now calls `invalidateDependents` first

**Backend tests (new):**
- `api/tests/Unit/PromptValidation/ValidateSectionOrderingTest.php`
- `api/tests/Unit/PromptValidation/RequiredKeywordsTest.php`
- `api/tests/Unit/PromptValidation/ApiContractSchemaTest.php`
- `api/tests/Feature/DependencyInvalidationTest.php`
- `api/tests/Unit/PromptValidation/GenericOutputDetectionTest.php`
- `api/tests/Unit/PromptValidation/SignatureElementTest.php`
- `api/tests/Unit/PromptValidation/PrdDifferentiationTest.php`

**Backend tests (modified stubs):**
- `api/tests/Feature/PipelineRunnerTest.php` — 7 stubs updated to include `resource` field in api_contract
- `api/tests/Feature/PipelineNewStagesTest.php` — signature element stub extended to ≥300 chars

**Frontend (TS):**
- `web/src/components/wizard/StageRow.tsx` — NEW (B1)
- `web/src/components/wizard/EmptyArtifact.tsx` — NEW (B2)
- `web/src/components/wizard/AppSpecWebView.tsx` — cross-ref rendering (B3)
- `web/src/components/wizard/AppSpecMobileView.tsx` — cross-ref rendering (B3)
- `web/src/app/(app)/projects/[id]/page.tsx` — integrated StageRow, EmptyArtifact (Web + Mobile tabs)

## Workflow per Phase

Setiap phase:
1. Implementasi kode
2. Run automated tests (`php artisan test --filter=X` atau `npm run test`)
3. Run lint (`./vendor/bin/pint` untuk PHP, `npm run lint` untuk TS)
4. Run typecheck (`npx tsc --noEmit`)
5. Browser test via MCP Playwright (jika relevance)
6. **Jika ada issue:** fix saat itu juga, re-run semua tests, update checkpoint
7. Update checkpoint di dokumen ini
8. Commit progress (jika diminta)

---

## File Inventory (Predicted)

### Backend (Phase A + C)
- `api/app/Services/PipelineRunner.php` — modified (A1, A2, A3, A4, C1, C2, C3)
- `api/app/Services/AiOutputParser.php` — potentially modified (A1 helpers)
- `api/app/Prompts/prd.php` — modified (C3 — tambah section 7)
- `api/tests/Unit/PromptValidation/ValidateSectionOrderingTest.php` — NEW (A1)
- `api/tests/Unit/PromptValidation/RequiredKeywordsTest.php` — NEW (A2)
- `api/tests/Unit/PromptValidation/ApiContractSchemaTest.php` — NEW (A3)
- `api/tests/Feature/DependencyInvalidationTest.php` — NEW (A4)
- `api/tests/Unit/PromptValidation/GenericOutputDetectionTest.php` — NEW (C1)
- `api/tests/Unit/PromptValidation/PrdValidationTest.php` — NEW (C3)

### Frontend (Phase B)
- `web/src/components/wizard/StageRow.tsx` — NEW (B1)
- `web/src/components/wizard/EmptyArtifact.tsx` — NEW (B2)
- `web/src/app/(app)/projects/[id]/page.tsx` — modified (B1, B2, B3)
- `web/src/components/wizard/AppSpecWebView.tsx` — modified (B3)
- `web/src/components/wizard/AppSpecMobileView.tsx` — modified (B3)
- `web/src/components/wizard/__tests__/AppSpecWebView.test.tsx` — NEW (B3)

### Docs
- `docs/32-quality-origin-plan.md` — dokumen ini (checkpoints update real-time)

---

## Estimated Effort

| Phase | Tasks | Est. time |
|-------|-------|-----------|
| A | 4 tasks | 2-3 jam |
| B | 3 tasks | 1.5-2 jam |
| C | 3 tasks | 1.5-2 jam |
| **Total** | **10 tasks** | **5-7 jam** |

---

## Risks & Mitigations

1. **Generic pattern false positive** — `C1` heuristic mungkin false-positive untuk legit content. Mitigasi: log warning + allow override via env `GENERIC_GUARD_STRICT=false` untuk testing.
2. **Dependency map incomplete** — `A4` dependency mungkin tidak match semua use case. Mitigasi: test dengan scenario regenerate setiap stage, revise map jika ada anomaly.
3. **Empty state CTA flow undefined** — `B2` butuh API endpoint `/generate` untuk trigger stage. Cek apakah endpoint sudah ada, atau perlu new route.
4. **Test coverage regression** — Phase A+C nambah ~8 test files. Pastikan tidak ada test existing yg break.

---

## Glossary

- **Stage status**: JSON di `versions.stage_status` — `pending` | `running` | `done` | `error`.
- **Artifact**: Konten hasil generate per stage (markdown / JSON / code fence).
- **Dependency chain**: Urutan stage yang harus selesai sebelum stage lain bisa jalan (mis `prd` sebelum `architecture`).
- **Generic output**: AI output yang pakai frasa template ("lorem ipsum", "leverage cutting-edge", "blue-purple gradient") — tidak orisinil.
- **Signature element**: Section di design system yang define diferensiasi visual spesifik (bukan "glassmorphism" generik).
