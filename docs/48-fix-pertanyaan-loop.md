# 48 — Fix Looping Generate Stage `pertanyaan`

Sumber: analisa post 47 (docs/47). Temuan utama di `api/app/Services/PipelineRunner.php`:

1. Token budget 1500 vs prompt minta 8–12 MCQ → output terpotong → retry 10× (user lihat loop generate).
2. Text fallback bikin options plain string → dijamin gagal `sanitizeMcqData` → stage error → retry lagi.
3. Nol idempotensi: re-POST `/generate/stream` menjalankan ulang stage walau sudah `done`.

## Fase E — perketat stage `pertanyaan`

- [x] E1. Idempotency guard di `PipelineRunner::run()` loop: stage `done` + artifact terisi → emit artifact+done, skip (berlaku semua stage)
- [x] E2. Fix `buildQuestionsFromText()` fallback: options jadi `[{key,text}×4]` agar lolos `sanitizeMcqData()`
- [x] E3. Satukan counter retry: pakai `mcqValidCount()` konsisten dengan saveArtifact
- [x] E4. Realign resource: `STAGE_MAX_TOKENS[pertanyaan]` 1500→3000, `MAX_MCQ_RETRIES` 10→2, samakan prompt min dengan `MIN_MCQ_QUESTIONS`

- [x] E5. Verifikasi: `php artisan test` (PipelineRunnerTest, GenerateStreamTest, full suite) + pint
- [x] E6. Update dok progres + commit/push
