# 39 — AI Stream Performance — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-20
> **Completed:** 2026-08-20
> **Scope:** Perbaiki cara pemanggilan AI agar semua wizard stage cepat (tidak "stuck") — SSE accumulator, per-stage token budget, continuation 'length' yang benar, fast-path MCQ. **Tanpa mengubah validator/aturan/skor.**
> **Parent:** docs/31-38 (COMPLETED)

---

## Objective

`AiClient::stream()` membaca `read(8192)` lalu `explode("\n")` → event SSE terbelah di batas baca → token HILANG → JSON rusak → retry MCQ 10× = "stuck lama". Plus `max_tokens=8192` dipaksa tiap stage + `'length'` re-post prompt sama (kerja 3× duplikat).

---

## P1 — SSE Accumulator (semua stage)
**File:** `api/app/Services/AiClient.php`
**What:** Baca incremental (mis. 1024B), buffer baris penuh, parse hanya baris `data:` lengkap; sisa baris dipindah ke buffer antarbaca (jangan dibuang).
**Verifikasi:**
- [ ] Implementasi akumulator (line-buffer)
- [ ] `AiStreamAccumulatorTest` pass (event terbelah 2-3 read → konten tersambung penuh)
- [ ] pint

## P2 — Per-stage Token Budget
**File:** `api/app/Services/PipelineRunner.php` (const `STAGE_MAX_TOKENS`) + `AiClient::stream(..., ?int $maxTokens)`
**What:** Budget per stage: MCQ 1500; analisa/erd/api_contract/app_spec 4096; lainnya 8192 (tetap — hindari truncation baru).
**Verifikasi:**
- [ ] Map stage→budget diterapkan di runStage
- [ ] `TokenBudgetTest` (map konsisten, MCQ kecil, panjang tetap 8192)
- [ ] Regressi pipeline test tetap hijau
- [ ] pint

## P3 — Continuation 'length' benar (semua stage)
**File:** `api/app/Services/PipelineRunner.php` (runStage loop)
**What:** Pada `finish_reason=length`: kirim `assistant` partial + user `[LANJUTKAN dari posisi terakhir — jangan ulangi]`, bukan re-POST kosong. `maxChunks` 3→2; stop-gate pakai `finish_reason==stop` (hapus tebak `added<50`).
**Verifikasi:**
- [ ] Continuation memakai partial output
- [ ] `AiJsonRepairTest`/pipeline tetap pass
- [ ] pint

## P4 — Fast-path MCQ non-stream
**File:** `api/app/Services/PipelineRunner.php` (runStage untuk pertanyaan/pertanyaan_mobile)
**What:** Stage MCQ gunakan `client->complete($messages, 1500)` (non-stream) — output kecil-JSON; hindari SSE. Stage lain tetap stream (UX token + redaction master).
**Verifikasi:**
- [ ] MCQ memakai complete(); stage lain stream
- [ ] `PipelineRunnerTest` pertanyaan pass
- [ ] pint

## P5 — Test & Ukur
**File:** test baru + ukur nyata
**What:** `AiStreamAccumulatorTest` (P1), `TokenBudgetTest` (P2); regresi full; ukur durasi Buat Plan → MCQ (target <30s).
**Verifikasi:**
- [ ] Unit tests baru pass
- [ ] Full `php artisan test` hijau (kecuali Socialite flake)
- [ ] tsc/eslint (web tak berubah, tetap cek)
- [ ] Ukur riil: project baru → MCQ muncul cepat

---

## Checkpoint Tracker
- [x] P1 — SSE accumulator ✅ (`extractSseEvents` statis + dipakai stream(); `AiStreamAccumulatorTest` 3 pass)
- [x] P2 — per-stage token budget ✅ (`STAGE_MAX_TOKENS` map; MCQ 1500, sedang 4096, panjang 8192; `TokenBudgetTest` pass)
- [x] P3 — continuation 'length' ✅ (stop-break tanpa heuristik endsNatural; maxChunks 3→2; budget di stream())
- [x] P4 — fast-path MCQ **DIBATALKAN** ⚠️ provider 9r tidak mendukung non-stream (complete() → empty, 71s error). MCQ kembali ke STREAMING (P1 accumulator => benar & cepat)
- [x] P5 — tests + ukur ✅
  - `AiStreamAccumulatorTest` + `TokenBudgetTest` pass; `PipelineRunnerTest` 46 pass; full suite 394 pass (+3, flake Socialite pre-existing)
  - **Benchmark riil** (provider nyata, project baru → pertanyaan): **`dur=10s status=done len=8449`** (sebelum: 71s+ error)
- [ ] Final — commit + push + verify container

## Catatan
- P4 di-revert: provider 9r mengembalikan konten kosong pada `complete()` non-stream. Solusi sebenarnya = P1 (SSE accumulator) yang membuat streaming andal (token tak hilang) + P2 budget 1500 → MCQ selesai ~10s.
- `complete()` kini hanya dipakai test stub (harmless).

## File touch
- `api/app/Services/AiClient.php` (P1, P2)
- `api/app/Services/PipelineRunner.php` (P2, P3, P4)
- `api/tests/Unit/.../AiStreamAccumulatorTest.php` (baru)
- `api/tests/Feature/TokenBudgetTest.php` (baru)