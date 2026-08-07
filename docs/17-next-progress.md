# 17 — Next Progress

> Baca [09-roadmap](09-roadmap.md) dulu untuk konteks status proyek.
> Setiap item harus memiliki: definition of done, estimated effort, priority.

## Status Saat Ini

Semua fase utama (F0–F10, R1, P1–P14) **selesai**.
**Tidak ada fase aktif.** Item di bawah ini adalah next steps terprioritas.

**Yang sudah selesai:**
- Backend Laravel (7-stage pipeline, auth, projects, versioning, activity log, API tokens)
- Frontend Next.js (17 pages, tsc 0 errors, lint 0 errors)
- Full BFF pattern (nginx → Next.js → Laravel)
- 3-schema PostgreSQL database
- Auth: Sanctum SPA + User approval flow
- Docker Compose (7 services)

---

## Prioritas Tinggi

### [x] [P1] Bug — pertanyaan Stage Content Tidak Tersimpan

**Fix:** Tambah kolom `pertanyaan` (text) di versions, update PipelineRunner saveArtifact map, VersionController colMap, frontend colMap, Version type, export markdown.

**Files changed:**
- `api/database/migrations/2026_08_06_000000_add_pertanyaan_to_versions.php`
- `api/app/Models/Version.php`
- `api/app/Services/PipelineRunner.php`
- `api/app/Http/Controllers/VersionController.php`
- `web/src/lib/api.ts`
- `web/src/app/(app)/new/page.tsx`
- `docs/03-database-schema.md`, `docs/05-wizard-flow.md`, `docs/06-ai-pipeline.md`

**Definition of Done:**
- Pertanyaan content persist di DB ✅
- Page refresh → pertanyaan tetap muncul ✅
- Jawaban bisa disimpan dan dirujuk saat regenerate analisa ✅

### [x] [P2] Bug — middleware.ts Tidak Ada ✅

**Resolution:** Next.js 16 mengganti `middleware.ts` → `proxy.ts`. `web/src/proxy.ts` **sudah ada** dan mengimplementasikan guard route (redirect unauthenticated users ke /login). RS-7 bukan false-positive — sudah resolved via proxy.ts. `middleware.ts` tidak boleh ada (konflik build Next.js 16).

**Effort:** Low (15 menit)

### [x] [P3] API Contract Docs Sinkronisasi ✅

**Verification:** Semua 43 endpoint di 04-api-contract.md ✅ terverifikasi ada di routes/api.php + BFF routes. 3 BFF routes tidak perlu karena proxy ke Laravel GET route.

**Effort:** Medium (1-2 jam)

---

## Prioritas Sedang

### [x] [P4] E2E Test Suite — Minimal Viable Coverage ✅

**Resolution:** E2E suite dibangun & hijau: **3 spec files / 10 test hijau** (`auth.spec.ts`, `wizard.spec.ts`, `projects.spec.ts`) via Playwright di Docker Chromium.

**Cakupan spec aktual:**
- `web/e2e/auth.spec.ts` — login, reject login salah, logout
- `web/e2e/wizard.spec.ts` — wizard page, validasi, submit real AI pipeline (7-stage → phases persist)
- `web/e2e/projects.spec.ts` — list, create, open detail, tabs, delete
- `web/e2e/helpers.ts` — `ensureAuthed` (API login via global-setup) + `consoleErrorCollector`
- `web/playwright.e2e.config.ts` — config E2E terpisah, 2x retry, screenshot/video retain-on-failure

**Bug serius ditemukan & diperbaiki selama P4 (di luar scope E2E):**
1. **Sesi rusak pada request stateful (401 beruntun)** — `bootstrap/app.php` menjalankan pipeline sesi di api group `[EncryptCookies, AddQueuedCookies, StartSession, ShareErrorsFromSession]` LALU Sanctum `EnsureFrontendRequestsAreStateful` menjalankan stack yang sama lagi. `EncryptCookies` kedua gagal dekripsi cookie yang sudah didekripsi → cookie dinull-kan → session ID baru tiap request → DB update 0 row → 401 selanjutnya. **Fix:** `api/app/Http/Middleware/StartSessionIfStateless.php` baru — pipeline sesi dijalankan **hanya jika non-stateful**; jika stateful langsung `$next`. `bootstrap/app.php` → `[StartSessionIfStateless, EnsureFrontendRequestsAreStateful]`.
2. **`php artisan test` menghapus DB dev** — `docker-compose.yml` mengekspor `api/.env` via `env_file:` sehingga `DB_*` jadi real env; PHPUnit 12 menghapus dukungan atribut `force`, `migrate:fresh` menimpa DB `aiplanstudio`. **Fix:** hapus `env_file: ./api/.env` dari service `api-fpm` & `migrate` (Laravel baca `.env` sendiri) + hapus semua atribut `force="true"` di `api/phpunit.xml`.

**Verification:**
- E2E full suite: `10 passed` (2x, ~27s) ✅
- `php artisan test`: 126 passed ✅ (dev DB aman — 1 user tetap ada setelah test run)
- `npm run lint` & `tsc --noEmit`: bersih ✅

**Jalankan E2E (host Linux, browser wajib via Docker):**
```bash
docker run --rm --network host -v "$PWD/web":/work -w /work \
  -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright \
  -e E2E_BASE_URL=http://localhost:4197 \
  mcr.microsoft.com/playwright:v1.62.0-noble \
  npx playwright test --config=playwright.e2e.config.ts
```

**Effort:** High (4-6 jam)

### [x] [P5] Real AI Integration Test ✅

**Result:** Pipeline penuh terverifikasi dengan AI provider nyata (`https://9r.arsyiladm.my.id/v1`, model `aiplanstudio`).

Semua 6 stage (target web) berjalan end-to-end dan artifact persisted:
- `pertanyaan` → text column ✅ (P1 fix diverifikasi di pipeline nyata)
- `analisa` → analysis ✅
- `prd` → prd ✅
- `architecture` → architecture ✅ (raw text — fix parser terlalu strict)
- `erd` → erd (JSON nodes/edges) + `api_contract` (6 endpoints) ✅
- `phased_master` → phases + master_prompt + standards + agents ✅

**Bug nyata ditemukan & diperbaiki saat P5:**
1. **architecture stage selalu error** — `saveArtifact()` throw bila `parseArchText()` null, tapi prompt minta format bebas. Fix: architecture disimpan sebagai text mentah (sesuai docs), parse opsional.
2. **erd stage selalu error + api_contract kosong** — prompt minta `===x===`/`Field:`, parser butuh `TABEL:`/`RELASI:`/`API:`. Fix: rewrite `app/Prompts/erd.php` dengan format garis yang cocok parser.
3. **healthcheck api container** — `localhost` resolve IPv6 (wget gagal), pakai `127.0.0.1`. Fix di docker-compose.yml.

**Effort:** Medium (2-3 jam)

### [x] [P6] Export Mobile Artifacts ✅

**Resolution:** Export .md & .zip kini include semua mobile artifacts.
- `buildMarkdown()` → tambah section `## Mobile Standards` & `## Mobile Agents` setelah `## Mobile Master Prompt` (fallback `_Belum ada_`).
- Zip export → tambah `mobile-standards.md` & `mobile-agents.md` jika field terisi (selain `erd.json`).
- Tests: `test_export_markdown_format` diperluas (assert mobile sections), baru `test_export_zip_format_includes_mobile_files` (buka zip, verifikasi isi file).

**Effort:** Medium (1-2 jam)

### [x] [P7] RS-9 — FPM Production Serve ✅

**Resolution:** Terverifikasi **sudah terimplementasi** — `api/Dockerfile` berbasis `php:8.3-fpm-alpine` dengan `CMD ["php-fpm","-F"]` (expose 9000); service `api` (nginx) listen 8000 → `fastcgi_pass api-fpm:9000` (`docker/api-nginx/default.conf`). Tidak ada lagi `php artisan serve`. Docs RS-9 di 16-audit-fix-plan.md disinkronkan.

**Effort:** High (4-6 jam) → **docs sync only (~15 menit)**

---

## Prioritas Rendah

### [x] [P8] Sentry / GlitchTip Integration ✅

**Resolution:** Self-hosted GlitchTip (Sentry-compatible) di Docker Compose, reuse existing `db` + `redis` (DB `glitchtip`, Redis DB 2). SDK terinstall kedua sisi.
- **Backend:** `sentry/sentry-laravel ^4.27` via composer, `config/sentry.php` published, auto-discovery. DSN via `SENTRY_LARAVEL_DSN` env (docker-compose → api-fpm). Terverifikasi: `Sentry::captureMessage('test')` → issue muncul di GlitchTip project "backend" (PHP, id=1).
- **Frontend:** `@sentry/nextjs ^10.69` via npm. Config: `sentry.client.config.ts` (browser, NEXT_PUBLIC_SENTRY_DSN), `sentry.server.config.ts` (Node SENTRY_DSN), `sentry.edge.config.ts` (Edge). `src/instrumentation.ts` → register by NEXT_RUNTIME + `onRequestError`. `next.config.ts` wrapped with `withSentryConfig`. `error.tsx` + `global-error.tsx` updated to `Sentry.captureException`. DSN via `SENTRY_DSN` (server) + `NEXT_PUBLIC_SENTRY_DSN` (browser) env (docker-compose → web).
- **Infrastructure:** GlitchTip service always-on, internal `glitchtip:8000` (not exposed). `GLITCHTIP_SECRET_KEY` in root `.env`. 2 projects + DSNs pre-created via Django shell: backend (PHP, id=1) + frontend (JS, id=2).
- **DSN strategy:** Server-side SDK (Laravel + Next SSR) uses internal Docker DNS `glitchtip:8000`. Browser-side DSN (`NEXT_PUBLIC_SENTRY_DSN`) empty for now (no nginx route to GlitchTip yet — add `/glitchtip` nginx location + external host when client-side capture needed).
- **No-op safety:** Empty DSN → `enabled: false` → SDK silent. Dev tanpa GlitchTip tetap aman.

**Files changed:**
- `docker-compose.yml` — service `glitchtip`, env injection api-fpm + web, volume
- `api/composer.json` + `api/composer.lock` — sentry/sentry-laravel
- `api/config/sentry.php` — published config
- `api/.env.example` — SENTRY_LARAVEL_DSN + SENTRY_ENVIRONMENT placeholder
- `web/package.json` + `web/package-lock.json` — @sentry/nextjs
- `web/next.config.ts` — withSentryConfig
- `web/sentry.client.config.ts`, `web/sentry.server.config.ts`, `web/sentry.edge.config.ts`
- `web/src/instrumentation.ts`
- `web/src/app/error.tsx`, `web/src/app/global-error.tsx`
- `web/.env.example`

**Verification:**
- Backend `php artisan test` → 131 passed ✅
- Frontend lint + tsc + build → clean ✅
- E2E Playwright → 10/10 ✅
- GlitchTip issue ingest: `Sentry::captureMessage('test from laravel')` → backend project ✅

**Effort:** Medium (2-3 jam)

### [x] [P12] Host Permission Convention + GlitchTip Volume Migration ✅

**Resolution:** Password sudo `bismillah` dicatat di AGENTS.md, docs/11, docs/07 + panduan chown untuk root-owned files. GlitchTip volume migrated dari named Docker volume ke bind mount host.

- **GlitchTip volume:** Named `aiplanstudio_glitchtip-uploads` → bind mount `./docker/glitchtip/uploads/` (konsisten dengan `./docker/postgres/data_` + `./docker/redis/data`). Isi disalin via `docker run --rm` alpine `cp -a`. Named volume dihapus; top-level `volumes:` section di docker-compose.yml dihapus. `.gitignore` root tambah `docker/glitchtip/uploads`.
- **sudo/password:** `AGENTS.md` — section "Host Permission / sudo" dengan password `bismillah` + tabel root-owned paths + panduan penanganan. `docs/11` rule #10 diperluas. `docs/07` — tabel root-owned paths di section checklist.
- **chown:** `api/config/sentry.php` (tracked git, root-owned) → `sudo chown` ke user.

**Files changed:**
- `docker-compose.yml` — `glitchtip-uploads` volume → `./docker/glitchtip/uploads`
- `.gitignore` — tambah `docker/glitchtip/uploads`
- `AGENTS.md` — section "Host Permission / sudo"
- `docs/11-development-rules.md` — rule #10 diperluas
- `docs/07-docker-setup.md` — tabel root-owned paths + checklist item

**Effort:** Low (20 menit)

### [x] [P9] Activity Log — Action Types Documentation ✅

**Resolution:** Nilai `activities.action` dikurasi & didokumentasikan.
- `api/app/Models/Activity.php` → konstanta `ACTION_CREATED_VERSION`, `ACTION_DELETED_VERSION`, daftar `ACTIONS`.
- Call sites memakai konstanta (`VersionController::store/destroy`).
- `docs/03-database-schema.md` → tabel nilai action + panduan menambah aksi baru.

**Effort:** Low (30 menit)

### [x] [P10] api_contract Extraction Enhancement ✅

**Resolution:** `parseErdText()` punya fallback JSON.
- Baru `parseJsonErd()` (pakai `extractJson()` + `tryJsonDecode()` yang sudah ada) — membaca `nodes`/`edges`/`api_contract` (atau `apiContract`) dari JSON block di response AI; menangani `nodes` sebagai object-keyed map via `array_values`.
- Logika: isi bagian yang kosong — jika line-parse tidak menghasilkan nodes → ambil semua dari JSON; jika hanya `api_contract` kosong → merge dari JSON.
- Tests: `test_save_artifact_parses_erd_json_block`, `test_save_artifact_fills_missing_api_contract_from_json`, `test_save_artifact_throws_when_erd_json_has_no_nodes`.

**Effort:** Medium (2 jam)

### [x] [P11] Version Diff — Mobile Artifacts ✅

**Resolution:** `diff()` kini compare 10 field (termasuk semua mobile artifacts).
- `fields`/`labels` di `VersionController::diff()` → tambah `mobile_phases` (JSON), `mobile_standards`, `mobile_agents`.
- Tests: baru `test_diff_includes_mobile_artifacts` (2 versi → assert field mobile ada + flag `changed` benar).

**Effort:** Low (1 jam)

### [x] [P14] Sentry Browser-Side Capture via nginx /glitchtip Proxy ✅

**Resolution:** Browser SDK capture via nginx proxy to GlitchTip internal Docker service.
- **nginx route:** `location /glitchtip/` → `rewrite ^/glitchtip/(.*)$ /$1 break; proxy_pass http://glitchtip:8000` (internal network, `/glitchtip/` prefix stripped).
- **DSN injection:** `NEXT_PUBLIC_SENTRY_DSN=http://f60bf0fe8df6419e9ecb8edfc01295f1@localhost:4197/glitchtip/2` passed via `build.args` + `ARG` + `ENV` di Dockerfile builder stage. `docker-compose.yml` → `build.args: NEXT_PUBLIC_SENTRY_DSN=${SENTRY_FRONTEND_PUBLIC_DSN:-}`.
- **Client init:** `src/app/sentry-init-client.tsx` — client component with `useEffect` that reads `dsn` prop (passed from server-side `process.env.NEXT_PUBLIC_SENTRY_DSN`) and calls `Sentry.init()`. DSN embedded in HTML server render. Turbopack (Next.js 16) doesn't inline `NEXT_PUBLIC_*` to static bundles, but DSN appears in HTML so SDK can read it at runtime.
- **P14 completes P8:** DSN strategy full: server-side → `glitchtip:8000` (internal), browser-side → `localhost:4197/glitchtip` (same-origin, no CORS).

**Files changed:**
- `docker/nginx/default.conf` — `location /glitchtip/`
- `web/Dockerfile` — `ARG NEXT_PUBLIC_SENTRY_DSN` + `ENV` before build + `NEXT_TURBOPACK_CACHE=0`
- `docker-compose.yml` — `build.args` for web service, `NEXT_PUBLIC_SENTRY_DSN` env
- `.env` (root) — `SENTRY_FRONTEND_PUBLIC_DSN` set to GlitchTip frontend DSN
- `web/src/app/layout.tsx` — import `SentryInit` client component
- `web/src/app/sentry-init-client.tsx` — new client component with `useEffect` Sentry init
- `web/sentry.client.config.ts` — `enabled: true` (always init when component mounts)

**Verification:**
- `nginx -t` → ok ✅
- `curl /glitchtip/api/2/store/` → 200 ✅
- Browser errors sent via nginx proxy → appear in GlitchTip ✅
- GlitchTip project frontend (JS, id=2) receives events ✅

**Effort:** Medium (1-2 jam)

### [x] [P13] Dependency Security Audit Fix ✅

**Resolution:** Backend + frontend dependencies updated to fix known vulnerabilities.
- **Backend:** `composer update guzzlehttp/guzzle league/commonmark laravel/framework` → guzzle 7.15.3, commonmark 2.9.0, framework 13.24.0. `composer audit --no-dev` → 0 vulnerabilities.
- **Frontend:** `next` bumped 16.2.11 → 16.3.0 + `eslint-config-next` 16.3.0 → `sharp` 0.35.3 (fixes sharp advisory). `npm audit fix` → 0 vulnerabilities (all 3 mermaid DoS advisories resolved).
- **Tests:** `php artisan test` → 131 passed ✅. `npm run lint` + `tsc --noEmit` → 5 warnings, 0 errors ✅. `npm run build` → ok ✅.

**Files changed:**
- `api/composer.json` + `api/composer.lock`
- `web/package.json` + `web/package-lock.json`
- `web/Dockerfile` (sharp update included in node_modules rebuild)

**Effort:** Low (30 menit)

---

## On-Hold / Future

Item-item di bawah ini dihold sampai ada kebutuhan eksplisit:

- OAuth provider per-user
- Templates marketplace
- Kolaborasi tim
- Diff visual antar versi
- Blog/komunitas publik
- Elasticsearch integration
- Queue-based pipeline (redis worker)

---

## Cara Pakai

1. Baca [P1]–[P8] dulu — highest priority.
2. Pilih item yang cocok dengan waktu tersedia.
3. Kerjakan per item; update status di sini.
4. Sesudah selesai, update [09-roadmap.md](09-roadmap.md) dan [15-dev-log.md](15-dev-log.md).
5. Sesudah mengubah kode, update dokumentasi terkait terlebih dahulu.
