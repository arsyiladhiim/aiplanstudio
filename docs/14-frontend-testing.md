# 14 — Frontend Testing (Playwright + Chrome)

> Lihat juga: [08-frontend](08-frontend.md) · [05-wizard-flow](05-wizard-flow.md) · [15-dev-log](15-dev-log.md)
> Uji **real browser (Chromium/Chrome)** dengan Playwright: **setiap button & menu diklik dan diverifikasi**. Bila ada error → **perbaiki sampai fix**, catat di [15-dev-log](15-dev-log.md).

## Prinsip
- E2E lewat browser nyata terhadap app yang jalan di Docker (`http://localhost`).
- **Loop wajib:** jalankan test → jika gagal/error → perbaiki kode → jalankan ulang → **ulangi sampai semua hijau**. Jangan tandai fase selesai bila masih ada test merah.
- Setiap elemen interaktif (button, link menu, form submit, toggle) punya assertion hasilnya (navigasi/berubah/muncul).
- Catat tiap run (pass/fail + error + perbaikan) di [15-dev-log](15-dev-log.md).

## Setup

### Unit / Smoke (host Chromium)
```bash
# Dari host (spec ada di web/e2e/)
cd web && npx playwright test
cd web && npx playwright test --headed      # lihat browser
cd web && npx playwright test --ui          # mode UI (debug)
```
- Config `web/playwright.config.ts`: `baseURL: 'http://localhost'`, `actionTimeout: 15000`, `navigationTimeout: 30000`, `projects: [{ name:'chromium', use:{ ...devices['Desktop Chrome'] } }]`, `trace:'on-first-retry'`, `screenshot:'only-on-failure'`, `video:'retain-on-failure'`.

### E2E (browser wajib via Docker — host Linux kurang libs browser)
```bash
cd web
docker run --rm --network host -v "$PWD/web":/work -w /work \
  -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
  -e E2E_BASE_URL=http://localhost:4197 \
  mcr.microsoft.com/playwright:v1.62.0-noble \
  npx playwright test --config=playwright.e2e.config.ts
```
- Config `web/playwright.e2e.config.ts`: baseURL `E2E_BASE_URL`, `globalSetup: e2e/global-setup.ts` (login API + simpan state auth), 2 retries, screenshot/video `retain-on-failure`.
- Pre-req: stack up + dev DB tersedia (login `admin@aistack.dev` / `password123`).
- **Struktur:** `web/e2e/*.spec.ts` + `helpers.ts` (`ensureAuthed`, `consoleErrorCollector`) + `global-setup.ts`.
- **Artefak** (`web/test-results/`, `web/e2e/.auth/`, `web/out.png`, `web/playwright-report/`) di-`gitignore`; cleanup root-owned files via `docker run --rm -v "$PWD/web":/w alpine sh -c 'rm -rf /w/test-results /w/e2e/.auth'`.

## Cakupan Spec Aktual

**E2E suite (3 spec files / 10 test):**
| Spec | Cakupan |
|---|---|
| `e2e/auth.spec.ts` | login sukses, login salah ditolak, logout |
| `e2e/wizard.spec.ts` | render wizard, validasi input, submit → real AI pipeline (13-stage) → phases persist |
| `e2e/projects.spec.ts` | list project, create via API, open detail, tab navigasi, delete |

Semua alur pakai real backend di Docker; test login tidak lewat UI throttle (`throttle:5,1`) — global-setup pakai API login sekali.

## Smoke Test Menyeluruh
Satu spec yang **mengklik seluruh menu & tombol utama** berurutan (crawl UI) dan memastikan tak ada:
- error konsol browser (`page.on('console')` error/`pageerror`),
- request gagal (4xx/5xx) pada aksi utama,
- broken navigation.
Bila ditemukan → perbaiki → ulang sampai bersih.

## Definition of Done (frontend)
- Semua spec fase **hijau di Chromium** (`playwright test` exit 0), tanpa error konsol pada alur utama.
- Artefak kegagalan (trace/screenshot/video) nol pada run final.
- Loop perbaikan & hasil dicatat di [15-dev-log](15-dev-log.md).
