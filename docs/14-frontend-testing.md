# 14 — Frontend Testing (Playwright + Chrome)

> Lihat juga: [08-frontend](08-frontend.md) · [05-wizard-flow](05-wizard-flow.md) · [15-dev-log](15-dev-log.md)
> Uji **real browser (Chromium/Chrome)** dengan Playwright: **setiap button & menu diklik dan diverifikasi**. Bila ada error → **perbaiki sampai fix**, catat di [15-dev-log](15-dev-log.md).

## Prinsip
- E2E lewat browser nyata terhadap app yang jalan di Docker (`http://localhost`).
- **Loop wajib:** jalankan test → jika gagal/error → perbaiki kode → jalankan ulang → **ulangi sampai semua hijau**. Jangan tandai fase selesai bila masih ada test merah.
- Setiap elemen interaktif (button, link menu, form submit, toggle) punya assertion hasilnya (navigasi/berubah/muncul).
- Catat tiap run (pass/fail + error + perbaikan) di [15-dev-log](15-dev-log.md).

## Setup
```bash
# Dari host (spec ada di web/e2e/)
cd web && npx playwright test
cd web && npx playwright test --headed      # lihat browser
cd web && npx playwright test --ui          # mode UI (debug)
```
- Config `web/playwright.config.ts`: `baseURL: 'http://localhost'`, `actionTimeout: 15000`, `navigationTimeout: 30000`, `projects: [{ name:'chromium', use:{ ...devices['Desktop Chrome'] } }]`, `trace:'on-first-retry'`, `screenshot:'only-on-failure'`, `video:'retain-on-failure'`.
- Struktur: `web/e2e/*.spec.ts` (11 files, ~1410 lines).
- Shared helpers: `web/e2e/helpers.ts` (loginAsAdmin, loginAs, navTo, consoleErrorCollector, registerUser).

## Cakupan per Spec

| Spek | Cakupan | Status |
|------|---------|--------|
| `auth.spec.ts` | Login form, login sukses/gagal, logout, protected routes, guard | ✅ |
| `full.spec.ts` | Smoke test semua halaman (auth, projects, templates, landing, dashboard, settings, nav, wizard) | ✅ |
| `register.spec.ts` | Register form, valid credentials, duplicate email, short password, login after register | ✅ |
| `rbac.spec.ts` | Admin access settings, unauthenticated redirect, authenticated protected routes | ✅ |
| `wizard.spec.ts` | Wizard form, target buttons, auto-run, submit enable/disable, 6 stages, reset | ✅ |
| `wizard-e2e.spec.ts` | Target selection state, auto-run toggle, submit states, stack field | ✅ |
| `project-detail.spec.ts` | Projects list, empty state, project detail elements, tab switching, 404 | ✅ |
| `projects-templates.spec.ts` | Projects list, CTA nav, templates page, template cards, landing page | ✅ |
| `settings-nav.spec.ts` | Sidebar navigation, "Buat Plan Baru" CTA | ✅ |
| `settings-crud.spec.ts` | Provider form fields, save, test connection, user list, admin delete disabled | ✅ |

## Smoke Test Menyeluruh (wajib sebelum F9 selesai)
Satu spec yang **mengklik seluruh menu & tombol utama** berurutan (crawl UI) dan memastikan tak ada:
- error konsol browser (`page.on('console')` error/`pageerror`),
- request gagal (4xx/5xx) pada aksi utama,
- broken navigation.
Bila ditemukan → perbaiki → ulang sampai bersih.

## Definition of Done (frontend)
- Semua spec fase **hijau di Chromium** (`playwright test` exit 0), tanpa error konsol pada alur utama.
- Artefak kegagalan (trace/screenshot/video) nol pada run final.
- Loop perbaikan & hasil dicatat di [15-dev-log](15-dev-log.md).
