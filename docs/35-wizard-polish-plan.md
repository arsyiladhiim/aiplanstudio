# 35 — Wizard Polish — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-19
> **Completed:** 2026-08-19
> **Scope:** Perbaikan UX & konsistensi wizard stage berdasarkan review — retry dengan hint, stage grouping, cross-ref validator, status skipped, lite mode
> **Parent:** `docs/32-quality-origin-plan.md` + `docs/33-quality-origin-phase2.md` (COMPLETED)

---

## Objective

Review wizard 22-stage memunculkan 5 area perbaikan:

1. **P3-Re try** — Retry stage gagal tidak membawa konteks error sebelumnya.
2. **P1-Grouping** — 22 stage berurutan melelahkan; tidak ada navigasi grupal.
3. **P2-CrossRef** — Master prompt redundancy tanpa cross-ref validator.
4. **P4-Skipped** — Stage mobile di target web di-mark `done` padahal tak pernah digenerate (progress sesat).
5. **P5-LiteMode** — Tidak ada opsi quick plan untuk scope cepat.

Urutan eksekusi (nilai tinggi → effort rendah dulu):
`P3 → P1 → P2 → P4 → P5`

---

## P3 — Retry dengan Hint (prioritas 1)

### Problem
Stage gagal (validator throw) → `regenerateStage` / `run` ulang pakai konteks yang sama → sama-sama bisa gagal. User stuck loop.

### Implementasi
**File:** `api/app/Services/PipelineRunner.php` + `api/app/Http/Controllers/VersionController.php`

- **Backend:** Simpan pesan error terakhir per stage di field baru `stage_errors` (jsonb) — `{stage: "message"}`.
- **Context prompt:** Saat regenerate stage, inject pesan error terakhir ke `systemPrompt()`:
  ```
  SEBELUMNYA output kamu DITOLAK karena: {error_message}
  Perbaiki spesifik masalah tersebut dan jangan ulangi kesalahan yang sama.
  ```
- **Retry otomatis:** Ekstensi pattern `retryPertanyaanForMinimum` (sudah ada) jadi generic `retryStage` — max 2 retry untuk stage non-artefak langka; inject hint ke tiap attempt (N+1 menerima pesan error attempt N).
- **Reset:** `stage_errors[stage]` dihapus saat stage sukses.

### Test
- `RetryWithHintTest`:
  1. Stage gagal → `stage_errors` terisi
  2. Generate ulang → hint error termuat di context prompt
  3. Sukses → `stage_errors` terhapus

### Verification
- [ ] Migration `stage_errors` applied
- [ ] Backend test pass
- [ ] `pint` clean
- [ ] Update checkpoint

---

## P1 — Stage Grouping (prioritas 2)

### Problem
22 stage linear tanpa unit navigasi.

### Implementasi
**File:** `web/src/lib/mock.ts` (group def) + `web/src/app/(app)/new/page.tsx` + `web/src/app/(app)/projects/[id]/page.tsx`

- **Group def di `mock.ts`:**
  ```ts
  export const STAGE_GROUPS = [
    { key: "discovery", label: "Klarifikasi & Analisa", stages: ["pertanyaan", "analisa"] },
    { key: "definition", label: "Dokumen Produk", stages: ["prd"] },
    { key: "design", label: "Arsitektur & Desain", stages: ["architecture", "erd", "api_contract", "design_system"] },
    { key: "web-build", label: "Web — Build Plan", stages: ["phases_web", "standards_web", "master_web", "app_spec_web"] },
    { key: "mobile-build", label: "Mobile — Build Plan", stages: ["design_system_mobile", "pertanyaan_mobile", "phases_mobile", "standards_mobile", "master_mobile", "app_spec_mobile"] },
    { key: "launch", label: "Ops & Security", stages: ["env_config", "security", "deployment", "observability", "agents"] },
  ];
  ```
- **Wizard (`new/page.tsx`):** Pipeline progress menampilkan group sebagai section collapsible; stage di dalam group. Done-group ditandai.
- **Project detail (`projects/[id]/page.tsx`):** Pipeline list dikelompokkan per group dengan header + ringkasan (n/tahap selesai).
- Backend TIDAK berubah — grouping murni UI.

### Test
- Manual browser: group header tampil, collapsible, progres benar.
- `tsc` + lint clean.

### Verification
- [ ] `tsc --noEmit` clean
- [ ] `npm run lint` clean
- [ ] Browser test: group header + kolaps/expand + ringkasan benar
- [ ] Update checkpoint

---

## P2 — Cross-Reference Validator (prioritas 3)

### Problem
`master_web` + `master_mobile` + phases + standards overlap; `app_spec` bisa tidak konsisten dengan master.

### Implementasi
**File:** `api/app/Services/PipelineRunner.php`

- **App Spec ↔ Master:** `validateAppSpecMasterCrossRef` — setiap `app_spec_web.halaman[].key` wajib muncul di konten `master_prompt` (atau sebaliknya, menampilkan peringatan bukan error keras). Dipanggil di `saveArtifact` untuk `app_spec_web`/`app_spec_mobile`.
- **Master ← Standars:** `validateMasterStandardsCrossRef` — `master_prompt`/`master_mobile` wajib memuat minimal 1 heading dari standards (`standards_web`/`mobile_standards`) dan 1 token design system (`--color-*` dari design_system fence). Cross-check via substring/heading match.
- Skor kualitas (`computeStageQuality`) tambah komponen cross-ref (0.1 weight).

### Test
- `CrossReferenceValidatorTest`:
  1. app_spec punya halaman yang tidak ada di master → error (atau warning per konfigurasi)
  2. master tidak memuat standards heading → error
  3. Valid → pass

### Verification
- [ ] Backend test pass
- [ ] `pint` clean
- [ ] Test existing (AppSpecValidation, PipelineNewStages) tidak regress
- [ ] Update checkpoint

---

## P4 — Status `skipped` Terpisah (prioritas 4)

### Problem
Stage mobile di target web di-`done`-kan otomatis (di `run()`) padahal tidak pernah digenerate → progress "16/16" palsu untuk bagian yang tidak pernah ada.

### Implementasi
**File:** `api/app/Services/PipelineRunner.php` + `api/app/Models/Version.php` + frontend `page.tsx`

- **Model:** tambah konstanta `SKIPPED_STAGES` mapping (web-only → stage mobile) dan method `isTargetSkipped(stage)`.
- **Runner `run()`:** ganti `updateStageStatus($key, 'done')` untuk skip mobile → `'skipped'`.
- **progressCount():** stage `skipped` TIDAK dihitung sebagai selesai progress (denominator = done + pending + running + error).
- **defaultStageStatus untuk web-target:** mobile stages default `skipped` (setelah gate).
- **Frontend:** render status `skipped` (badge abu-abu "Dilewati"), bukan hijau.
- Validator: stage skipped tidak menerima regenerate.

Penting: perubahan status di backend perlu test — PipelineNewStages `test_all_stages` bisa impact (periksa).

### Test
- `SkippedStatusTest`:
  1. target web → mobile stages = `skipped` setelah run
  2. progressCount exclude skipped
  3. regenerate stage skipped → ditolak (422)

### Verification
- [ ] Backend test pass
- [ ] Test existing pipeline tidak regress
- [ ] Browser test: badge "Dilewati" di Web target
- [ ] Update checkpoint

---

## P5 — Lite Mode (prioritas 5)

### Problem
Tidak ada jalur cepat untuk user yang hanya mau scope.

### Implementasi
**File:** `api/app/Prompts/*` (subset select) + `web/src/app/(app)/new/page.tsx` (toggle) + runner

- **UI:** toggle "Lite Plan (cepat)" di form /new.
- **Backend:** `LITE_STAGES` = `['pertanyaan', 'analisa', 'prd', 'erd', 'master_web']`. Saat lite mode:
  - `run()` hanya jalankan subset itu.
  - Stage di luar subset di-set `skipped` dengan alasan default ("Lite plan").
  - `master_prompt` di-generate dari subset (PRD + ERD + analisa), bukan full stack.
  - Catatan di `skip_reasons` per stage.
- **Gate:** prompt master_web tetap butuh prerequisites yang digenerate subset — pastikan contextPrompt tidak memaksa stage yang tidak ada.

### Test
- `LiteModeTest`:
  1. Lite run hanya proses subset
  2. Stage luar subset jadi skipped + skip_reasons berisi "Lite plan"
  3. master_web selesai tanpa stage penuh

### Verification
- [ ] Backend test pass
- [ ] Browser test: toggle Lite → pipeline pendek
- [ ] Update checkpoint

---

## Checkpoint Tracker

### Priority 1 — Retry Hint ✅
- [x] P3 — Retry dengan hint (stage_errors + inject error + retry otomatis)
  - Migration `stage_errors` applied
  - `injectRetryHint()` + `retryAndValidate()` + `recordStageError()`/`clearStageError()` di PipelineRunner
  - 4 tests pass, PipelineRunnerTest 52 pass no regress, pint clean

### Priority 2 — Grouping ✅
- [x] P1 — Stage grouping (mock.ts STAGE_GROUPS + wizard + detail)
  - `STAGE_GROUPS` + `getStageGroups(target)` di mock.ts
  - project detail pipeline list dikelompokkan per group (collapsible `<details>`) dengan ringkasan n/tahap selesai
  - `tsc --noEmit` clean, `npm run lint` clean

### Priority 3 — Cross-Ref ✅
- [x] P2 — Cross-reference validator (app_spec↔master, master↔standards)
  - `validateAppSpecMasterCrossRef`: halaman/screen app_spec wajib muncul di master prompt (hard error)
  - `validateMasterStandardsCrossRef`: soft check — log warning bila master tak memuat heading standards
  - 4 tests pass; AppSpec+PipelineNewStages 16 pass; pint clean

### Priority 4 — Status ✅
- [x] P4 — Status `skipped` terpisah untuk mobile web-only
  - `run()`: mobile stage di target web → `skipped` (bukan `done`)
  - `visibleStageCount()` baru — denominator web=16, both=22
  - `progressCount()` exclude skipped
  - Migration: CHECK `phase_progress.status` + `skipped`
  - StageRow: badge "Dilewati" + ikon Minus + type `skipped`
  - 4 tests pass; pipeline/version/project/tracking 137 pass; pint clean

### Priority 5 — Lite Mode ✅
- [x] P5 — Lite mode (toggle + backend subset)
  - `LITE_STAGES = [pertanyaan, analisa, prd, architecture, erd, master_web]`
  - `run($stage, $auto, $lite=true)` → stage non-lite di-mark `skipped` + `skip_reasons` "Lite plan"
  - GenerateStreamController terima `lite` query param
  - Frontend `/new`: checkbox "Lite Plan" → `lite=1` ke SSE
  - 3 tests pass; tsc + lint clean

---

## Workflow per Progress

1. Implementasi
2. Test backend (`php artisan test --filter=X`)
3. Lint (`pint`) + typecheck (`tsc --noEmit`) + lint frontend (`npm run lint`)
4. Browser test via MCP Playwright bila relevan
5. **Issue ditemukan → fix saat itu juga, re-run semua**
6. Update checkpoint di dokumen ini
7. Lanjut ke progress berikutnya

---

## File Inventory (Predicted)

### Backend
- `api/database/migrations/2026_08_19_*_add_stage_errors_to_versions.php` — NEW (P3)
- `api/app/Models/Version.php` — modified (P3 fillable/cast, P4 SKIPPED)
- `api/app/Services/PipelineRunner.php` — modified (P3 retry, P2 cross-ref, P4 skipped, P5 lite)
- `api/app/Http/Controllers/VersionController.php` — modified (P3 hint post)
- `api/tests/Feature/RetryWithHintTest.php` — NEW
- `api/tests/Feature/CrossReferenceValidatorTest.php` — NEW
- `api/tests/Feature/SkippedStatusTest.php` — NEW
- `api/tests/Feature/LiteModeTest.php` — NEW

### Frontend
- `web/src/lib/mock.ts` — modified (P1 STAGE_GROUPS, P4 skipped badge)
- `web/src/app/(app)/new/page.tsx` — modified (P1 groups, P5 toggle)
- `web/src/app/(app)/projects/[id]/page.tsx` — modified (P1 groups, P4 badge)
- `web/src/components/wizard/StageRow.tsx` — modified (P4 skipped render)

### Docs
- `docs/35-wizard-polish-plan.md` — dokumen ini

---

## Estimasi

| Priority | Task | Est. |
|----------|------|------|
| P3 | Retry hint | 1.5-2 jam |
| P1 | Grouping | 1-1.5 jam |
| P2 | Cross-ref | 1.5 jam |
| P4 | Skipped status | 1 jam |
| P5 | Lite mode | 1.5 jam |
| **Total** | **5 task** | **6.5-7 jam** |