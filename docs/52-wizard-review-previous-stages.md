# 52 — Wizard Review Stage Sebelumnya + Regenerate On-Demand

Desain keputusan (dari user):
1. Klik stage sebelumnya (status done) = **review saja** (lihat artifact). Regenerate hanya via tombol.
2. Saat regenerate diminta dan pipeline masih jalan → **abort stream yang berjalan, lalu regen**.

Constraint produk: `master_web` & `master_mobile` TIDAK boleh menampilkan tombol kembali/preview via rail (tidak clickable).

Backend sudah ada: `POST /versions/{id}/regenerate {stage}` (VersionController::regenerateStage — invalidasi dependents via PipelineRunner::invalidateDependents, jalan synchronous, rollback bila error).

## Checklist

- [x] K1. State `viewStage` di `/new` — view mode terpisah dari `current` (posisi pipeline)
- [x] K2. Rail row clickable bila status done && !master stages → set viewStage; rail/master row tetap non-clickable
- [x] K3. Panel konten render menggunakan effective key (viewStage ?? activeKey); banner "Sedang melihat <label>" + tombol Kembali ke stage aktif
- [x] K4. Tombol "Generate ulang dari stage ini" + ConfirmModal (peringatan dependents reset); saat dipanggil: `streamApi.abort()` (stop stream berjalan) → POST regenerate → refetch `/versions/{id}` → clear viewStage → toast error bila gagal (rollback backend)
- [x] K5. Hide controls advance/approve saat view mode aktif; sembunyikan tombol regen pada pertanyaan stages (MCQ berdampak jawaban)? — keep: regen tetap boleh untuk pertanyaan dimungkinkan; konsisten
- [x] K6. Verify: tsc + lint + prettier, rebuild web, commit/push
