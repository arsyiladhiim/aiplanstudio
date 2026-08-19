# 30 — Fix Pertanyaan Web+Mobile: Validasi Isi MCQ (kolom kosong di tengah)

> Tujuan: pertanyaan klarifikasi (pertanyaan + pertanyaan_mobile) WAJIB 100% valid — tiap pertanyaan punya id/question/options lengkap. Fix: validasi per-item, filter item rusak saat simpan, perketat prompt, guard frontend.

## Root Cause
- `mcqCount()` hanya `count($questions)` → item rusak (id=array, question malformed) ikut dihitung valid → lolos MIN 5 → list tidak 100% lengkap.
- Bukti (version 282): 9 questions, idx 3 rusak (id=array, question/options invalid) → frontend render nomor "4." kosong.

## CHECKPOINT
### CP-1 — Backend validasi isi
- [✅] `AiOutputParser::mcqValidCount()` + `mcqCount()` delegasi.

### CP-2 — Filter saat simpan
- [✅] `PipelineRunner::saveArtifact()` pertanyaan/mobile filter item invalid + throw bila <5 setelah sanitasi (`sanitizeMcqData`).

### CP-3 — Retry pakai valid count
- [✅] `retryPertanyaanForMinimum()` decision pakai mcqCount (= valid).

### CP-4 — Perketat prompt
- [✅] `pertanyaan.php` + `pertanyaan_mobile.php` VERIFY: id/question string, options 5 (A-E), JSON utuh.

### CP-5 — Frontend guard
- [✅] `McqForm.tsx`: skip item tanpa question string valid / options kosong.

### CP-6 — Test
- [✅] mcq_valid_count, filter invalid, throw <5, nested wrapper, save clean — PipelineRunnerTest 46 pass.

### CP-7 — Validasi akhir
- [✅] pint clean; full suite 268 pass + 1 pre-existing Socialite; tsc/lint clean (2 CommandPalette pre-existing).
- [✅] rebuild web + restart; health OK; /new 200.
- [✅] Live check: data 282 (1 item rusak) → mcqValidCount 8 → sanitize → 8 questions bersih re-index (q1..q3,q5..q9).
- [✅] update checkpoint ini + dev-log.

## Status: SEMUA CHECKLIST ✅
Pertanyaan web+mobile kini divalidasi isi (id/question string, options ≥4), item rusak di-filter saat simpan, prompt diperketat (VERIFY 5 point), frontend skip item invalid. Kualitas ≥5 pertanyaan valid dijamin; <5 → error + tombol Coba Lagi tersedia.