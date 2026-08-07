# 17 — Next Progress

> Baca [09-roadmap](09-roadmap.md) dulu untuk konteks status proyek.
> Setiap item harus memiliki: definition of done, estimated effort, priority.

## Status Saat Ini

Semua fase utama (F0–F10, R1, P1–P6, P11) **selesai**.
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

### [P7] RS-9 — FPM Production Serve

**Problem:** API masih pakai `php artisan serve`. Production hardening perlu FPM.

**Plan:** Migrasi ke php-fpm + nginx upstream. Sudah diplan di RS-9 (audit fix plan).

**Definition of Done:** API jalan di FPM, bukan artisan serve. docker-compose.yml updated.

**Effort:** High (4-6 jam, bisa paralel dengan item lain)

---

## Prioritas Rendah

### [P8] Sentry Integration

**Problem:** AGENTS.md bilang "When you encounter errors, use Sentry MCP" tapi Sentry belum dikonfigurasi.

**Plan:** Setup Sentry untuk Laravel (backend) dan Next.js (frontend).

**Effort:** Medium (2-3 jam)

### [P9] Activity Log — Action Types Documentation

**Problem:** `activities.action` field tidak punya daftar values yang jelas. Tidak ada enum atau dokumentasi.

**Plan:** Tambah dokumentasi action values di 03-database-schema.md atau buat enum di model.

**Effort:** Low (30 menit)

### [P10] api_contract Extraction Enhancement

**Problem:** `parseErdText()` extract api_contract hanya dari format text-line (`API: GET | /path | desc | auth`). Jika AI response dalam format berbeda, api_contract kosong.

**Plan:** Tambah fallback: jika `api_contract` kosong setelah parsing, coba extract dari JSON block di response.

**Definition of Done:** api_contract terisi dari berbagai format AI response.

**Effort:** Medium (2 jam)

### [x] [P11] Version Diff — Mobile Artifacts ✅

**Resolution:** `diff()` kini compare 10 field (termasuk semua mobile artifacts).
- `fields`/`labels` di `VersionController::diff()` → tambah `mobile_phases` (JSON), `mobile_standards`, `mobile_agents`.
- Tests: baru `test_diff_includes_mobile_artifacts` (2 versi → assert field mobile ada + flag `changed` benar).

**Effort:** Low (1 jam)

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

1. Baca [P1]–[P3] dulu — highest priority.
2. Pilih item yang cocok dengan waktu tersedia.
3. Kerjakan per item; update status di sini.
4. Sesudah selesai, update [09-roadmap.md](09-roadmap.md) dan [15-dev-log.md](15-dev-log.md).
5. Sesudah mengubah kode, update dokumentasi terkait terlebih dahulu.
