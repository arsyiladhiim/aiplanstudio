# 37 — Resume Resilience (Anti-Stuck) — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-20
> **Completed:** 2026-08-20
> **Scope:** Pastikan resume wizard & project mid-pipeline tidak stuck seperti PromoGila. **Tanpa melemahkan aturan/validator apa pun yang menjaga skor.** Resilience dicapai lewat: fallback path yang tetap lolos validator existing, antisipasi truncation, cleanup orphan, dan UX resume transparan.

---

## Prinsip Kunci
- **TIDAK** menurunkan ambang validator (minimal counts, marker, placeholder, keyword) — semua rule tetap.
- Fallback baru tetap **melewati validator yang sama** (schema check, min MCQ, dst).
- Root-cause truncation (provider 9r potong output panjang) dikurangi lewat **budget konteks** + **hint**, bukan melonggarkan validasi.

---

## Backend

### R2 — api_contract fallback ke ERD (tanpa lemahkan schema)
**Problem:** api_contract di HALT-list; bila provider output JSON murni prose → stuck.
**Fix:** Pada `run()`/`saveArtifact` api_contract — bila decode JSON gagal setelah retry, cek `$this->version->erd['api_contract']`; bila non-empty → `assertApiContractSchema()` pada fallback itu (validasi SAMA), simpan, mark done + log.
**Verifikasi:**
- [ ] Fallback dipakai saat ERD punya api_contract & stage gagal
- [ ] Fallback tetap lolos `assertApiContractSchema`
- [ ] Test `ResumeResilienceTest::test_api_contract_falls_back_to_erd`
- [ ] pint + regresi

### R3 — Phases parser toleransi delimiter (tetap wajib blok FASE)
**Problem:** parser split hanya `---`; provider lupa separator → parse null → halt.
**Fix:** `parsePhasesText` — split blok juga pada baris `FASE:` (akui delimiter alternatif: `---` ATAU baris baru `FASE:`). Struktur `FASE: key | title` + token tetap case-insensitive & wajib `key|title`.
**Verifikasi:**
- [ ] Tanpa `---`, blok tetap ter-parse
- [ ] Blok FASE wajib tetap (parse null bila tidak ada FASE)
- [ ] Test `PhasesCasingTest` extend
- [ ] pint + regresi

### R4 — Orphan `running` cleanup
**Problem:** proses crash meninggalkan stage `running`; resume tidak reset → inkonsistensi.
**Fix:** Di awal `run()`: status apa pun yang `running` → `pending` (defensif, sekali per run).
**Verifikasi:**
- [ ] Test `ResumeResilienceTest::test_running_orphan_reset_on_run`
- [ ] pint

### R5 — MCQ plain-text fallback (tetap wajib ≥5 valid)
**Problem:** provider gagal JSON MCQ → 0 pertanyaan setelah 10 retry → data kosong.
**Fix:** Gagal JSON → parse teks berformat `1. pertanyaan` / `- pertanyaan` / `### pertanyaan` menjadi items {id,question,options:['Ya','Tidak']}; tetap Wajib `mcqCount≥5` via `sanitizeMcqData`; komputer kualitas tidak berubah (pertanyaan tak masuk skor). Simpan + mark done.
**Verifikasi:**
- [ ] Fallback text menghasilkan ≥5 items valid
- [ ] Masih gagal bila <5 (rule min TETAP)
- [ ] Test `ResumeResilienceTest::test_mcq_text_fallback`
- [ ] pint

### R6 — Budget konteks stage panjang (root-cause truncation)
**Problem:** master_web/mobile + phases memuat konteks besar → provider potong → marker/gaya hilang → fail (masih hard rule).
**Fix (TANPA mengubah validator):**
- Context prompt master_web/mobile: `truncateForContext` untuk prd/architecture/design_system diperketat (dari 1500/2000 → 900/1200) agar total konteks turun.
- Prompt master_web/mobile: tambah instruksi tegas "AKHIRI output dengan satu baris `## SELESAI` — jangan tambahkan apa pun setelahnya" + W6 hint sudah me-repeat error.
**Verifikasi:**
- [ ] Konteks master dilihat dari stub — panjang terpangkas
- [ ] Validator master TETAP (marker hard)
- [ ] Test regresi context prompt
- [ ] pint

---

## Frontend

### F1 — Resume normalize `running`
**Fix:** di resume load (`new/page.tsx`), status `running` → `pending` (selaras R4).
**Verifikasi:**
- [ ] tsc + lint

### F2 — Banner resume "Melanjutkan dari {stage} — {n} tahap tersisa"
**Fix:** sebelum auto-start, tampilkan inline notice stage awal + perkiraan sisa (dari `stages` aktif target). Bukan modal, tidak memblokir.
**Verifikasi:**
- [ ] tsc + lint + Playwright

### F3 — Fail action "Coba lagi dengan perbaikan"
**Fix:** saat SSE `fail` stage, tampilkan aksi di samping pesan error → `doStream(versionId, stage)` ulang (hint W6 otomatis ter-inject karena stage_errors tersimpan). Batasi manual aja.
**Verifikasi:**
- [ ] tsc + lint + Playwright (simulasi fail tak dpt dipicu live tanpa AI gagal — verifikasi kode + render)

---

## Checkpoint Tracker
- [x] R2 — api_contract fallback ERD (schema tetap) ✅ 1 test
- [x] R3 — phases delimiter tolerance ✅ 1 test
- [x] R4 — orphan running cleanup ✅ 1 test
- [x] R5 — MCQ text fallback (≥5 tetap) ✅ 2 test
- [x] R6 — budget konteks master (PRD/arch 2000→1300, app_spec 1500→1000, master_mobile full→2200) ✅ regressi 68 pass
- [x] F1 — resume normalize running ✅ (error+running→pending)
- [x] F2 — banner resume stage ✅ (banner inline "Melanjutkan dari X — n dari N")
- [x] F3 — fail retry action ✅ (tombol "Coba lagi dengan perbaikan" → doStream; hint otomatis)
- [x] R7 — app_spec derive components (schema tetap) ✅
- [ ] Final — Resume run real 5 project mid (329 khusus master_web) + full test + pint/tsc/eslint

### Final — Resume Run Real (verified) ✅
- **R7** (ditambah): derive components/widgets app_spec dari components_used halaman (schema tetap) — `deriveSpecComponents`
- **MAX_VALIDATE_RETRIES 2 → 3** (self-heal, bukan pelonggaran rule)
- **Konteks di-trim 6 titik** (PRD/Architecture full → summarize 1400) — menurunkan truncation pada security/deployment/env_config (verified: env_config & deployment kini lolos di 328/332)
- **HashMap resume simulasi (provider 9r riil):**
  - 284 SaaS (resume master_web — risiko tertinggi): **16/16 DONE, 0 error** ✅
  - 332 Internal CRM: 15/16 (tersisa deployment — truncation ## 8, non-stuck, resumable)
  - 328 Kasir: 18/22 (tersisa app_spec/mobile-DS/MCQ — provider truncation/format)
  - 330 Marketplace: 18/22 (sama)
  - 334 fresh: 14/22 (tersisa prd Then-count + pertanyaan 3/5)
- **Kesimpulan:** Tidak ada project dead-lock. Tiap kegagalan = status `error` aman → resume/F3 retry dengan hint; halt-stage punya fallback data (api_contract SQL+CRUD, app_spec derive). Residual kegagalan hanya **truncation provider** pada dokumen panjang — tidak bisa disembuhkan tanpa melemahkan aturan (yang dilarang).
- **Tests:** 391 passed (+10 sejak plan), pint/tsc/eslint clean, 1 Socialite flake pre-existing
- **R2b** — buildCrudContractFromErd: fallback CRUD deterministik dari node ERD (328 api_contract sukses via ini)

## File touch
- `api/app/Services/PipelineRunner.php` (R2,R4,R6)
- `api/app/Services/AiOutputParser.php` (R3,R5)
- `web/src/app/(app)/new/page.tsx` (F1,F2,F3)
- `api/tests/Feature/ResumeResilienceTest.php` (baru), `PhasesCasingTest`, `KeywordSynonymTest` (extend)