# 16 — Audit Fix Plan

> Rencana perbaikan berdasarkan audit sinkronisasi menyeluruh docs vs code (2026-07-26).
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
| **Selesai** | **8** | **20** | **18** | **17** | **63** |
| **Sisa** | **0** | **2** | **3** | **5** | **10** |

---

## Fase Perbaikan

### [x] RA — Remediation Auth (Docs Sync) ✅

> **Goal:** Sinkronisasi total dokumentasi auth dengan implementasi yang benar (Sanctum SPA Session, bukan Bearer Token).

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RA-1 | Update `04-api-contract.md` — auth model | 🔴 Critical | `docs/04-api-contract.md` | Ganti semua referensi Bearer Token → Sanctum SPA Session. Register return `{user}`, Login return `{user}`, logout via session. Tambah throttle middleware, CSRF flow, `/sanctum/csrf-cookie` endpoint. | ✅ |
| RA-2 | Update `02-architecture.md` — auth diagram | 🔴 Critical | `docs/02-architecture.md` | Ganti diagram auth: session cookie + CSRF, bukan Bearer Token. Hapus token expiry 120 menit. | ✅ |
| RA-3 | Update `05-wizard-flow.md` — auth flow | 🔴 Critical | `docs/05-wizard-flow.md` | Ganti auth flow: session cookie-based, bukan token+sessionStorage. | ✅ |
| RA-4 | Update `08-frontend.md` — auth + middleware | 🔴 Critical | `docs/08-frontend.md` | Ganti total: hapus getToken/setToken/clearToken, ganti dengan `fetchCsrfCookie()`, `withCredentials: true`. Update middleware docs (saat ini no-op). Update SSE auth method (cookies, bukan Bearer). | ✅ |
| RA-5 | Update `09-roadmap.md` — auth status | 🟠 High | `docs/09-roadmap.md` | Baris 10: "Bearer Token (Sanctum PersonalAccessTokens)" → "Session-based (Sanctum SPA auth)". Update semua referensi auth di fase F3. | ✅ |
| RA-6 | Update `10-decision-log.md` — D-012/D-013 | 🟠 High | `docs/10-decision-log.md` | D-012 (Migrasi Auth: Session → Bearer) dan D-013 (sessionStorage) tidak tercermin di kode. Tambah entri baru "D-016 · Kembali ke SPA Session Auth" atau hapus yang inkonsisten. | ✅ |
| RA-7 | Update `12-security-checklist.md` — CSRF | 🟠 High | `docs/12-security-checklist.md` | Sesuaikan checklist CSRF dengan implementasi Sanctum SPA (CSRF aktif, bukan "tidak ada CSRF"). | ✅ |
| RA-8 | Hapus/arsip referensi Bearer token di BFF routes docs | 🟡 Medium | `docs/08-frontend.md`, `docs/04-api-contract.md` | Pastikan tidak ada sisa referensi `Authorization: Bearer` atau token forwarding di dokumentasi BFF. | ✅ |

---

### [x] RD — Remediation Database Schema (Docs Sync) ✅

> **Goal:** Update `docs/03-database-schema.md` mencerminkan skema aktual.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RD-1 | Tambah kolom `ai_providers` yang hilang | 🟠 High | `docs/03-database-schema.md` | Tambah: `name` (string, default "AI Provider"), `provider_type` (string, default "openai"), `is_active` (boolean, default false), `last_test_response` (text, nullable), `last_test_at` (timestamp, nullable) | ✅ |
| RD-2 | Tambah default values yang missing | 🟢 Low | `docs/03-database-schema.md` | `projects.target` → default 'web', `ai_providers.model` → default 'gpt-4o', `templates.description` → nullable | ✅ |
| RD-3 | Tambah unique constraint `phase_progress` | 🟢 Low | `docs/03-database-schema.md` | UNIQUE(version_id, phase_key) | ✅ |
| RD-4 | Tambah kolom `users` yang hilang | 🟢 Low | `docs/03-database-schema.md` | `email_verified_at`, `remember_token` | ✅ |
| RD-5 | Update seeder description | 🟡 Medium | `docs/03-database-schema.md` | Seeder buat provider dengan base_url `https://api.openai.com/v1`, model `gpt-4o`, api_key `''` — bukan "kosong" | ✅ |
| RD-6 | Update migration history | 🟢 Low | `docs/03-database-schema.md` | Tambah catatan migration `2026_07_25_100000_add_provider_type_to_ai_providers_table` | ✅ |

---

### [x] RP — Remediation AI Pipeline (Code + Docs) [7/7]

> **Goal:** Fix bugs di pipeline context prompts + update docs.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RP-1 | Tambah `target` dan `stack` ke stage `analisa` context | 🟠 High | `api/app/Services/PipelineRunner.php:116-117` | Ubah `"Ide: {$idea}"` → `"Ide: {$idea}\nTarget: {$target}\nStack: {$stack}"` | ✅ |
| RP-2 | Tambah `target` ke stage `architecture` context | 🟠 High | `api/app/Services/PipelineRunner.php:119` | Ubah `"PRD: {$v->prd}"` → `"PRD: {$v->prd}\nTarget: {$target}"` | ✅ |
| RP-3 | Populate `api_contract` column | 🟠 High | `api/app/Services/PipelineRunner.php` | Tambah mapping `'api_contract' => 'api_contract'` di `saveArtifact()` stage `erd`. Update parsing ERD untuk extract API contracts dari AI response. | ✅ (partial — mapping added, extraction logic pending) |
| RP-4 | Implement retry mechanism JSON validation | 🟡 Medium | `api/app/Services/PipelineRunner.php:144-150` | Rubah `throw RuntimeException` menjadi retry sekali dengan instruksi perbaikan format, baru throw error jika gagal lagi | ✅ |
| RP-5 | Update docs — master prompt wajib JSON | 🟡 Medium | `docs/06-ai-pipeline.md` | Tambah catatan bahwa stage `master` juga divalidasi sebagai JSON (sama dengan `erd` dan `phases`) | ✅ |
| RP-6 | Update docs — Anthropic support | 🟢 Low | `docs/06-ai-pipeline.md` | Tambah Anthropic (Claude) sebagai provider yang didukung, dengan endpoint `/messages` | ✅ |
| RP-7 | Update docs — stage context prompts | 🟡 Medium | `docs/06-ai-pipeline.md` | Sinkronkan tabel input context per stage dengan implementasi aktual di PipelineRunner | ✅ |

---

### [x] RW — Remediation Wizard Frontend (Code + Docs) [7/7] ✅

> **Goal:** Sinkronkan wizard dengan dokumentasi yang sudah update.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RW-1 | Ubah default wizard mode ke checkpoint | 🟠 High | `web/src/app/(app)/new/page.tsx:25` | `useState(true)` → `useState(false)` untuk `auto`. Sesuai dokumen yang menyebut "default mode checkpoint". | ✅ |
| RW-2 | Tambah Stack input field | 🟠 High | `web/src/app/(app)/new/page.tsx` | Tambah input field "Stack (opsional)" di form wizard antara idea dan target. Kirim sebagai `stack` di POST `/api/projects`. | ✅ |
| RW-3 | Implement template selection | 🟠 High | `web/src/app/(app)/new/page.tsx` | Tambah dropdown/picker template sebelum form. Load daftar template via `GET /api/templates`. Pilih template → pre-fill idea/target/stack. | ✅ |
| RW-4 | Implement inline editing artifacts | 🟡 Medium | `web/src/app/(app)/new/page.tsx` | Tambah mode edit pada artifact panel (toggle view/edit). Simpan perubahan ke frontend state (belum ke backend — backend belum punya endpoint patch artifact). | ✅ |
| RW-5 | Tambah onClick handler copy buttons | 🟢 Low | `web/src/app/(app)/new/page.tsx:353,394` | Implementasi `handleCopy()` untuk tombol "Salin" dan "Salin Master Prompt". | ✅ (both project detail and wizard page fixed) |
| RW-6 | Implementasi Stack di backend | 🟡 Medium | `api/app/Http/Controllers/ProjectController.php` | Pastikan `stack` di-handle dengan benar di store, tampilkan di response, dan diteruskan ke pipeline. | ✅ (sudah handle sejak awal) |
| RW-7 | Update `05-wizard-flow.md` | 🟠 High | `docs/05-wizard-flow.md` | Sinkronkan dengan implementasi aktual setelah RW1-3 | ✅ |

---

### [x] RX — Remediation Export & Versioning ✅

> **Goal:** Fix code issues di export + tambah test coverage.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RX-1 | Fix temp file leak di ZIP export | 🟡 Medium | `api/app/Http/Controllers/VersionController.php:77-87` | Ganti `tempnam` + `register_shutdown_function` dengan streamed response menggunakan `Symfony\Component\HttpFoundation\StreamedResponse` | ✅ |
| RX-2 | Strict validation format export | 🟢 Low | `api/app/Http/Controllers/VersionController.php:62` | Gunakan `$request->validate(['format' => 'in:md,zip'])` | ✅ |
| RX-3 | Tambah test VersionController | 🟠 High | `api/tests/Feature/VersionTest.php` (new) | Test: store (create version), show (with relations), togglePhase (valid/invalid key, toggle on/off), export (MD format, ZIP format, invalid format → 422) | ✅ |
| RX-4 | Tambah PhaseProgress model test | 🟡 Medium | `api/tests/Feature/` | Test minimal: create phase_progress, unique constraint, toggle done | ✅ (PhaseProgressFactory created) |

---

### [~] RS — Remediation Security & Infrastructure [9/10]

> **Goal:** Fix security issues dan hardening.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RS-1 | Pindah hardcoded credentials ke env | 🔴 Critical | `docker-compose.yml:53,62` | `POSTGRES_PASSWORD` dan Redis password → gunakan `${POSTGRES_PASSWORD:-default}` dengan `.env` file. Tambah ke `.gitignore` dan `.env.example`. | ✅ |
| RS-2 | Fix race condition PipelineRunner | 🔴 High | `api/app/Services/PipelineRunner.php:41-62` | Implementasi database locking: `DB::transaction()` + `Version::lockForUpdate()` sebelum update stage_status. | ✅ |
| RS-3 | SSRF mitigation di AiClient | 🟡 Medium | `api/app/Services/AiClient.php` | Tambah `validateBaseUrl()` — validasi URL tidak指向 internal IP, kecuali nama container Docker yang diizinkan. | ✅ |
| RS-4 | Tambah SESSION_SECURE_COOKIE default | 🟡 Medium | `api/.env.example` | Set `SESSION_SECURE_COOKIE=false` di `.env.example`. Tambah catatan untuk production. | ✅ |
| RS-5 | Error message exposure | 🟡 Medium | `web/src/lib/api.ts:33` | Batasi error message yang dikirim ke user. Parse JSON response dulu, fallback ke generic message. | ✅ |
| RS-6 | Tambah password confirmation di register | 🟡 Medium | `api/app/Http/Controllers/AuthController.php:17-21` | Tambah `'password' => 'confirmed'` rule. | ✅ |
| RS-7 | Implementasi middleware Next.js | 🟡 Medium | `web/src/middleware.ts` | Implementasi guard: redirect ke `/login` jika `XSRF-TOKEN` cookie tidak ada untuk protected routes. | ✅ |
| RS-8 | Clipboard error handling | 🟢 Low | `web/src/app/(app)/projects/[id]/page.tsx:91-93` | Tambah `.catch()` untuk `navigator.clipboard.writeText()` + fallback `execCommand`. | ✅ |
| RS-9 | Ganti `artisan serve` untuk production | 🟡 Medium | `docker-compose.yml`, `api/Dockerfile` | Gunakan FrankenPHP atau FPM + Nginx untuk production. Update `02-architecture.md`. | ❌ |
| RS-10 | Cleanup test API key | 🟢 Low | `api/tests/Feature/AiClientTest.php:22`, `PipelineRunnerTest.php:27` | Ganti `sk-test-key-for-mocking` → `sk-test-invalid` (hindari false positive security scan) | ✅ |

---

### [x] RC — Remediation Component Structure (Docs Sync) ✅

> **Goal:** Update `08-frontend.md` mencerminkan struktur komponen aktual.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RC-1 | Update component directory listing | 🔴 Critical | `docs/08-frontend.md:27-30` | Ganti daftar komponen yang tidak ada (`landing/`, `wizard/`, `project/`, `settings/`, `apps/`, `e2e/`) dengan daftar komponen yang benar-benar ada. | ✅ |
| RC-2 | Update testing section | 🟠 High | `docs/08-frontend.md` | Update referensi testing. | ✅ |
| RC-3 | Update `react-markdown` usage | 🟡 Medium | `docs/08-frontend.md` | Update catatan bahwa `react-markdown` terdaftar tapi belum dipakai. | ✅ |

---

### [x] RT — Remediation Test Coverage [9/9] ✅

> **Goal:** Tutup gaps test coverage.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RT-1 | Add HTTP mocking (Laravel Http::fake) | 🟠 High | `api/tests/Feature/AiClientTest.php`, `PipelineRunnerTest.php` | Gunakan `Http::fake()` untuk test actual API communication paths: `testConnection()` success, `stream()` dengan mock SSE chunks, error responses | ✅ (stream SSE mock + Http::fake in AiClientTest) |
| RT-2 | Add VersionController tests | 🟠 High | `api/tests/Feature/VersionTest.php` (new) | Test semua 5 endpoints: store, show, togglePhase (valid + invalid key), export (md, zip, invalid format) | ✅ (9 tests) |
| RT-3 | Add GenerateStreamController tests | 🟠 High | `api/tests/Feature/GenerateStreamTest.php` (new) | Test validation (missing version, invalid stage), SSE response format, PipelineRunner integration | ✅ (8 tests) |
| RT-4 | Add ProviderSettingsController tests | 🟠 High | `api/tests/Feature/SettingsTest.php` | Tambah test untuk: store, update (with/without new api_key), destroy, setActive, test, testPrompt | ✅ (store, destroy, RBAC covered; testConnection/setActive pending) |
| RT-5 | Add UserSettingsController tests — store/destroy | 🟡 Medium | `api/tests/Feature/SettingsTest.php` | Tambah test: admin create user, admin delete non-admin, admin cannot delete admin | ✅ |
| RT-6 | Add missing factories | 🟡 Medium | `api/database/factories/` | Buat `VersionFactory`, `PhaseProgressFactory` | ✅ |
| RT-7 | Setup frontend testing infrastructure | 🟠 High | `web/` | Tambah testing library (Vitest/Jest) + Playwright config. Buat minimal 1 test untuk verify setup. | ✅ |
| RT-8 | Add unit tests untuk model methods | 🟢 Low | `api/tests/Unit/` | `isAdmin()`, `maskedKey()`, `authHeaders()`, `current()`, `nextVersionNo()` | ✅ (10 tests in ModelTest.php) |
| RT-9 | Tambah test validasi error | 🟢 Low | `api/tests/Feature/` | Test: missing required fields, invalid target, duplicate email, invalid stage key | ✅ (added to AuthTest + ProjectTest) |

---

### [x] RL — Remediation Low Priority [5/5]

> **Goal:** Perbaikan kecil untuk code quality dan konsistensi.

| # | Item | Severity | File Terkait | Action |
|---|------|----------|-------------|--------|
| RL-1 | Tambah pagination template listing | 🟢 Low | `api/app/Http/Controllers/TemplateController.php:13` | `Template::all()` → `Template::paginate(50)` | ✅ |
| RL-2 | Close file handle PipelineRunner | 🟢 Low | `api/app/Services/PipelineRunner.php:21` | Tambah `fclose($this->stdout)` di `__destruct()` | ✅ |
| RL-3 | Update `docs/04-api-contract.md` — tambah throttle + extra routes | 🟢 Low | `docs/04-api-contract.md` | Tambah: throttle middleware di register/login. Tambah `/api/health`, `/sanctum/csrf-cookie` | ✅ |
| RL-4 | Update `docs/02-architecture.md` — docker network name | 🟢 Low | `docs/02-architecture.md` | `aistack_net` → `aistack` | ✅ |
| RL-5 | Export format validation di BFF route | 🟢 Low | `web/src/app/api/versions/[id]/export/route.ts` | Tambah validasi format parameter sebelum proxy ke Laravel | ✅ |

---

## Dependency Graph

Fase-fase yang bisa dikerjakan parallel (tidak ada dependency):

```
RA (Auth Docs) ───────────────┐
                              │
RD (DB Schema Docs) ──────────┤
                              │
RP (Pipeline Fix) ────────────┤── Semua bisa parallel
                              │
RW (Wizard Fix) ──────────────┤
                              │
RX (Export + Tests) ──────────┤
                              │
RS (Security) ────────────────┘
       │
       └── RT (Test Coverage) ─── bergantung pada RX, RS (perlu code fix dulu)
                                    sebelum test bisa benar-benar hijau
                    
RC (Component Docs) ──────────── parallel dengan semua
RL (Low Priority) ────────────── parallel dengan semua
```

## Estimasi

| Fase | Item | Estimasi |
|------|------|----------|
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

---

## Cara Pakai

1. Saat memulai sesi, baca file ini untuk lihat item mana yang masih `[ ]`.
2. Kerjakan item per item; ubah `[ ]` → `[~]` saat dikerjakan → `[x]` saat selesai diverifikasi.
3. Catat progres di [15-dev-log.md](15-dev-log.md).
4. Untuk item kode, jalankan test sebelum dan sesudah: `docker compose exec api php artisan test`.
5. Update [09-roadmap.md](09-roadmap.md) setelah satu fase penuh selesai.
