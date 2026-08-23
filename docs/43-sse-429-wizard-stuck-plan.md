# 43 — SSE 429 Fix — Wizard Stage Stuck "Tidak Bisa Diklik" — Build Plan & Checkpoints

> **Status:** 🚧 IN PROGRESS
> **Started:** 2026-08-22
> **Scope:** Wizard `/new` deadlock setelah SSE gagal: status stage stuck `running`, tombol Coba Lagi / Lanjutkan tidak muncul. Akar masalah: (1) throttle `10/min` pada `/generate/stream` jebol oleh auto-retry burst → 429; (2) jalur max-retry di `doStream` tidak menandai stage error sehingga UI tidak menawarkan aksi recovery.

---

## Root Cause
- `api/routes/api.php:101` — `POST /generate/stream` pakai `throttle:10,1`. Auto-retry SSE (1 + 3 retry) + klik manual beruntun = burst POST → 429 (22x 429 tercatat di access log 22 Agu).
- `web/src/app/(app)/new/page.tsx:534-537` — jalur `else` (max retry) hanya `setError("Koneksi SSE terputus setelah 3x retry.")`; **tidak** `setStatus(stage→"error")` dan **tidak** `setFailedStage(stage)`:
  - Tombol "Coba Lagi" (card, line 1864) butuh `status[activeKey] === "error"` → tidak render.
  - Tombol "Coba lagi dengan perbaikan" (error panel, line 1952) butuh `failedStage` → tidak render.
  - Status tetap `"running"` → spinner abadi / BuildWall aktif → terlihat "tidak bisa diklik".
- `createSSEPost` (`web/src/lib/api.ts:351`) tidak membedakan 429 → retry cepat (2s/4s/6s) justru memperpanjang window throttle.

---

## D43-1 — Frontend: jalur max-retry tandai stage error
**File:** `web/src/app/(app)/new/page.tsx` (`doStream`)
**What:** branch `else` setelah 3 retry: panggil `setStatus(stage → "error")`, `setFailedStage(stage)`, dan pesan error memakai `err.message` (429 kelihatan jelas). Dengan ini tombol "Coba Lagi" + panel error muncul dan user bisa lanjut.
**Verifikasi:**
- [ ] Simulasi 3x 429: tombol "Coba Lagi" + "Coba lagi dengan perbaikan" muncul, spinner berhenti.
- [ ] Klik "Coba Lagi" setelah window throttle lewat → stream jalan.
- [ ] `npm run lint` + `npx tsc --noEmit` lulus.

## D43-2 — Frontend: backoff khusus 429 di `createSSEPost`
**File:** `web/src/lib/api.ts`
**What:** Saat `res.status === 429`, panggil `onError` dengan `Error` ber-flag (mis. `error.name = "TooManyRequests"`) agar `doStream` bisa membedakan: jangan retry cepat — langsung tandai stage error dengan pesan "Terlalu banyak permintaan, tunggu ±1 menit lalu coba lagi" (retry manual). Retry otomatis cepat dipertahankan hanya untuk error non-429.
**Verifikasi:**
- [ ] 429 → langsung UI error, tanpa 3x burst tambahan.
- [ ] Error jaringan biasa → tetap auto-retry 3x.
- [ ] `npm run lint` + `npx tsc --noEmit` lulus.

## D43-3 — Backend: longgarkan throttle stream
**File:** `api/routes/api.php`
**What:** `/generate/stream` `throttle:10,1` → `throttle:30,1` (selaras `/phase-progress/stream`). Memberi ruang reconnect sah tanpa membuka flood.
**Verifikasi:**
- [ ] `php artisan route:list --path=generate/stream` tampil throttle 30,1.
- [ ] `php artisan test` lulus.

---

## Out of Scope
- Redis-backed limiter khusus / retry-token — YAGNI; tambahkan bila 429 masih muncul setelah fix ini.
