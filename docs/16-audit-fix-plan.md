# 16 — Audit Fix Plan

> Rencana perbaikan berdasarkan audit sinkronisasi docs vs code (2026-07-26).
> Prioritas: 🔴 Critical · 🟠 High · 🟡 Medium · 🟢 Low
> Baca [00-README](00-README.md) untuk konteks proyek.

---

## Ringkasan Temuan

| Kategori | 🔴 Critical | 🟠 High | 🟡 Medium | 🟢 Low | Total |
|----------|------------|---------|-----------|-------|-------|
| Docs vs Code | 4 | 10 | 8 | 9 | 31 |
| Code Quality | 3 | 6 | 7 | 7 | 23 |
| Test Coverage | — | 5 | 4 | 5 | 14 |
| Security | 1 | 1 | 2 | 1 | 5 |
| **Total** | **8** | **22** | **21** | **22** | **73** |
| **Selesai** | **8** | **22** | **21** | **22** | **73** |
| **Sisa** | **0** | **0** | **0** | **0** | **0** |

> **Semua item RS selesai (0 sisa).** RS-9 (FPM production serve) dikonfirmasi sudah terimplementasi di code (php-fpm + nginx api upstream) — tinggal sinkronisasi docs. RS-7 (middleware Next.js) adalah **false-positive** — auth guard sudah ditangani Laravel; middleware frontend tidak diperlukan.

---

## Fase Perbaikan

### [x] RA — Remediation Auth (Docs Sync) ✅

> **Goal:** Sinkronisasi total dokumentasi auth dengan implementasi yang benar (Sanctum SPA Session, bukan Bearer Token).

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RA-1 | Update `04-api-contract.md` — auth model | 🔴 Critical | `docs/04-api-contract.md` | Ganti semua referensi Bearer Token → Sanctum SPA Session. Register return `{user}`, Login return `{user}`, logout via session. Tambah throttle middleware, CSRF flow, `/sanctum/csrf-cookie` endpoint. | ✅ |
| RA-2 | Update `02-architecture.md` — auth diagram | 🔴 Critical | `docs/02-architecture.md` | Ganti diagram auth: session cookie + CSRF, bukan Bearer Token. Hapus token expiry 120 menit. | ✅ |
| RA-3 | Update `05-wizard-flow.md` — auth flow | 🔴 Critical | `docs/05-wizard-flow.md` | Ganti auth flow: session cookie-based, bukan token+sessionStorage. | ✅ |
| RA-4 | Update `08-frontend.md` — auth + middleware | 🔴 Critical | `docs/08-frontend.md` | Ganti total: hapus getToken/setToken/clearToken, ganti dengan `fetchCsrfCookie()`, `withCredentials: true`. Update middleware docs. Update SSE auth method (cookies, bukan Bearer). | ✅ |
| RA-5 | Update `09-roadmap.md` — auth status | 🟠 High | `docs/09-roadmap.md` | "Bearer Token" → "Session-based (Sanctum SPA auth)". Update semua referensi auth di fase F3. | ✅ |
| RA-6 | Update `10-decision-log.md` — D-012/D-013 | 🟠 High | `docs/10-decision-log.md` | D-012 (Migrasi Auth: Session → Bearer) dan D-013 (sessionStorage) tidak tercermin di kode. Tambah entri "D-016 · Kembali ke SPA Session Auth". | ✅ |
| RA-7 | Update `12-security-checklist.md` — CSRF | 🟠 High | `docs/12-security-checklist.md` | Sesuaikan checklist CSRF dengan implementasi Sanctum SPA (CSRF aktif, bukan "tidak ada CSRF"). | ✅ |
| RA-8 | Hapus/arsip referensi Bearer token di BFF routes docs | 🟡 Medium | `docs/08-frontend.md`, `docs/04-api-contract.md` | Pastikan tidak ada sisa referensi `Authorization: Bearer` atau token forwarding di dokumentasi BFF. | ✅ |

---

### [x] RD — Remediation Database Schema (Docs Sync) ✅

> **Goal:** Update `docs/03-database-schema.md` mencerminkan skema aktual.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RD-1 | Tambah kolom `ai_providers` yang hilang | 🟠 High | `docs/03-database-schema.md` | Tambah: `name`, `provider_type`, `is_active`, `last_test_response`, `last_test_at` | ✅ |
| RD-2 | Tambah default values yang missing | 🟢 Low | `docs/03-database-schema.md` | `projects.target` default 'web', `ai_providers.model` default 'gpt-4o', `templates.description` nullable | ✅ |
| RD-3 | Tambah unique constraint `phase_progress` | 🟢 Low | `docs/03-database-schema.md` | UNIQUE(version_id, phase_key) | ✅ |
| RD-4 | Tambah kolom `users` yang hilang | 🟢 Low | `docs/03-database-schema.md` | `email_verified_at`, `remember_token` | ✅ |
| RD-5 | Update seeder description | 🟡 Medium | `docs/03-database-schema.md` | Seeder buat provider dengan base_url `https://api.openai.com/v1`, model `gpt-4o`, api_key `''` | ✅ |
| RD-6 | Update migration history | 🟢 Low | `docs/03-database-schema.md` | Tambah catatan migration `2026_07_25_100000_add_provider_type_to_ai_providers_table` | ✅ |

---

### [x] RP — Remediation AI Pipeline (Code + Docs) [7/7] ✅

> **Goal:** Fix bugs di pipeline context prompts + update docs.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RP-1 | Tambah `target` dan `stack` ke stage `analisa` context | 🟠 High | `api/app/Services/PipelineRunner.php` | Ubah `"Ide: {$idea}"` → `"Ide: {$idea}\nTarget: {$target}\nStack: {$stack}"` | ✅ |
| RP-2 | Tambah `target` ke stage `architecture` context | 🟠 High | `api/app/Services/PipelineRunner.php` | Ubah `"PRD: {$v->prd}"` → `"PRD: {$v->prd}\nTarget: {$target}"` | ✅ |
| RP-3 | Populate `api_contract` column | 🟠 High | `api/app/Services/PipelineRunner.php` | Tambah mapping `'api_contract' => 'api_contract'` di `saveArtifact()` stage `erd`. Update parsing ERD untuk extract API contracts dari AI response. | ✅ (extraction via parseErdText regex) |
| RP-4 | Implement retry mechanism JSON validation | 🟡 Medium | `api/app/Services/PipelineRunner.php` | Rubah `throw RuntimeException` menjadi retry sekali dengan instruksi perbaikan format, baru throw error jika gagal lagi | ✅ |
| RP-5 | Update docs — master prompt wajib JSON | 🟡 Medium | `docs/06-ai-pipeline.md` | Tambah catatan bahwa stage `master` juga divalidasi sebagai JSON (sama dengan `erd` dan `phases`) | ✅ |
| RP-6 | Update docs — Anthropic support | 🟢 Low | `docs/06-ai-pipeline.md` | Tambah Anthropic (Claude) sebagai provider yang didukung, dengan endpoint `/messages` | ✅ |
| RP-7 | Update docs — stage context prompts | 🟡 Medium | `docs/06-ai-pipeline.md` | Sinkronkan tabel input context per stage dengan implementasi aktual di PipelineRunner | ✅ |

---

### [x] RW — Remediation Wizard Frontend (Code + Docs) [7/7] ✅

> **Goal:** Sinkronkan wizard dengan dokumentasi yang sudah update.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RW-1 | Ubah default wizard mode ke checkpoint | 🟠 High | `web/src/app/(app)/new/page.tsx` | `useState(true)` → `useState(false)` untuk `auto`. Sesuai dokumen yang menyebut "default mode checkpoint". | ✅ |
| RW-2 | Tambah Stack input field | 🟠 High | `web/src/app/(app)/new/page.tsx` | Tambah input field "Stack (opsional)" di form wizard antara idea dan target. Kirim sebagai `stack` di POST `/api/projects`. | ✅ |
| RW-3 | Implement template selection | 🟠 High | `web/src/app/(app)/new/page.tsx` | Tambah dropdown/picker template sebelum form. Load daftar template via `GET /api/templates`. Pilih template → pre-fill idea/target/stack. | ✅ |
| RW-4 | Implement inline editing artifacts | 🟡 Medium | `web/src/app/(app)/new/page.tsx` | Tambah mode edit pada artifact panel (toggle view/edit). Simpan perubahan ke backend via PATCH endpoint. | ✅ |
| RW-5 | Tambah onClick handler copy buttons | 🟢 Low | `web/src/app/(app)/new/page.tsx` | Implementasi `handleCopy()` untuk tombol "Salin" dan "Salin Master Prompt". | ✅ |
| RW-6 | Implementasi Stack di backend | 🟡 Medium | `api/app/Http/Controllers/ProjectController.php` | Pastikan `stack` di-handle dengan benar di store, tampilkan di response, dan diteruskan ke pipeline. | ✅ |
| RW-7 | Update `05-wizard-flow.md` | 🟠 High | `docs/05-wizard-flow.md` | Sinkronkan dengan implementasi aktual — 7 stages, keys: pertanyaan→analisa→prd→architecture→erd→phased_master→phased_master_mobile | ✅ |

---

### [x] RX — Remediation Export & Versioning ✅

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RX-1 | Fix temp file leak di ZIP export | 🟡 Medium | `api/app/Http/Controllers/VersionController.php` | Ganti `tempnam` + `register_shutdown_function` dengan `StreamedResponse` | ✅ |
| RX-2 | Strict validation format export | 🟢 Low | `api/app/Http/Controllers/VersionController.php` | Gunakan `$request->validate(['format' => 'in:md,zip'])` | ✅ |
| RX-3 | Tambah test VersionController | 🟠 High | `api/tests/Feature/VersionTest.php` | Test: store, show, togglePhase, export (md, zip, invalid) | ✅ (9 tests) |
| RX-4 | Tambah PhaseProgress model test | 🟡 Medium | `api/tests/Feature/` | Test: create phase_progress, unique constraint, toggle done | ✅ (PhaseProgressFactory created) |

---

### [x] RS — Remediation Security & Infrastructure [10/10]

> **Goal:** Fix security issues dan hardening.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RS-1 | Pindah hardcoded credentials ke env | 🔴 Critical | `docker-compose.yml` | `POSTGRES_PASSWORD` dan Redis password → `${POSTGRES_PASSWORD:-default}` dengan `.env` file. | ✅ |
| RS-2 | Fix race condition PipelineRunner | 🔴 High | `api/app/Services/PipelineRunner.php` | Implementasi database locking: `DB::transaction()` + `Version::lockForUpdate()` sebelum update stage_status. | ✅ |
| RS-3 | SSRF mitigation di AiClient | 🟡 Medium | `api/app/Services/AiClient.php` | Tambah `validateBaseUrl()` — validasi URL tidak指向 internal IP, kecuali nama container Docker yang diizinkan. | ✅ |
| RS-4 | Tambah SESSION_SECURE_COOKIE default | 🟡 Medium | `api/.env.example` | Set `SESSION_SECURE_COOKIE=false` di `.env.example`. Tambah catatan untuk production. | ✅ |
| RS-5 | Error message exposure | 🟡 Medium | `web/src/lib/api.ts` | Batasi error message yang dikirim ke user. Parse JSON response dulu, fallback ke generic message. | ✅ |
| RS-6 | Tambah password confirmation di register | 🟡 Medium | `api/app/Http/Controllers/AuthController.php` | Tambah `'password' => 'confirmed'` rule. | ✅ |
| RS-7 | Implementasi middleware Next.js | 🟡 Medium | `web/src/proxy.ts` | **Resolved via `web/src/proxy.ts`** (Next.js 16 rename middleware→proxy). Guard route aktif: redirect unauthenticated ke `/login` untuk protected paths. JANGAN buat `middleware.ts` (konflik build). | ✅ |
| RS-8 | Clipboard error handling | 🟢 Low | `web/src/app/(app)/projects/[id]/page.tsx` | Tambah `.catch()` untuk `navigator.clipboard.writeText()` + fallback `execCommand`. | ✅ |
| RS-9 | Ganti `artisan serve` untuk production | 🟡 Medium | `docker-compose.yml`, `api/Dockerfile` | **Selesai:** `api-fpm` build `php:8.3-fpm-alpine` dengan `CMD ["php-fpm","-F"]` (expose 9000); service `api` = nginx listen 8000 → `fastcgi_pass api-fpm:9000` (`docker/api-nginx/default.conf`). Update `02-architecture.md`. | ✅ |
| RS-10 | Cleanup test API key | 🟢 Low | `api/tests/Feature/AiClientTest.php`, `PipelineRunnerTest.php` | Ganti `sk-test-key-for-mocking` → `sk-test-invalid` | ✅ |

---

### [x] RC — Remediation Component Structure (Docs Sync) ✅

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RC-1 | Update component directory listing | 🔴 Critical | `docs/08-frontend.md` | Ganti daftar komponen yang tidak ada dengan daftar komponen yang benar-benar ada. | ✅ |
| RC-2 | Update testing section | 🟠 High | `docs/08-frontend.md` | Update referensi testing. | ✅ |
| RC-3 | Update `react-markdown` usage | 🟡 Medium | `docs/08-frontend.md` | Update catatan bahwa `react-markdown` terdaftar tapi belum dipakai. | ✅ |

---

### [x] RT — Remediation Test Coverage [9/9] ✅

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RT-1 | Add HTTP mocking (Laravel Http::fake) | 🟠 High | `api/tests/Feature/AiClientTest.php`, `PipelineRunnerTest.php` | Gunakan `Http::fake()` untuk test actual API communication paths | ✅ |
| RT-2 | Add VersionController tests | 🟠 High | `api/tests/Feature/VersionTest.php` | Test semua endpoints | ✅ (9 tests) |
| RT-3 | Add GenerateStreamController tests | 🟠 High | `api/tests/Feature/GenerateStreamTest.php` | Test validation, SSE format, PipelineRunner integration | ✅ (8 tests) |
| RT-4 | Add ProviderSettingsController tests | 🟠 High | `api/tests/Feature/SettingsTest.php` | Test: store, update, destroy, setActive, test, testPrompt | ✅ |
| RT-5 | Add UserSettingsController tests — store/destroy | 🟡 Medium | `api/tests/Feature/SettingsTest.php` | Test: admin create user, admin delete non-admin, admin cannot delete admin | ✅ |
| RT-6 | Add missing factories | 🟡 Medium | `api/database/factories/` | Buat `VersionFactory`, `PhaseProgressFactory` | ✅ |
| RT-7 | Setup frontend testing infrastructure | 🟠 High | `web/` | Tambah Playwright config + smoke test | ✅ |
| RT-8 | Add unit tests untuk model methods | 🟢 Low | `api/tests/Unit/ModelTest.php` | `isAdmin()`, `isActive()`, `isPending()`, dll. | ✅ (10 tests) |
| RT-9 | Tambah test validasi error | 🟢 Low | `api/tests/Feature/` | Test: missing required fields, invalid target, duplicate email, invalid stage key | ✅ |

---

### [x] RL — Remediation Low Priority [5/5]

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RL-1 | Tambah pagination template listing | 🟢 Low | `api/app/Http/Controllers/TemplateController.php` | `Template::all()` → `Template::paginate(50)` | ✅ |
| RL-2 | Close file handle PipelineRunner | 🟢 Low | `api/app/Services/PipelineRunner.php` | Tambah `fclose($this->stdout)` di `__destruct()` | ✅ |
| RL-3 | Update `docs/04-api-contract.md` — tambah throttle + extra routes | 🟢 Low | `docs/04-api-contract.md` | Tambah: throttle middleware, `/api/health`, `/sanctum/csrf-cookie` | ✅ |
| RL-4 | Update `docs/02-architecture.md` — docker network name | 🟢 Low | `docs/02-architecture.md` | `aistack_net` → `aistack` | ✅ |
| RL-5 | Export format validation di BFF route | 🟢 Low | `web/src/app/api/versions/[id]/export/route.ts` | Tambah validasi format parameter sebelum proxy ke Laravel | ✅ |

---

## Sinkronisasi Tambahan (2026-08-06)

Setelah audit plan awal, ditemukan gap tambahan yang sudah difiks:

| # | Item | Severity | File | Action |
|---|------|----------|------|--------|
| D-01 | 7 stages pipeline vs "6 tahap" docs | 🔴 Critical | 05-wizard-flow.md, 06-ai-pipeline.md | Rewrite: pertanyaan + phased_master keys, context prompts |
| D-02 | mobile artifact columns salah di schema docs | 🔴 Critical | 03-database-schema.md | Hapus mobile_analysis/prd/architecture; tambah mobile_phases/master_prompt/standards/agents |
| D-03 | 04-api-contract: SSE stages salah, missing endpoints | 🟠 High | 04-api-contract.md | Fix SSE stage list; tambah activities, tokens, webhook, profile, standards/agents |
| D-04 | 02-architecture: port 80 vs 4197, pipeline outdated | 🟡 Medium | 02-architecture.md | Sinkron port, tambah 7-stage pipeline description, activity log, favorites |
| D-05 | 10-decision-log: D-021/D-022 duplikat | 🟡 Medium | 10-decision-log.md | Fix duplikat; activities.action field fix |
| D-06 | 09-roadmap: outdated status, test counts | 🟡 Medium | 09-roadmap.md | Update status, Phase 4 done, RS-7 false-positive note |
| D-07 | 00-README: AUTH.md/AGENTS.md tidak di-list | 🟢 Low | 00-README.md | Tambah ke daftar dokumen |
| D-08 | 13-backend-testing: checklist outdated | 🟡 Medium | 13-backend-testing.md | Tambah feature tests untuk fitur baru (activity log, favorites, diff, tokens, etc) |
| D-09 | 14-frontend-testing: spec count salah | 🟡 Medium | 14-frontend-testing.md | Update spec file count |
| D-10 | RS-7 false positive dalam summary | 🟢 Low | 16-audit-fix-plan.md | Tambah catatan RS-7 adalah N/A |

---

## Estimasi

| Fase | Item | Estimasi |
|------|------|---------|
| RA | 8 items | 2-3 jam |
| RD | 6 items | 30 menit |
| RP | 7 items | 2-3 jam |
| RW | 7 items | 4-6 jam |
| RX | 4 items | 2-3 jam |
| RS | 10 items | 3-4 jam |
| RC | 3 items | 30 menit |
| RT | 9 items | 4-6 jam |
| RL | 5 items | 1 jam |
| **Total** | **59 items** | **~20-27 jam** |

> **Actual:** Semua 59 items selesai. RS-9 (FPM production) terkonfirmasi sudah terimplementasi (2026-08-06).

---

## [x] 13-Stage Refactor Sync ✅

> **Goal:** Sinkronisasi docs 00-18 + web/AGENTS.md setelah pipeline refactor 13-stage (D-028). Semua referensi `phased_master`, `phased_master_mobile`, `7 stage`, `target mobile` diperbarui.

| # | Item | File Terkait | Status |
|---|------|-------------|--------|
| D-11a | 00-README: 7→13 stages | docs/00-README.md | ✅ |
| D-11b | 01-overview: Web/Both, 13 tahap | docs/01-overview.md | ✅ |
| D-11c | 02-architecture: pipeline 13, col mapping, gate | docs/02-architecture.md | ✅ |
| D-11d | 03-database-schema: enum web\|both, 13 stage keys, template seed | docs/03-database-schema.md | ✅ |
| D-11e | 04-api-contract: stage list, gate desc, diff fields | docs/04-api-contract.md | ✅ |
| D-11f | 05-wizard-flow: 13 tahap, target, flow, gate | docs/05-wizard-flow.md | ✅ |
| D-11g | 06-ai-pipeline: ALL_STAGES 13, stage table, parse | docs/06-ai-pipeline.md | ✅ |
| D-11h | 08-frontend: 13 tahap, artifact tabs | docs/08-frontend.md | ✅ |
| D-11i | 09-roadmap: 13 stages, F5 label | docs/09-roadmap.md | ✅ |
| D-11j | 10-decision-log: D-028 entry + update stage refs | docs/10-decision-log.md | ✅ |
| D-11k | 11-development-rules: Web/Both | docs/11-development-rules.md | ✅ |
| D-11l | 13-backend-testing: 13 keys, parsePhasesText, stage list | docs/13-backend-testing.md | ✅ |
| D-11m | 14-frontend-testing: 13-stage | docs/14-frontend-testing.md | ✅ |
| D-11n | 15-dev-log: new entry | docs/15-dev-log.md | ✅ |
| D-11o | 17-next-progress: 13-stage refs + P15 entry | docs/17-next-progress.md | ✅ |
| D-11p | 18-production-readiness: pipeline diagram, target, gate, auto-run | docs/18-production-readiness.md | ✅ |
| D-11q | web/AGENTS.md: 13-stage pipeline reference | web/AGENTS.md | ✅ |

> File 15 (dev-log) dan 16 (audit-fix-plan) entri lama dibiarkan sebagai rekam historis.

---

## Cara Pakai

1. Saat memulai sesi, baca [09-roadmap.md](09-roadmap.md) untuk status fase.
2. Baca [17-next-progress.md](17-next-progress.md) untuk next steps terprioritas.
3. Item masih `[ ]` → dikerjakan → ubah `[~]` → selesai → `[x]`.
4. Catat progres di [15-dev-log.md](15-dev-log.md).
5. **Wajib:** Sesudah mengubah kode, update dokumentasi terkait **terlebih dahulu** sebelum commit.
6. Update [09-roadmap.md](09-roadmap.md) setelah satu fase penuh selesai.
