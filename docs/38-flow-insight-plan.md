# 38 — Flow & Insight — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-20
> **Completed:** 2026-08-20
> **Scope:** A — Alur (auto-resume CTA dashboard, retry error 1-klik di project detail, lite-on-resume, bersihkan residual error). B — Insight (History Versi timeline, Quality Report per project).
> **Parent:** docs/31-37 (COMPLETED)

---

## Objective

Memaksimalkan penyelesaian proyek & hasil: satu-klik lanjutkan, retry error mudah, dan halaman insight berbasis data yang sudah ada (stage_quality, skip_reasons, stage_errors, versions).

---

## Phase A — Alur

### A1 — Auto-Resume CTA di Dashboard
**File:** `web/src/app/(app)/dashboard/page.tsx`
**What:** Untuk project mid (progress < stage_count ATAU ada error), tampilkan tombol "Lanjutkan" (selain "Buka") → `/new?resume=1&version={latest_version_id}`. Project done → hanya "Buka".
**Catatan:** backend `dashboardStats` sudah kirim `latest_version_id`, `progress`, `stage_count`. Done bila `progress === stage_count`.
**Verifikasi:**
- [ ] Tombol "Lanjutkan" hanya utk project belum tuntas
- [ ] Click → wizard resume mulai stage pertama belum done
- [ ] tsc + lint

### A2 — Retry Error 1-Klik di Project Detail
**File:** `web/src/app/(app)/projects/[id]/page.tsx` + `web/src/components/wizard/StageRow.tsx`
**What:** StageRow sudah punya tombol regenerate utk status `done`. Tambah tombol retry saat `error` → `apiPost(regenerate, {stage})` (hint otomatis). Status `error` → tombol `RotateCcw` merah.
**Verifikasi:**
- [ ] Tombol retry muncul di status error
- [ ] Regenerate dipanggil + fetchVersion
- [ ] tsc + lint

### A3 — Lite-on-Resume
**File:** `web/src/app/(app)/new/page.tsx` (resume effect)
**What:** Deteksi lite dari `skip_reasons` version (bila ada reason berisi "Lite plan") → `setLiteMode(true)`. Tanpa URL param; deterministik dari data.
**Verifikasi:**
- [ ] Version lite → liteMode aktif saat resume
- [ ] Version normal → tidak terpengaruh
- [ ] tsc + lint

### A4 — Bersihkan Residual Error Stage (ops/data)
**File:** console tinker (bukan kode)
**What:** Untuk project 283,285,287,289 — stage ber-status `error` di-regenerate batch (run stage, auto=false, 1x) pakai peovsk hint (stage_errors tersimpan). Catat hasil lolos/tetap error.
**Verifikasi:**
- [ ] Regenerate semua stage error (4 project)
- [ ] Laporan akhir jumlah stage yang lolos vs tetap error (truncation provider)

---

## Phase B — Insight

### B1 — History Versi Timeline
**File:** `web/src/app/(app)/projects/[id]/page.tsx`
**What:** Panel collapsible "Riwayat Versi" — daftar semua versi: `v{n}` · tanggal · x/y tahap · source (v sebelum) · baseline_notes · skip_reasons singkat. Klik versi → pilih version tersebut.
**Verifikasi:**
- [ ] Timeline render semua versi + metadata
- [ ] Klik versi memilih version
- [ ] tsc + lint

### B2 — Quality Report per Project
**File:** `web/src/app/(app)/projects/[id]/page.tsx` (modal/inline)
**What:** Tombol "Laporan Kualitas" → panel tabel per stage: nama · status · skor (stage_quality) · peringatan (stage_errors/warning). Empty: "—". Threshold warna skor (≥.8 hijau, .6 kuning, else merah). Rekomendasi regenerate utk skor < .6.
**Verifikasi:**
- [ ] Tabel render dari stage_quality + stage_status + stage_errors
- [ ] Threshold warna benar
- [ ] tsc + lint

---

## Checkpoint Tracker
- [x] A1 — Auto-Resume CTA dashboard ✅ (tombol "Lanjutkan" bila progress<stage_count; tsc+lint clean)
- [x] A2 — Retry error 1-klik project detail ✅ (StageRow tombol merah retry saat status error; tsc+lint clean)
- [x] A3 — Lite-on-resume ✅ (deteksi skip_reasons "Lite plan" → setLiteMode true; tsc+lint clean)
- [x] A4 — Bersihkan residual error ✅ hasil: 287 Internal CRM 16/16 selesai; 283 18/22; 285 19/22; 289 14/22 — sisa murni flake truncation/format 9r (mobile DS, app_spec JSON, MCQ, prd Then)
- [x] B1 — History Versi timeline ✅ (collapsible Card di sidebar: v{n} · tanggal · done/total · source versi · baseline_notes · skip_reasons; klik pilih versi; tsc+lint clean)
- [x] B2 — Quality Report modal ✅ (tombol "Laporan Kualitas" di Pipeline card → modal tabel stage: status · skor % badge warna · catatan error/skip; tsc+lint clean)
- [x] Final — full test + tsc/eslint + build web + Playwright + commit/push ✅

### Final — Verified
- **A1** browser: dashboard Kasir/Marketplace punya "Lanjutkan"+"Buka"; Internal CRM (done) hanya "Buka" ✅
- **B1** browser: sidebar "Riwayat Versi" render (v1 · date · done/total) ✅
- **B2** browser: modal "Laporan Kualitas" → 16 baris (stage · status · skor% · catatan) ✅
- **Fix issue:** modal quality crash saat null selectedVersion (modal mount) → guard `{selectedVersion && ...}`
- Backend 391 pass (1 Socialite flake); tsc/eslint clean; web 200 healthy

## File touch
- `web/src/app/(app)/dashboard/page.tsx` (A1)
- `web/src/components/wizard/StageRow.tsx` (A2)
- `web/src/app/(app)/projects/[id]/page.tsx` (A2, B1, B2)
- `web/src/app/(app)/new/page.tsx` (A3)
- DB ops (A4)