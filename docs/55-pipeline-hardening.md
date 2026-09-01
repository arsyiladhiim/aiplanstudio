# 55 — Pipeline Hardening Deep (Wizard + Output Quality)

Keputusan user: ERD min dinamis (≥2×modul PRD, floor 8); retry loop maks 2; concurrency via **PG advisory lock** (409 bila duplikat); MCQ restore dari format `q{n}: teks → "A. ..."`.

## Batch 1 — Stabilitas wizard (frontend)

- [x] 1.1 Tracking SSE re-subscribe saat activeKey berubah (`new/page.tsx` tracking effect dep versiId saja → broken saat master_*)
- [x] 1.2 Batalkan setTimeout retry saat abort/cancelAll (`usePipelineStream`)
- [x] 1.3 Satu jalur abort: `streamApi.cancelAll()`; hapus `abortRef` page mati (601/840/919)
- [x] 1.4 cancelGeneration → backend cancel (set stage pending + pesan) + abort lokal; DB sinkron
- [x] 1.5 MCQ resume: `q{n}: teks → "A. ..." | "E. Lainnya: ..."` → mcqAnswers/mobileMcqAnswers di-restore
- [x] 1.6 Regen gagal → revert status + tutup spinner + pesan spesifik
- [x] 1.7 projects/[id] pasca-regen: refetch ×3 jeda 3s sampai artifact segar

## Batch 2 — Kualitas output pipeline (backend)

- [x] 2.1 TestingStrategyValidator: regex `**PATH-N**:` DAN `**PATH-N:**` + fallback count baris list di section; + unit test
- [x] 2.2 ERD: prompt richness (modul dari PRD); backend enforce `MIN_NODES = max(8, 2 × fitur/modul)`; PK/FK/audit/timestamps; retry maks 2
- [x] 2.3 api_contract: min endpoints `max(12, 2 × resource ERD)`; coverage resource↔nodes ≥80%
- [x] 2.4 Token budget: master_/api_contract/app_spec → 12288; continuation tangguh (length ATAU json belum lengkap); jangan berhenti pada chunk kecil
- [x] 2.5 Drift fixes: phases (≥5 fase, ≥3 tasks, instruksi ≥100 kata), design_system_mobile tanpa syarat CSS var, app_spec_mobile prompt ≥3 widgets, PRD per-story AC ringan, deriveSpecComponents aktif sebelum throw
- [x] 2.6 Advisory lock: `pg_try_advisory_lock` di GenerateStream + regenerate; 409 duplikat; unlock finally; test duplikat

## Batch 3 — Robustness + housekeeping

- [x] 3.1 SSE client: idle timeout 30s restart; 419 → CSRF refresh auto-retry; multi-line data
- [x] 3.2 Scoring: keyword groups analisa/phases/master/testing_strategy; generic-pattern skip pada JSON stage
- [x] 3.3 Lazy-load viewer berat di /new (AnalysisView/PrdView/dst)
- [x] 3.4 projects: guard latest_version null, countdown interval stop saat done, debounce refetch fase 2s
- [x] 3.5 A11y: modal focus-return, radio arrow-key, drawer aria-expanded
- [x] 3.6 Test gaps: erd min, api_contract fallback, master SELESAI, phases min, 419, duplikat concurrent
