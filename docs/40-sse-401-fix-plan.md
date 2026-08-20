# 40 — SSE 401 Fix — Realtime Progress (Project Detail + Wizard Master) — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-20
> **Completed:** 2026-08-20
> **Scope:** Hilangkan error 401 + aktifkan realtime phase-progress di: (1) project detail "Progress Bangun", (2) wizard master_web & master_mobile TrackingPanel. Saklar EventSource (tanpa cookie cross-origin) → fetch POST + credentials + CSRF (`createSSEPost`). **Tanpa mengubah data/aturan.**

---

## Root Cause
- Browser `EventSource` tidak bisa kirim cookie session cross-origin (`aiplanstudio…` vs `api-aiplanstudio…`).
- Route `/versions/{id}/phase-progress/stream` (GET) di grup `auth:sanctum` → 401 → reconnect loop → realtime mati + console storm.
- Dipakai di 2 tempat: `projects/[id]/page.tsx:105` dan `new/page.tsx:494`.
- Backend sudah emit `event: phase_progress` + `data:` → kompatibel dengan `createSSEPost`.

---

## D1-1 — Backend: route accept GET+POST
**File:** `api/routes/api.php`
**What:** `Route::match(['get','post'], '/versions/{id}/phase-progress/stream', [VersionController::class, 'phaseProgressStream'])->middleware('throttle:30,1');`
Controller `phaseProgressStream` tak berubah (StreamedResponse method-agnostic).
**Verifikasi:**
- [ ] Route POST → 200 (dengan cookie) / 401 (tanpa)
- [ ] GET lama tetap jalan (backward compat)
- [ ] `php artisan route:list` tampil GET|POST

## D1-2 — `lib/api.ts`: wrapper POST + auto-reopen
**File:** `web/src/lib/api.ts`
**What:** Tambah `createPhaseProgressStream(path, onEvent, onError)` → lakukan `createSSEPost(path, {}, onEventParer, onError)` di mana event `phase_progress` diteruskan; auto-reopen (setTimeout 3s) saat stream tertutup (tidak `done`/`fail`); kembalikan `AbortController`.
**Verifikasi:**
- [ ] tsc clean
- [ ] pola sama dgn createSSE (reopen)

## D1-3 — Project detail pakai POST-stream
**File:** `web/src/app/(app)/projects/[id]/page.tsx`
**What:** Ganti `createSSE(...)` (line ~105) → `createPhaseProgressStream(...)`; cleanup abort on unmount.
**Verifikasi:**
- [ ] tsc/eslint clean

## D1-4 — Wizard master pakai POST-stream
**File:** `web/src/app/(app)/new/page.tsx`
**What:** Ganti `createSSE(...)` (line ~494) → `createPhaseProgressStream(...)`; cleanup abort on unmount.
**Verifikasi:**
- [ ] tsc/eslint clean

---

## Checkpoint Tracker
- [x] D1-1 — route GET+POST ✅ (`Route::match(['get','post'], ...)`)
- [x] D1-2 — wrapper POST + reopen ✅ (`createPhaseProgressStream` di `lib/api.ts`: createSSEPost + auto-reopen 4s + abort)
- [x] D1-3 — project detail ✅ (ganti createSSE → createPhaseProgressStream; abort on unmount)
- [x] D1-4 — wizard master ✅ (new/page line ~494 ganti ke wrapper; import createSSE dihapus)
- [ ] Final — console 0 error + realtime + full test + Playwright + commit/push

## File touch
- `api/routes/api.php`
- `web/src/lib/api.ts`
- `web/src/app/(app)/projects/[id]/page.tsx`
- `web/src/app/(app)/new/page.tsx`