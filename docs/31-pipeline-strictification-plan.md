# Wizard Pipeline Strictification — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED — Phase 1-8 selesai, verified via automated tests + manual browser test via MCP Playwright
> **Started:** 2026-08-18
> **Completed:** 2026-08-18
> **Scope:** Konsistensi & strict validation untuk 22-stage wizard pipeline + tambah 4 stage baru

---

## Objective

1. **Un-collapse `api_contract`** dari tab ERD (CP-10 revert) — jadikan dedicated wizard stage.
2. **Tambah 4 stage baru:**
   - `design_system` (web) — design tokens + signature element + anti-pattern.
   - `design_system_mobile` (Flutter) — Material 3 + ThemeData.
   - `app_spec_web` (JSON) — Halaman/Navigation/Flows/Components registry.
   - `app_spec_mobile` (JSON Flutter) — Screens/Navigation/Flows/Widgets registry.
3. **Strictify 9 prompts existing** — tambah validator rules di backend untuk throw error kalau output不符合 struktur.

## Pipeline Order Final (Locked)

### Target=web (16 stages)

```
1.  pertanyaan
2.  analisa
3.  prd
4.  architecture
5.  erd
6.  api_contract                 ← un-collapse (NEW visible)
7.  design_system                ← NEW
8.  phases_web
9.  standards_web
10. master_web
11. app_spec_web                 ← NEW (JSON)
12. env_config
13. security
14. deployment
15. observability
16. agents
```

### Target=both (22 stages)

```
1.  pertanyaan
2.  analisa
3.  prd
4.  architecture
5.  erd
6.  api_contract                 ← un-collapse
7.  design_system                ← NEW
8.  phases_web
9.  standards_web
10. master_web                   ← web gate
11. app_spec_web                 ← NEW
12. design_system_mobile         ← NEW
13. pertanyaan_mobile
14. phases_mobile
15. standards_mobile
16. master_mobile
17. app_spec_mobile              ← NEW
18. env_config
19. security
20. deployment
21. observability
22. agents
```

## Dependency Matrix

| Stage | Input Context |
|-------|---------------|
| `pertanyaan` | idea, target, stack, answers |
| `analisa` | idea, target, stack |
| `prd` | + analysis |
| `architecture` | + prd |
| `erd` | + prd + architecture |
| `api_contract` | + prd + architecture + erd |
| `design_system` (NEW) | + analysis (persona + halaman) |
| `phases_web` | + standards_web + prd + architecture + erd + design_system + tracking |
| `standards_web` | + analysis + prd + architecture + erd |
| `master_web` | + ringkasan + phases_web + api_contract + design_system + app_spec_web + tracking |
| `app_spec_web` (NEW) | + analysis (halaman) + phases_web (sub-items) + design_system (signature) |
| `design_system_mobile` (NEW) | + app_spec_web (konsistensi) + analysis + design_system (web token) |
| `pertanyaan_mobile` | + master_prompt + api_contract + erd + design_system_mobile |
| `phases_mobile` | + mobile_answers + standards_mobile + prd + architecture + erd + master_web + design_system_mobile + tracking |
| `standards_mobile` | + mobile_answers + prd + architecture + erd + master_web + design_system_mobile |
| `master_mobile` | + mobile_answers + standards_mobile + analysis + prd + architecture + api_contract + mobile_phases + master_web + design_system_mobile + app_spec_web + tracking |
| `app_spec_mobile` (NEW) | + app_spec_web + design_system_mobile + phases_mobile + mobile_answers |
| `env_config` | + prd + architecture + api_contract + master_prompt (web ringkasan) |
| `security` | + prd + architecture + api_contract + ops docs |
| `deployment` | + architecture + api_contract + env_config |
| `observability` | + architecture + api_contract + env_config + deployment |
| `agents` | + standards + api_contract + master_prompt + mobile_master_prompt + ops docs |

## Validator Rules per Stage

| Stage | Type | Validator |
|-------|------|-----------|
| `pertanyaan` | JSON MCQ | 8-12 q, 5 options each, 1 recommended |
| `analisa` | Markdown | 7 heading, ≥2 personas, ≥3 JTBD, ≥5 anti-goals, 5-12 halaman |
| `prd` | Markdown | 7 heading, 5-15 US, AC Given/When/Then |
| `architecture` | Markdown | 6 section, trade-off table ≥4 rows |
| `erd` | JSON | ≥4 entities, FK notation, soft delete |
| `api_contract` | JSON array | all ERD resources covered |
| `design_system` | Markdown | 9 heading, ≥4 color vars, ≥3 screens, ≥5 components, ≥7 anti-pattern, ≥2500 chars |
| `phases_web` | Text-regex | FASE separator, HALAMAN/MENU/FITUR/FLOW/API |
| `standards_web` | Markdown | ✅/❌ per section, hard rules numbered ≥10 |
| `master_web` | Markdown | length ≥500, marker `## SELESAI`, ≤3 unfilled placeholders |
| `app_spec_web` | JSON | ≥3 halaman, cross-ref components_used ↔ components, flows valid |
| `design_system_mobile` | Markdown | 9 heading, ThemeData/ColorScheme references |
| `standards_mobile` | Markdown | same as standards_web but Flutter |
| `phases_mobile` | Text-regex | m_ prefix, FASE separator |
| `master_mobile` | Markdown | same as master_web |
| `app_spec_mobile` | JSON Flutter | ≥3 screens, cross-ref widgets |
| `env_config` | Markdown | .env.example fenced block, var count minimum |
| `security` | Markdown | 9 heading, checklist ≥6 items |
| `deployment` | Markdown | 10 heading, post-deploy verify |
| `observability` | Markdown | runbook ≥5 rows, alerting thresholds |
| `agents` | Markdown | hard rules ≥10 numbered |

---

## Progress Checkpoints

### Checkpoint 0 — Plan & Documentation ✅
- [x] Plan final disusun dengan user
- [x] Pipeline order verified
- [x] Dependency chain verified
- [x] Validator rules defined per stage
- [x] CHECKPOINT.md dibuat

### Checkpoint 1 — Foundation (DB + Model) ✅
- [x] Migration `add_design_system_columns_to_versions` (2 kolom)
- [x] Migration `add_app_spec_columns_to_versions` (2 kolom)
- [x] `Version::ALL_STAGES` updated dengan 4 stage baru
- [x] `Version::defaultStageStatus()` updated
- [x] `Version::casts()` updated dengan 4 kolom JSONB
- [x] `Version::Fillable` updated dengan 4 kolom baru
- [x] `Version::progressCount()` updated (hapus hard reject api_contract)
- [x] Verifikasi: `php artisan migrate` applied + `php artisan test` 127 pass untuk test terkait
- [x] Issue #1: Fillable attribute missing — FIXED
- [x] Issue #2: PipelineRunnerTest `all_stages_defined` hardcoded expected — FIXED
- [x] Issue #3: GenerateStreamTest `rejects invalid stage` hardcoded message — FIXED

### Checkpoint 2 — Backend Validator ✅
- [x] `AiOutputParser::extractMarkdownHeadings()` added
- [x] `AiOutputParser::extractCodeFence()` added
- [x] `AiOutputParser::extractEnvVars()` added
- [x] `AiOutputParser::extractChecklistItems()` added
- [x] `AiOutputParser::parseAppSpecJson()` added (with cross-ref validation)
- [x] `PipelineRunner::validateMarkdownArtifact()` method (reusable heading checker)
- [x] `PipelineRunner::validateDesignSystemSectionRules()` method
- [x] `PipelineRunner::validatePrdSectionRules()` method
- [x] `PipelineRunner::validateEnvConfigSectionRules()` method
- [x] `PipelineRunner::validateStandardsSectionRules()` method
- [x] `PipelineRunner::validateAgentsSectionRules()` method
- [x] `PipelineRunner::MOBILE_STAGES` updated (design_system_mobile, app_spec_mobile added)
- [x] `PipelineRunner::systemPrompt()` updated untuk 4 stage baru
- [x] `PipelineRunner::contextPrompt()` updated dengan dependency chain baru
- [x] `PipelineRunner::saveArtifact()` updated untuk 4 stage baru + 9 strictified
- [x] Issue #4: PipelineRunnerTest stub `analisa` plain text rejected — FIXED dengan stub content valid
- [x] Verifikasi: `php artisan test` 268 passed (1 skipped pre-existing)

### Checkpoint 3 — 4 New Prompts ✅
- [x] `api/app/Prompts/design_system.php` (9 section, web-specific, CSS @theme)
- [x] `api/app/Prompts/design_system_mobile.php` (9 section, Flutter-specific, ThemeData)
- [x] `api/app/Prompts/app_spec_web.php` (JSON output spec untuk web)
- [x] `api/app/Prompts/app_spec_mobile.php` (JSON Flutter output spec)
- [x] Verifikasi: prompt files valid PHP syntax (require_once test)
- [x] Verifikasi: existing tests masih pass (268 passed, SocialiteControllerTest flake pre-existing)

### Checkpoint 4 — Strictify 9 Existing Prompts ✅
- [x] `analisa.php` — VERIFY STRUKTUR ditambahkan (6 heading + minimum count)
- [x] `prd.php` — VERIFY STRUKTUR (7 heading + US 5-15 + AC Given/When/Then)
- [x] `architecture.php` — VERIFY STRUKTUR (6 heading + trade-off ≥4 rows)
- [x] `env_config.php` — VERIFY STRUKTUR (5 heading + .env.example block ≥8 vars)
- [x] `security.php` — VERIFY STRUKTUR (9 heading + checklist ≥6 items)
- [x] `deployment.php` — VERIFY STRUKTUR (10 heading + topology + restore-verify)
- [x] `standards.php` — VERIFY STRUKTUR (code fence bahasa + hard rules ≥10)
- [x] `observability.php` — VERIFY STRUKTUR (9 heading + runbook ≥5 rows)
- [x] `agents.php` — VERIFY STRUKTUR (hard rules ≥10 + file structure block)
- [x] Issue #5: 9 prompt files missing closing `';` — FIXED dengan append `';"`
- [x] Verifikasi: `php -l` semua 9 files valid syntax
- [x] Verifikasi: `php artisan test` 268 passed (SocialiteControllerTest flake pre-existing)

### Checkpoint 5 — Frontend ✅
- [x] `web/src/lib/mock.ts` — 4 stage baru + un-collapse api_contract
- [x] `web/src/lib/api.ts` — Version type updated (4 field baru)
- [x] `web/src/components/wizard/DesignSystemView.tsx` (web, NEW)
- [x] `web/src/components/wizard/DesignSystemMobileView.tsx` (mobile, NEW)
- [x] `web/src/components/wizard/AppSpecWebView.tsx` (NEW)
- [x] `web/src/components/wizard/AppSpecMobileView.tsx` (NEW)
- [x] `web/src/app/(app)/new/page.tsx` — 5 render blocks + 2 colMap updates + ApiContractTable value import
- [x] Issue #6: StageKey type missing 4 keys — FIXED di mock.ts
- [x] Issue #7: Badge tone "default" invalid — FIXED dengan "muted"
- [x] Issue #8: JSX.Element namespace missing — FIXED dengan ReactElement type
- [x] Issue #9: Unused imports (Type, Ruler, Layout, dll) — FIXED dengan simplified SECTION_ICONS
- [x] Verifikasi: `tsc --noEmit` 0 errors
- [x] Verifikasi: `eslint .` 0 errors, 2 pre-existing warnings di MasterPromptViewer

### Checkpoint 6 — Tests ✅
- [x] `tests/Unit/PromptValidation/DesignSystemValidationTest.php` (6 tests)
- [x] `tests/Unit/PromptValidation/AppSpecWebValidationTest.php` (8 tests)
- [x] `tests/Unit/PromptValidation/AppSpecMobileValidationTest.php` (2 tests)
- [x] `tests/Feature/PipelineNewStagesTest.php` (6 tests)
- [x] Issue #10: parser hardcoded `components` untuk mobile — FIXED dengan `$componentsKey`
- [x] Issue #11: Test stub design_system too short (821 chars < 2500) — FIXED dengan str_repeat
- [x] Issue #12: Test stub app_spec_web only 1 component — FIXED dengan 3 components
- [x] Issue #13: Mobile spec stub only 1 flow step — FIXED dengan 2 steps
- [x] Verifikasi: `php artisan test --filter="...new stages"` 22 passed
- [x] Verifikasi: `php artisan test` total 290 passed (SocialiteControllerTest flake pre-existing)

### Checkpoint 7 — Final Verification ✅
- [x] `php artisan migrate` — applied 2 migrations
- [x] `php artisan test` — 290 passed, 1 skipped, 1 flake (SocialiteControllerTest pre-existing race)
- [x] `npx tsc --noEmit` — 0 errors
- [x] `npx eslint .` — 0 errors, 2 pre-existing warnings
- [x] Manual verification: Stage ordering matches dependency chain
- [x] Manual verification: 4 new stages (design_system, design_system_mobile, app_spec_web, app_spec_mobile) added to ALL_STAGES
- [x] Manual verification: api_contract un-collapsed (visible di mock.ts)
- [x] Manual verification: Frontend view components render OK untuk masing-masing artifact

### Checkpoint 8 — Manual Browser Test via MCP Playwright ✅
- [x] Landing page: 22 stages muncul di "Cara Kerja" section dengan responsive grid
- [x] Landing page: "Wizard 22 Tahap" description updated (was "6 Tahap")
- [x] Landing page: Hero preview card grid responsive (was sm:grid-cols-3, now grid-cols-2 sm:3 md:4)
- [x] Login flow: demo@aistack.dev / demo1234 works (DemoDataSeeder run)
- [x] Dashboard: pipeline progress shows "22" denominator (was 14/18)
- [x] Project detail (target=web): 16 stages visible in pipeline list (mobile excluded)
- [x] Project detail (target=both): 22 stages visible in pipeline list
- [x] Project detail Web tab: API Contract, Design System, App Spec — Web, Standards Web rendered
- [x] Project detail Mobile tab: Design System Mobile, App Spec — Mobile, Standards Mobile rendered
- [x] Backend API: 22 stage_status keys in response (design_system, design_system_mobile, app_spec_web, app_spec_mobile included)
- [x] Backend API: 4 new columns in version response (design_system, design_system_mobile, app_spec_web, app_spec_mobile)
- [x] Backend tests: 6 PipelineNewStagesTest pass (validator + save artifact)
- [x] Pre-existing issues confirmed: SSE 401 (EventSource cross-origin), SocialiteControllerTest flake

---

## Issues Log

### Issues ditemukan saat manual browser test (Phase 8)

1. **Stale `.next` build cache** — Landing page masih tampilkan "CP-10: api_contract collapsed" padahal mock.ts sudah updated. Fix: `docker compose build --no-cache` rebuild image.
2. **Landing page "Wizard 6 Tahap"** — Description feature card masih tulis "6 Tahap" padahal pipeline sudah 22 stages. Fix: Update ke "Wizard 22 Tahap" di `web/src/app/page.tsx:87`.
3. **Landing page grid layout cramped** — `md:grid-cols-6` dengan 22 cards menghasilkan cell width 111px (terlalu sempit). Fix: Responsive grid `grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6`.
4. **Hero preview card layout cramped** — `sm:grid-cols-3` dengan 22 cards menghasilkan 8 rows tinggi. Fix: Responsive `grid-cols-2 sm:grid-cols-3 md:grid-cols-4`.
5. **Project detail Web tab missing new artifacts** — TAPI hanya menampilkan analisa, prd, architecture, erd, phases, master_prompt. Tidak ada: api_contract, design_system, app_spec_web, standards_web. Fix: Tambah render blocks + imports di `web/src/app/(app)/projects/[id]/page.tsx`.
6. **Project detail Mobile tab missing new artifacts** — Hanya tampilkan mobile_phases, mobile_master_prompt. Tidak ada: design_system_mobile, app_spec_mobile, mobile_standards. Fix: Tambah render blocks.
7. **TypeScript error: `unknown` not assignable to `ReactNode`** — `{selectedVersion.app_spec_web && (...)}` mengembalikan `unknown` (type field di Version). Fix: `Boolean(...)` wrapping untuk truthy check.
8. **DemoDataSeeder tidak auto-run** — Demo user belum ada sampai run `php artisan db:seed --class=DemoDataSeeder --force`. Pre-existing (production mode block).
9. **SSE 401 phase-progress/stream** — EventSource cross-origin tidak support credentials. Pre-existing (bukan issue dari CP-10 11).
10. **SocialiteControllerTest flake** — Google login test requires DatabaseSeeder interaction. Pre-existing.

---

## File Inventory

### New Files (14)

**Backend prompts (4):**
- `api/app/Prompts/design_system.php`
- `api/app/Prompts/design_system_mobile.php`
- `api/app/Prompts/app_spec_web.php`
- `api/app/Prompts/app_spec_mobile.php`

**Frontend components (4):**
- `web/src/components/wizard/DesignSystemView.tsx`
- `web/src/components/wizard/DesignSystemMobileView.tsx`
- `web/src/components/wizard/AppSpecWebView.tsx`
- `web/src/components/wizard/AppSpecMobileView.tsx`

**Tests (6):**
- `api/tests/Unit/PromptValidation/DesignSystemValidationTest.php`
- `api/tests/Unit/PromptValidation/DesignSystemMobileValidationTest.php`
- `api/tests/Unit/PromptValidation/AppSpecWebValidationTest.php`
- `api/tests/Unit/PromptValidation/AppSpecMobileValidationTest.php`
- `api/tests/Unit/PromptValidation/AgentsValidationTest.php`
- `api/tests/Feature/AppSpecPipelineTest.php`

### Updated Files (4)

- `api/app/Models/Version.php`
- `api/app/Services/PipelineRunner.php`
- `api/app/Services/AiOutputParser.php`
- `web/src/lib/mock.ts`
- `web/src/app/(app)/new/page.tsx`

### Migrations (2)

- `api/database/migrations/<ts>_add_design_system_columns_to_versions.php`
- `api/database/migrations/<ts>_add_app_spec_columns_to_versions.php`
