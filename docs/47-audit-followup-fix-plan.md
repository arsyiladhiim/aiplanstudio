# 47 — Audit Follow-up Fix Plan (Post Resume-Loop 65c4bc1)

Sumber temuan: audit menyeluruh 30 Aug 2026 (post fix resume loop). Setiap fase: cek → fix → verifikasi → update checklist.

## Fase A — Resume pipeline (backend bug di `useResume` + cancel race)

- [x] A1. Resume project fully-done auto-restart dari `pertanyaan` (`useResume.ts:79-82,171-175`) — `resumeInfo: null` saat `firstIdx === -1`
- [x] A2. Resume blocked oleh master_web juga auto-start salah stage (`useResume.ts:151-156`) — encode blocked pada `resumeInfo`, page tidak auto-start
- [x] A3. Cancel race: page `cancelGeneration` tidak memanggil `streamApi.cancelAll()` — queued retry masih bisa POST (`page.tsx:290` vs `usePipelineStream.ts:34`)
- [x] A4. `reset()` tidak reset `resumeAutoStartedRef` (page.tsx ~809-850)

## Fase B — Keamanan

- [x] B1. SSRF redirect bypass: Guzzle `allow_redirects` tidak divalidasi ulang (`AiClient.php:203-205`)
- [x] B2. Registrasi pertama otomatis admin tanpa gate (`AuthController.php:30`, `SocialiteController.php:49`)

## Fase C — Operasional

- [ ] C1. Stage stuck `running` selamanya setelah container restart — sweeper terjadwal (`PipelineRunner.php:186-193`; `console.php` hanya `research:collect`)
- [ ] C2. Scheduler tanpa healthcheck di `docker-compose.yml`

## Fase D — Housekeeping

- [ ] D1. Rebuild graphify (`/graphify .`)
- [ ] D2. Final verification: tsc + lint + `php artisan test` + commit/push
