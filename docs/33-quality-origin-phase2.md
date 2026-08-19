# 33 — Quality Origin Phase 2 — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-18
> **Completed:** 2026-08-19
> **Scope:** Workflow improvements (D) + Quality scoring (E) + Operational hardening (F)
> **Parent:** `docs/32-quality-origin-plan.md` (COMPLETED Phase A+B+C)

---

## Objective

Setelah Phase A (validator) + B (frontend consistency) + C (originality guard) selesai, lanjut ke:
- **D — Workflow**: skip justification, diff view, export ZIP
- **E — Quality scoring**: per-stage score + dashboard originality index
- **F — Operational**: test fixtures untuk 9 prompts existing, migration rollback smoke test, CSP fix

---

## Phase D — Workflow Improvements

### D1 — Stage skip justification (audit trail)
**File:** `api/app/Models/Version.php` (fillable + casts) + `api/app/Services/PipelineRunner.php`
**What:** Saat user skip stage (lewat `cancelStage` atau mark as skipped), wajib isi alasan. Track di field baru `skip_reasons` JSON (nullable).
**Implementation:**
- Migration: `add_skip_reasons_to_versions` (`jsonb` nullable)
- `Version::$fillable` tambah `'skip_reasons'`
- `Version::$casts` tambah `'skip_reasons' => 'array'`
- `PipelineRunner::cancelStage($stage, $reason)` — store reason ke skip_reasons[stage]
- Frontend: tombol "Skip" di StageRow → modal prompt untuk alasan
**Test:** `SkipReasonTest` — store + retrieve via model
**Verification:**
- [ ] Migration applied
- [ ] Test pass
- [ ] Lint pint
- [ ] Update checkpoint

### D2 — Diff view between versions (wire from UI)
**File:** `web/src/app/(app)/projects/[id]/page.tsx` (sudah ada diff route dari cp_progress)
**What:** Project detail page sudah ada tab "diff" atau button "Lihat Diff" di version picker. Wire tombol "Compare dengan versi sebelumnya" yang trigger side-by-side diff per stage.
**Implementation:**
- API endpoint `GET /api/versions/{id}/diff?with={other_version_id}` — return diff per stage
- Frontend: di version selector dropdown, tombol "Compare" → modal dengan stage-by-stage diff (PRD v1 vs v2)
- Diff display: simple side-by-side text comparison dengan highlight berbeda
**Test:** `DiffEndpointTest` — verify response shape
**Verification:**
- [ ] Backend tests pass
- [ ] TypeScript clean
- [ ] Browser test: version dropdown muncul "Compare" option + modal diff
- [ ] Update checkpoint

### D3 — Export original ZIP
**File:** `api/app/Http/Controllers/VersionController.php` (new method `exportZip`)
**What:** Endpoint `GET /api/versions/{id}/export-zip` — bundle semua artifact jadi ZIP. Tiap prompt sudah define filename via `## Filename:` convention.
**Implementation:**
- Backend: kumpulkan semua artifact fields (PRD.md, ARCHITECTURE.md, DESIGN_SYSTEM.md, STANDARDS.md, AGENTS.md, app_spec_web.json, app_spec_mobile.json, dll) → buat ZIP via `ZipArchive`
- Filename per artifact mapping di `PipelineRunner::ARTIFACT_FILENAMES` const
- Auth required (ownership check)
- Frontend: tombol "Export ZIP" di project detail page (sudah ada Export group) → trigger download
**Test:** `ZipExportTest` — verify ZIP contains expected files
**Verification:**
- [ ] Backend tests pass
- [ ] Browser test: klik Export ZIP → file downloaded dengan content benar
- [ ] Update checkpoint

---

## Phase E — Quality Scoring

### E1 — Per-stage quality score
**File:** `api/app/Models/Version.php` (fillable + casts) + `api/app/Services/PipelineRunner.php`
**What:** Tambah field `stage_quality` JSON parallel dengan `stage_status`. Skor 0-1 per stage dihitung dari validator pass count / total checks.
**Implementation:**
- Migration: `add_stage_quality_to_versions` (`jsonb` nullable)
- `PipelineRunner::saveArtifact` — track validator checks pass/fail, hitung skor sebelum return
- Score formula: `passed_checks / total_checks`
- Frontend: badge per stage di StageRow dengan tooltip "Quality: 0.85"
**Test:** `StageQualityScoreTest` — verify formula + storage
**Verification:**
- [ ] Migration applied
- [ ] Test pass
- [ ] Browser test: StageRow menampilkan quality badge
- [ ] Update checkpoint

### E2 — Overall Originality Score (dashboard)
**File:** `web/src/app/(app)/dashboard/page.tsx`
**What:** Aggregate originality score 0-100 di dashboard per project. Threshold:
- ≥80: "Original" (green)
- 60-79: "Standard" (yellow)
- <60: "Regenerate recommended" (red)
**Implementation:**
- Backend: `GET /api/projects/originality-scores` — return map project_id → score (aggregate dari stage_quality)
- Frontend: card baru di dashboard atau badge di project card
- Score formula: weighted average (PRD + Design System + App Spec lebih berat karena originality guard aktif)
**Test:** `OriginalityScoreEndpointTest` — verify response
**Verification:**
- [ ] Backend tests pass
- [ ] TypeScript clean
- [ ] Browser test: dashboard menampilkan originality score per project
- [ ] Update checkpoint

---

## Phase F — Operational Hardening

### F1 — Test mock fixtures untuk 9 strictified prompts existing
**File:** `api/tests/Unit/PromptValidation/` (new test files)
**What:** Saat ini C1-C3 + A1-A4 sudah ada test untuk stage baru + beberapa validator. Belum ada test dedicated untuk 9 prompt existing yang sudah strictified di doc 31 (`analisa`, `prd`, `architecture`, `env_config`, `security`, `deployment`, `standards`, `observability`, `agents`).
**Implementation:**
- `AnalisaValidationTest` — cek struktur analisa (intent summary, user personas, core problem, success metrics, anti-goals, daftar halaman)
- `PrdValidationTest` — sudah include C3 differentiation test, tambah user story + AC count
- `ArchitectureValidationTest` — cek folder structure + tech stack + trade-offs
- `EnvConfigValidationTest` — cek .env block + required vars
- `SecurityValidationTest` — cek OWASP sections + checklist
- `DeploymentValidationTest` — cek Docker + Tunnel + rollback sections
- `StandardsValidationTest` — cek convention sections (web + mobile)
- `ObservabilityValidationTest` — cek health check + logging sections
- `AgentsValidationTest` — cek AGENTS.md structure
**Test:** 9 test files × 2-3 tests each = ~25 new tests
**Verification:**
- [ ] All 9 test files created + pass
- [ ] `php artisan test` no regression
- [ ] Update checkpoint

### F2 — Migration rollback smoke test
**File:** `api/tests/Feature/MigrationRollbackTest.php` (new)
**What:** Verify 2 migrations dari doc 31 (`add_design_system_columns_to_versions` + `add_app_spec_columns_to_versions`) bisa di-rollback + re-apply tanpa error.
**Implementation:**
- Test script: `migrate:rollback --step=2` → `migrate` → verify columns exist + don't exist at right times
- Pakai `RefreshDatabase` trait atau in-memory DB
- Jangan include di CI run normal (kasih `@group migration` annotation)
**Test:** `MigrationRollbackTest` dengan 2 tests (rollback + re-apply)
**Verification:**
- [ ] Test pass manual run
- [ ] Update checkpoint

### F3 — CSP fix untuk Cloudflare Insights
**File:** `web/src/app/api/sitemap.ts` OR `web/src/middleware.ts` (CSP header)
**What:** Console error: `script-src 'self'` blocks `https://static.cloudflareinsights.com/beacon.min.js`. Allow CF Insights domain.
**Implementation:**
- Cek di mana CSP header di-set (cari `Content-Security-Policy` di codebase)
- Tambah `https://static.cloudflareinsights.com` ke `script-src` directive
- Alternative: disable CF analytics di env (kalau tidak dipakai)
- Test: console error hilang saat load page
**Test:** Browser verify no console error
**Verification:**
- [ ] CSP updated
- [ ] Browser test: no console CSP error
- [ ] Update checkpoint

---

## Checkpoint Tracker

### Phase D — Workflow Improvements ✅
- [x] D1 — Stage skip justification — `skip_reasons` migration + endpoint `POST /versions/{id}/skip-stage` + Skip button di StageRow (4 tests)
- [x] D2 — Diff view between versions — diff endpoint ditambah 8 field baru + test ownership/shape (3 tests)
- [x] D3 — Export original ZIP — `export?format=zip` + `format=md` kini include 11 artifact files baru (design-system, app-spec, env/security/deployment/observability) (2 tests)

### Phase E — Quality Scoring ✅
- [x] E1 — Per-stage quality score — `stage_quality` migration + `computeStageQuality()` di PipelineRunner + badge % di StageRow (3 tests)
- [x] E2 — Overall Originality Score di dashboard — `originality_score` di dashboard/stats dari aggregate stage_quality + badge berwarna (2 tests)

### Phase F — Operational Hardening ✅
- [x] F1 — Test mock fixtures untuk 9 strictified prompts existing — `ExistingPromptsValidationTest` cover analisa/prd/architecture/env_config/security/deployment/observability/standards/agents (10 tests)
- [x] F2 — Migration rollback smoke test — verify columns exist + down() define dropColumn untuk 4 migration (3 tests, @group migration)
- [x] F3 — CSP fix untuk Cloudflare Insights — `script-src` + `https://static.cloudflareinsights.com` di next.config.ts (0 console errors verified)

---

## File Inventory (Predicted)

### Backend
- `api/database/migrations/2026_08_19_*_add_skip_reasons_to_versions.php` — NEW (D1)
- `api/database/migrations/2026_08_19_*_add_stage_quality_to_versions.php` — NEW (E1)
- `api/app/Models/Version.php` — modified (D1, E1)
- `api/app/Services/PipelineRunner.php` — modified (D1, D3, E1)
- `api/app/Http/Controllers/VersionController.php` — modified (D2, D3, E2)
- `api/app/Http/Controllers/ProjectController.php` — modified (E2, endpoint)
- `api/tests/Unit/PromptValidation/AnalisaValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/PrdValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/ArchitectureValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/EnvConfigValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/SecurityValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/DeploymentValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/StandardsValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/ObservabilityValidationTest.php` — NEW (F1)
- `api/tests/Unit/PromptValidation/AgentsValidationTest.php` — NEW (F1)
- `api/tests/Feature/MigrationRollbackTest.php` — NEW (F2)
- `api/tests/Unit/PromptValidation/SkipReasonTest.php` — NEW (D1)
- `api/tests/Feature/DiffEndpointTest.php` — NEW (D2)
- `api/tests/Feature/ZipExportTest.php` — NEW (D3)
- `api/tests/Feature/StageQualityScoreTest.php` — NEW (E1)
- `api/tests/Feature/OriginalityScoreEndpointTest.php` — NEW (E2)

### Frontend
- `web/src/app/(app)/projects/[id]/page.tsx` — modified (D2 diff button, D3 export ZIP button, E1 quality badge)
- `web/src/app/(app)/dashboard/page.tsx` — modified (E2 originality score)
- `web/src/components/wizard/StageRow.tsx` — modified (E1 quality badge)
- `web/src/components/wizard/ExportButton.tsx` — NEW (D3)
- `web/src/components/wizard/DiffModal.tsx` — NEW (D2)
- `web/src/middleware.ts` — modified (F3 CSP)

### Docs
- `docs/33-quality-origin-phase2.md` — dokumen ini

---

## Estimated Effort

| Phase | Tasks | Est. time |
|-------|-------|-----------|
| D | 3 tasks | 2-3 jam |
| E | 2 tasks | 1.5-2 jam |
| F | 3 tasks | 2-3 jam |
| **Total** | **8 tasks** | **5.5-8 jam** |

---

## Risks & Mitigations

1. **D2 Diff logic complexity** — Side-by-side diff per stage. Mitigasi: start dengan simple text comparison, add highlight via `diff-match-patch` library kalau perlu.
2. **E1 Quality score formula subjective** — Skor 0-1 dari validator checks. Mitigasi: assign weight per check (e.g. keyword = 0.1, structure = 0.3, originality = 0.5).
3. **D3 ZIP size limit** — kalau artifacts panjang (PRD + arch + ERD + design system), ZIP bisa besar. Mitigasi: gunakan `ZipArchive::OPTIMIZE_DEFLATE` + check size di endpoint.
4. **F2 Migration rollback test flaky** — kalau test di CI environment dengan shared DB. Mitigasi: pakai `RefreshDatabase` atau mark test sebagai `@group migration` (manual only).

---

## Glossary

- **Skip reason**: Audit trail kenapa user melewati stage tertentu (e.g. "Mobile tidak relevan untuk project ini").
- **Diff view**: Side-by-side comparison antara 2 versions dari satu project (PRD v1 vs PRD v2).
- **Quality score**: 0-1 metric per stage yang reflect kelengkapan + originality output.
- **Originality score**: 0-100 aggregate metric di project level (weighted average).
- **CSP**: Content-Security-Policy HTTP header yang control resource loading.
