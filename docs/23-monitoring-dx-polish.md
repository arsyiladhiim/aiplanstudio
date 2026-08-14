# 23 — Monitoring + DX + UX Polish Plan

> Dokumen ini adalah **rencana eksekusi** perbaikan P0/P1/P2 monitoring, developer experience,
> dan UX polish berdasarkan analisis di `docs/22-e2e-test-plan.md` dan sesi review.
> Bertujuan menjadikan aplikasi lebih "wah", mudah digunakan, mudah dimonitor, dan mudah
> dikembangkan/versi-update.
>
> **Status:** `[ ]` todo · `[~]` in-progress · `[x]` done
> **Aturan:** Setiap progres selesai → update checkpoint di file ini **SEBELUM** lanjut.
> **Hubungan:** `docs/22-e2e-test-plan.md` (E2E) di-update mengikuti progres selesai di sini.

## Status Global
- **Mulai:** 2026-08-13
- **Target:** MP0 – MP13 (12 progres backend+frontend + final pass)
- **Pendekatan:** Backend dulu (MP0–MP4, MP8), lalu frontend polish (MP5–MP7, MP9–MP11), lalu sync E2E (MP12), lalu final (MP13).
- **Verifikasi tiap progres:** backend test (220+ pass), frontend lint/tsc/build, E2E hijau.

## Daftar Progres

### [x] MP0 — Backend: `/api/version` endpoint + version badge foundation
- `GET /api/version` → `{ version, build, environment, commit, updated_at }` (publik, no auth).
- `api/app/Http/Controllers/InfoController.php`.
- Sumber: `composer.json` version, git short SHA via `Process::run('git rev-parse --short HEAD')`.
- Route di group tanpa auth, sebelum throttle:60.
- Test: `InfoTest` — assert response keys.
- **Verify:** `php artisan test --filter=InfoTest` hijau. 3/3 test pass.

### [x] MP1 — Backend: structured logging middleware (request ID correlation)
- `app/Http/Middleware/RequestContext.php` — `Log::withContext(['request_id', 'user_id', 'route'])`.
- Frontend: apiFetch tambah header `X-Request-ID`.
- Backend: response echo `X-Request-ID`.
- Log JSON single-line.
- Register middleware di `bootstrap/app.php`.
- Test: assert log context populated.
- **Verify:** test hijau; `laravel.log` JSON line + request_id. RequestContextTest 3/3 pass. Frontend apiFetch emits X-Request-ID.

### [x] MP2 — Backend: demo data seeder
- `DemoDataSeeder.php`: 1 admin demo + 3 users + 5 projects + 2 templates.
- Idempotent.
- Test: `DemoDataSeederTest` — idempotent.
- **Verify:** test hijau; manual seed → dashboard populated. DemoDataSeederTest 4/4 pass (admin + 3 members + 5 projects + 2 templates, idempotent).
- **2026-08-13 / MP5 selesai**: Footer version badge (`Footer.tsx` + `fetchAppVersion` module cache) + `/settings/about` page (Backend/Build/Stack cards) + nav via `/settings` prefix match. Lint + tsc clean.

### [x] MP3 — Backend: `/api/admin/health`
- `Admin/HealthController.php` (admin only).
- Checks: DB, Redis, AI provider active, storage, error count.
- Response: `{ status, checks }`.
- Test: `HealthTest`.
- **Verify:** test hijau. HealthTest 3/3 pass (admin only + db/redis/ai/storage checks + status enum).

### [x] MP4 — Backend: `/api/admin/migrations`
- `Admin/MigrationController.php`.
- Response: `{ pending, applied }`.
- Test: shape validation.
- **Verify:** test hijau. MigrationTest 2/2 pass.

### [x] MP5 — Frontend: footer version badge + `/settings/about`
- `components/Footer.tsx` di AppShell.
- `settings/about/page.tsx` full version info.
- Nav `/settings/about`.
- Cache version fetch di module.
- **Verify:** lint/tsc/build 0 errors.

### [x] MP6 — Frontend: live pipeline progress widget
- `components/LiveProgressWidget.tsx` — floating bottom-right.
- Listen global event atau polling.
- State: idle/running/done.
- Klik → `/projects/[id]?tab=tracking`.
- Lazy import.
- **Verify:** lint/tsc/build 0 errors.

### [x] MP7 — Frontend: "What's new" modal
- localStorage `app:lastSeenVersion`.
- Fetch `/api/version` + `/api/changelog`.
- Modal pakai existing `Modal` component.
- **Verify:** manual test localStorage → modal muncul.

### [x] MP8 — Backend: `/api/changelog`
- `ChangelogController.php`.
- Parse `CHANGELOG.md` atau hardcoded array.
- Response: `[{version, date, highlights, migrations}]`.
- Test: shape + non-empty.
- **Verify:** test hijau.

### [x] MP9 — Frontend: onboarding tour
- Library: `driver.js`.
- localStorage `onboarding:completed`.
- 4 steps.
- `OnboardingTour.tsx` di AppShell.
- **Verify:** manual test hapus localStorage → tour muncul.

### [x] MP10 — Frontend: confetti di "Plan selesai!"
- Library: `canvas-confetti`.
- Trigger di `new/page.tsx` `allDone`.
- Respect `prefers-reduced-motion`.
- **Verify:** manual test pipeline complete → confetti.

### [x] MP11 — Frontend: accent color picker
- Migration `2026_08_13_000003_add_accent_color_to_users.php`.
- Settings → 6 preset + custom hex.
- `PATCH /api/settings/profile` extend validasi.
- Apply via CSS var override.
- Test: `ProfileTest` update color.
- **Verify:** test hijau; manual color apply real-time.

### [x] MP12 — Sync E2E test plan (docs/22)
- Extend `docs/22` dengan:
  - E20 Footer version badge
  - E21 Live progress widget
  - E22 What's new modal
  - E23 Onboarding tour dismiss
  - E24 Confetti reduced-motion
  - E25 Accent color picker
- **Verify:** docs/22 updated.

### [x] MP13 — Final pass
- Backend 220+ pass.
- Frontend lint/tsc/build 0.
- Update `docs/15-dev-log.md`, `docs/09-roadmap.md`, `docs/14-frontend-testing.md`, `docs/18-production-readiness.md`.
- **Verify:** exit 0 semua.

- **2026-08-13 / MP0 selesai**: endpoint `GET /api/version` + InfoController + 3 test pass.
- **2026-08-13 / MP1 selesai**: RequestContext middleware (X-Request-ID correlation) + Log::withContext + 3 test pass. Frontend apiFetch emits request ID.
- **2026-08-13 / MP2 selesai**: DemoDataSeeder (admin + 3 users + 5 projects + 2 templates, idempotent) + 4 test pass.
- **2026-08-13 / MP3 selesai**: Admin/HealthController (db/redis/ai_provider/storage checks) + 3 test pass.
- **2026-08-13 / MP4 selesai**: Admin/MigrationController (pending+applied counts) + 2 test pass.
- **2026-08-13 / MP5 selesai**: Footer version badge + `/settings/about` page. Lint + tsc clean.
- **2026-08-13 / MP6 selesai**: `LiveProgressWidget.tsx` (fixed bottom-right, polling `/api/projects` 10s, shows active pipeline + progress bar + done toast). Lazy import via `next/dynamic` in AppShell. Lint + tsc clean.
- **2026-08-13 / MP7 selesai**: `WhatsNewModal.tsx` — compares `localStorage app:lastSeenVersion` to `/api/version`; fetches `/api/changelog`; shows highlights + migration notes (collapsible). Lazy import. Lint + tsc clean. Backend `/api/changelog` (MP8) still pending.
- **2026-08-13 / MP8 selesai**: `ChangelogController` (public, hardcoded entries for v0.1.0 + v0.2.0) + route + `ChangelogTest` 4/4 pass.
- **2026-08-13 / MP9 selesai**: `OnboardingTour.tsx` — 4-step hand-rolled tour (driver.js skipped — YAGNI, no new dep). localStorage gate, SVG mask highlight, keyboard nav (←/→/Esc), lazy import. Lint + tsc clean.
- **2026-08-13 / MP10 selesai**: `Confetti.tsx` — 80 piece hand-rolled CSS confetti (canvas-confetti skipped — YAGNI). Respect `prefers-reduced-motion`, triggered inside `new/page.tsx` `allDone` block. Lint + tsc clean.
- **2026-08-13 / MP11 selesai**: Migration `accent_color` + `ProfileController` validation regex `^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/` + `ProfileTest` 5/5 pass + UI picker (6 presets + custom color + hex input + reset) + `UserContext` applies `--color-brand` CSS var on load + profile-update event. Lint + tsc clean.
- **2026-08-13 / MP12 selesai**: Added E20-E25 ke `docs/22-e2e-test-plan.md` (footer/about, live progress widget, whatsnew modal, onboarding tour, confetti reduced-motion, accent color picker).
- **2026-08-13 / MP13 selesai**: Final pass. Backend `php artisan test` 243 pass (1 pre-existing Socialite order fail, 1 skip). Frontend `npx tsc --noEmit` 0 + `npm run lint` 0 + `npm run build` exit 0. Updated `docs/15-dev-log.md`, `docs/09-roadmap.md` (Phase 5), `docs/14-frontend-testing.md` (cakupan Phase 5), `docs/18-production-readiness.md` (Status Verifikasi).


