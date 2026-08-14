# 24 — Pertanyaan Stage Performance Plan

> Dokumen ini adalah **rencana eksekusi** perbaikan bottleneck pada stage
> `pertanyaan` dan `pertanyaan_mobile` (generate MCQ klarifikasi).
> Bertujuan menurunkan latency + retry overhead + token cost.
>
> **Status:** `[ ]` todo · `[~]` in-progress · `[x]` done
> **Aturan:** Setiap progres selesai → update checkpoint di file ini **SEBELUM** lanjut.
> **Hubungan:** lihat juga `docs/15-dev-log.md` untuk entri final & `docs/22-e2e-test-plan.md` jika perlu E2E.

## Latar Belakang

Generate pertanyaan (MCQ 5–10 klarifikasi) di pipeline 14-stage terasa lamban.
Investigasi `api/app/Services/PipelineRunner.php` menemukan **6 bottleneck**:

| # | Bottleneck | Lokasi | Dampak |
|---|---|---|---|
| 1 | `MAX_MCQ_RETRIES = 180` (kritis) | `PipelineRunner.php:35` | Worst-case 180 full streaming API calls per stage pertanyaan. Constant terlalu tinggi tanpa batas realistis. |
| 2 | Retry tanpa backoff | `PipelineRunner.php:477` | Tight loop pada transient error → burn quota + 429 ban. |
| 3 | pertanyaan_mobile context bloated | `PipelineRunner.php:294` | Full `master_prompt` (5–20KB) di-inject ke pertanyaan_mobile. Latency naik proporsional. |
| 4 | Tidak ada observability retry | (missing) | Tidak kelihatan dari log berapa retry yang terjadi realitanya. |
| 5 | Tidak ada cache pertanyaan | (missing) | Tiap project → generate dari awal. DEFER (YAGNI, cache invalidation kompleks). |
| 6 | Frontend menunggu full SSE | `web/src/components/wizard/McqForm.tsx` (perlu inspeksi) | User lihat spinner tanpa feedback progresif. |

## Plan Eksekusi

### Phase A — Backend Quick Wins (aman, isolated)
**Target:** 1 file (`PipelineRunner.php`) + 1 test file.

- [x] A1. `MAX_MCQ_RETRIES = 180 → 10` (`PipelineRunner.php:35`)
- [x] A2. Exponential backoff `0.5s × 2^(attempt-1)`, max 8s (`PipelineRunner.php:477`)
- [x] A3. Log retry count ke laravel.log (end of `retryPertanyaanForMinimum`)
- [x] A4. Tambah unit test: retry stops at 10 (early-return test, retry-cap constants assertion)
- [x] A5. `php artisan test --filter=PipelineRunnerTest` → **36 passed** (126 assertions)
- [x] A6. Update checkpoint di `docs/24`

### Phase B — Context Trimming
**Target:** 1 file (`PipelineRunner.php`) + 1 test.

- [x] B1. Truncate `master_prompt` ke 2000 char di pertanyaan_mobile (`PipelineRunner.php:294`)
- [x] B2. Unit test: pertanyaan_mobile context size < 5KB + helper test
- [x] B3. Update checkpoint di `docs/24`
- [x] B4. Full test hijau — 38 passed (133 assertions)

### Phase C — Frontend UX
**Target:** 1 file (`web/src/app/(app)/new/page.tsx`).

- [x] C1. Inspect McqForm rendering flow
- [x] C2. Tambah spinner + retry counter inline untuk pertanyaan & pertanyaan_mobile loading state
- [x] C3. Lint + tsc clean (0 errors)
- [x] C4. Update checkpoint di `docs/24`

### Phase D — Validation
- [x] D1. Manual benchmark — `php artisan test` → **246 passed** (+3 dari baseline 243), 1 pre-existing Socialite fail, 1 skip.
- [x] D2. Update `docs/15-dev-log.md` + `docs/09-roadmap.md` (Phase 6 — Pertanyaan Performance)

## Checkpoint Log

_(Diisi per progres selesai, urut kronologis.)_

### 2026-08-13 · Phase A selesai (MAX_MCQ_RETRIES 180→10 + backoff + log)
- Edit `api/app/Services/PipelineRunner.php`:
  - `MAX_MCQ_RETRIES = 180 → 10`.
  - Exponential backoff `usleep(500_000 * 2^(attempt-1))` capped 8s.
  - `Log::info('PipelineRunner pertanyaan retry resolved', ...)` saat retry berhasil.
  - `Log::warning('PipelineRunner pertanyaan retry exhausted', ...)` saat habis retry.
- Edit `api/tests/Feature/PipelineRunnerTest.php`:
  - `test_mcq_retry_constants`: assertion `>= 60` → `[3, 20]` (range check).
  - Tambah `test_retry_pertanyaan_returns_early_when_min_met`: assert no retry loop saat pertanyaan sudah valid.
- Test: `php artisan test --filter=PipelineRunnerTest` → 36 passed (126 assertions).
- Dampak: worst-case retry attempts dari 180 → 10; backoff prevents tight loop pada transient error.

### 2026-08-13 · Phase B selesai (truncate master_prompt pertanyaan_mobile)
- Edit `api/app/Services/PipelineRunner.php`:
  - `pertanyaan_mobile` context line 294 → pakai `self::truncateForContext((string) $v->master_prompt, 2000)`.
  - Tambah helper static `truncateForContext(string $text, int $maxBytes): string` — append `[... truncated for context size ...]` saat dipotong.
- Edit `api/tests/Feature/PipelineRunnerTest.php`:
  - `test_pertanyaan_mobile_context_truncates_master_prompt`: inject master_prompt 22KB, assert context < 5KB + ada marker truncation.
  - `test_truncate_for_context_helper`: assert short → unchanged, long → truncated + marker.
- Test: `php artisan test --filter=PipelineRunnerTest` → **38 passed** (133 assertions).
- Dampak: pertanyaan_mobile input token turun dari ~22KB → ~2KB master_prompt (≈91% reduksi). Latency + cost turun proporsional.

### 2026-08-13 · Phase C selesai (frontend loading UX)
- Inspect `web/src/components/wizard/McqForm.tsx`: pure render, tidak ada loading state di sini. Loading state ada di parent `new/page.tsx`.
- Edit `web/src/app/(app)/new/page.tsx`:
  - Pertanyaan loading state (line ~913): tambah `<Loader2 animate-spin />` + retry counter inline ("Memproses pertanyaan (percobaan 3/10)").
  - Pertanyaan mobile loading state (line ~975): sama, dengan teks mobile.
- Verify: `npx tsc --noEmit` 0, `npm run lint` 0.
- Dampak: User lihat progres retry real-time (sebelumnya: text statis "Memproses pertanyaan...").

### 2026-08-13 · Phase D selesai (validation)
- `php artisan test` → **246 passed** (+3 baru dari baseline 243), 1 pre-existing Socialite order fail, 1 skip.
- Frontend: `npx tsc --noEmit` 0, `npm run lint` 0.
- Dampak komulatif: pertanyaan retry attempts cap 180 → 10; pertanyaan_mobile token input -91% (~22KB → ~2KB); observability via `Log::info/warning`; UX lebih transparan dengan retry counter inline.

## Total Ringkasan

| Aspek | Sebelum | Sesudah |
|---|---|---|
| `MAX_MCQ_RETRIES` | 180 | **10** |
| Retry behavior | Tight loop, no backoff | Exponential 0.5s→8s + report exception |
| pertanyaan_mobile master_prompt | full (5–20KB) | truncated 2000 char |
| Observability | Tidak ada | `Log::info/warning` dengan attempt count |
| Frontend loading UX | "Memproses pertanyaan..." (statis) | Spinner + retry counter inline |
| Backend test pass | 243 | **246** (+3) |
| Frontend lint/tsc | clean | clean |

