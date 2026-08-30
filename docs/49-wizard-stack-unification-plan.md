# 49 — Wizard Stack Unification + Wizard/Projects Fix Plan

Keputusan stack (hasil konfirmasi): **PHP 8.4 · Laravel 13.x · Next.js 16 · Node 24 LTS · PostgreSQL 18 (hanya di output prompt, bukan infra repo ini) · React 19 · Tailwind CSS v4**.

## Fase F — Bug `testing_strategy`

- [x] F1. `api/app/Prompts/testing_strategy.php` return array → closure fn (akar: fatal "Array callback must have exactly two elements")
- [x] F2. `SpecGate::PREREQ` tambah `testing_strategy => ['standards_web', 'phases_web']`
- [x] F3. Test: semua file `app/Prompts/*.php` harus callable (mencegah regresi format)

## Fase G — StackSpec single source of truth

- [ ] G1. Buat `api/app/Support/StackSpec.php` (PHP 8.4, Laravel 13, Next.js 16, Node 24, PostgreSQL 18, React 19, Tailwind v4)
- [ ] G2. Ganti versi hardcoded: `StageContextBuilder.php:219-220` (Laravel 11!), `Prompts/helpers.php`, `architecture.php`, `phased_master.php`, `standards.php:104` (Next.js 15), `agents.php`, mobile master bila relevan
- [ ] G3. `web/src/app/(app)/settings/about/page.tsx` badge: PHP 8.4 / PostgreSQL 18 (prompt platform target)
- [ ] G4. Pesannya konsisten untuk target web & both; test prompt-loadable tetap hijau

## Fase H — Wizard & Projects fixes

- [ ] H1. `ProjectController::index` dukung filter `target` (UI sudah kirim)
- [ ] H2. `projects/[id]/page.tsx` error banner: tampilkan error pasca-load (kondisi `error && !project` dibuang)
- [ ] H3. `/new` resume spinner render `resumeError` (sekarang dead-end tanpa pesan)
- [ ] H4. Render status `blocked`/`skipped` di rail & `StageRow` (tipe + UI + reason) — verify.* stages jelas
- [ ] H5. Verify: tsc, lint, pint, `php artisan test` penuh, e2e ringan manual
- [ ] H6. Update checkpoint doc + commit/push

## Batasan
- Tidak upgrade DB repo ke PG18 (output prompt saja).
- Komponen mati (WizardEvidenceBadge dll.) tidak dihapus dulu, dicatat saja.
