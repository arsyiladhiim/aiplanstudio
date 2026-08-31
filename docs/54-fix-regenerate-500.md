# 54 — Fix Regenerate 500 (SseEmitter stream ditutup prematur)

Sumber: log prod — `[regenerateStage] Error: fwrite(): supplied resource is not a valid stream resource` (`SseEmitter.php:32`) saat `POST /versions/{id}/regenerate` di wizard.

Akar: `VersionController::regenerateStage` membuat 2 `PipelineRunner` dengan stream `php://memory` yang sama. Runner#1 (`invalidateDependents`) direassign → destructor-nya menutup stream → runner#2 `fwrite` ke stream mati → 500.

## Checklist

- [x] M1. `SseEmitter::__destruct` hanya fclose stream yang dibuat sendiri (default `php://output`); stream injeksi jangan disentuh
- [x] M2. `regenerateStage`: satu runner untuk invalidate + run (refresh version di antara)
- [x] M3. Frontend: banner error saat regen gagal (ok:false/catch) — toast/pesan jelas, tidak diam
- [x] M4. Test: SseEmitter unit (injected stream tidak ditutup) + feature regenerate stage `erd` (JSONB) → 200 ok=true
- [x] M5. `php artisan test` penuh + pint + restart api + commit/push
