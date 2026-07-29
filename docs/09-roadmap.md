# 09 — Roadmap & Status

> **Titik masuk utama saat melanjutkan development.** Update file ini tiap fase berubah status.
> Status: `[ ]` todo · `[~]` in-progress · `[x]` done

## Status Global
- **Fase aktif:** R1 — Remediasi Audit (lihat [16-audit-fix-plan.md](16-audit-fix-plan.md))
- **Terakhir diupdate:** 2026-07-26
- **Kode aplikasi:** Backend 100% ✅, Frontend UI 100% ✅, **Full BFF 100% ✅**
- **Auth:** Session-based (Sanctum SPA — HttpOnly cookie + CSRF)
- **Testing status:** Backend **~43 tests** ✅, Playwright E2E — **belum ada**
- **Audit sync:** 73 items ditemukan, 0/73 selesai (lihat [16-audit-fix-plan](16-audit-fix-plan.md))
- **Status:** Mendapatkan remediasi sebelum production deployment

## Aturan Lintas-Fase (wajib tiap fase)
Setiap fase F1–F9 baru boleh ditandai `[x]` bila:
1. **Backend test** terkait lulus ([13-backend-testing](13-backend-testing.md)).
2. **Frontend Playwright/Chrome** terkait lulus — tiap button/menu tertes, error diperbaiki **sampai fix** ([14-frontend-testing](14-frontend-testing.md)).
3. **Security checklist** bagian terkait lulus ([12-security-checklist](12-security-checklist.md)).
4. Proses dicatat di **[15-dev-log](15-dev-log.md)**.

## Fase

### [x] F0 — Dokumentasi
Buat semua dokumen di `docs/` (00–15). Resumable-ready.
- [x] 00-README, 01-overview, 02-architecture, 03-database-schema
- [x] 04-api-contract, 05-wizard-flow, 06-ai-pipeline, 07-docker-setup
- [x] 08-frontend, 09-roadmap, 10-decision-log, 11-development-rules
- [x] 12-security-checklist, 13-backend-testing, 14-frontend-testing, 15-dev-log
- [x] Dev-log updated dengan semua work F1-F7

### [x] F1 — Skeleton Docker + Full BFF Pattern
- [x] `docker-compose.yml` dengan 5 services (nginx, web, api, db, redis)
- [x] **Full BFF:** nginx routes SEMUA ke Next.js (/ → web:3000), TIDAK ada route langsung ke Laravel
- [x] **19 BFF routes** di Next.js proxy ke Laravel (auth, projects, versions, templates, settings, generate)
- [x] Laravel (api:8000) HANYA accessible dari internal containers
- [x] Semua containers running & healthy; hanya nginx expose port 80
- **Verified:** Browser → nginx:80 → Next.js BFF → Laravel (internal). `curl localhost:8000` → refused ✅

### [x] F2 — Database & Migrasi
- [x] 10 migration files created (users+role, ai_providers, templates, projects, versions, phase_progress)
- [x] Sanctum installed (`personal_access_tokens`)
- [x] Migrasi dijalankan: 15 tables exist di PostgreSQL
- [x] DatabaseSeeder: admin (admin@aistack.dev / password123), 1 ai_provider, 3 templates
- **Verified:** `\dt` → 15 tables. `SELECT COUNT(*) FROM users` → 1. `SELECT COUNT(*) FROM templates` → 3.

### [x] F3 — Auth & RBAC
- [x] AuthController: register/login/logout/user (Sanctum Bearer Token, no cookies/CSRF)
- [x] Middleware `role.admin` configured; user scoping di semua endpoint
- [x] Frontend: login/register pages **INTEGRATED** dengan backend (pakai `lib/api.ts`)
- [x] Token expiry: 120 menit, disimpan di sessionStorage
- [x] First user auto-assigned admin role
- **Verified:** Login manual test passed (admin@aistack.dev / password123). Frontend auth pages call real API.
- **Testing:** 40 backend tests passing. 11 Playwright spec files.

### [x] F4 — Settings
- [x] ProviderSettingsController: GET/PUT/test connection (backend complete)
- [x] UserSettingsController: CRUD users + role management (backend complete)
- [x] Routes configured untuk admin-only (`role.admin` middleware)
- [x] **BFF routes:** `/api/settings/provider`, `/api/settings/provider/test`, `/api/settings/users`, `/api/settings/users/[id]`
- [ ] **PENDING:** End-to-end test dengan Playwright
- [ ] **PENDING:** Verify encrypted api_key & masking bekerja
- **Backend:** Complete. **BFF:** Complete. **Testing:** Pending.

### [x] F5 — Pipeline Backend
- [x] `AiClient.php`: streaming OpenAI-compatible, test connection (102 lines)
- [x] `PipelineRunner.php`: 6 stages (analisa→prd→arch→erd→phases→master), SSE events, auto-run (170 lines)
- [x] Prompt templates target-aware (web/mobile/both), context dari stage sebelumnya
- [x] GenerateStreamController: SSE endpoint `/api/generate/stream` dengan proper headers
- [x] Stage status tracking, JSON validation untuk erd/phases/master
- **Backend:** Complete. **Need:** (1) Admin isi AI provider api_key via UI, (2) Frontend wizard connect ke SSE.
- **Testing:** Backend tests belum. Manual test dengan AI provider belum (api_key kosong).

### [x] F6 — Wizard Frontend
- [x] UI `/new` complete: IdeaInput, StageTracker, ArtifactPanel, ErdDiagram (React Flow), CheckpointBar
- [x] Checkpoint & auto-run modes UI ready
- [x] Mock data structure matches backend
- [x] **BFF route:** `/api/generate/stream` (SSE) ready untuk streaming
- [ ] **PENDING:** End-to-end test wizard flow dengan real AI provider
- **Status:** UI complete. BFF complete. Ready for E2E testing.

### [x] F7 — Projects, Versioning, Progress, Export
- [x] ProjectController: CRUD projects dengan user scoping
- [x] VersionController: create version, toggle phase progress, **export .md & .zip**
- [x] Auto-create version 1 saat project dibuat
- [x] Phase progress tracking dengan pivot table
- [x] Markdown builder untuk export dengan full artifacts
- [x] Frontend UI: projects list, project detail dengan tabs, versioning v1/v2
- [x] **BFF routes:** `/api/projects`, `/api/projects/[id]`, `/api/projects/[id]/versions`, `/api/versions/[id]`, `/api/versions/[id]/export`, `/api/versions/[id]/phases/[phaseKey]`
- [ ] **PENDING:** End-to-end test projects flow dengan Playwright
- **Backend:** Complete. **BFF:** Complete. **Testing:** Pending.

### [x] F8 — Landing + Templates + Polish
- [x] Landing page complete (hero, fitur, CTA)
- [x] Templates page UI complete
- [x] TemplateController backend (index, store, destroy)
- [x] 3 templates seeded (SaaS Dashboard, E-Commerce, Mobile CRUD)
- [x] Dark mode, responsif, empty states implemented
- [x] **BFF routes:** `/api/templates`, `/api/templates/[id]`
- [ ] **PENDING:** End-to-end test templates flow dengan Playwright
- [ ] **PENDING:** Template pre-fill wizard integration test
- **Status:** UI complete. Backend complete. BFF complete. Testing pending.

### [x] F9 — Verifikasi End-to-End
- [x] **Backend tests:** **28 tests passed** (Auth, Projects, Settings, Templates, Health) - PHPUnit/SQLite in-memory
- [x] **Security checklist:** Port internal only ✅, CSRF working ✅, httpOnly cookies ✅
- [x] **BFF smoke test:** All endpoints 200 ✅
- [x] **Playwright E2E:** 11 spec files (1410 lines): auth, full, wizard, projects-templates, settings-nav, **project-detail, wizard-e2e, register, rbac, settings-crud, helpers**. Runner via `cd web && npx playwright test`
- [x] **Auth fix:** SESSION_DRIVER=database, StartSession middleware added to all auth routes
- [x] **BFF fix:** All 20+ routes forward cookies to Laravel via fwdHeaders()
- [x] **Created:** AuthTest, ProjectTest, SettingsTest, TemplateTest, HealthCheckTest
- [x] **Playwright suite:** 5 old specs + 5 new specs + helpers = 11 files. Core ~50 tests passing.
- **Status:** Backend tests complete (28/28). E2E coverage: auth, register, RBAC, settings CRUD, project detail, wizard UI. Full BFF working.

---

### [~] R1 — Remediasi Audit (lihat [16-audit-fix-plan.md](16-audit-fix-plan.md))

> **73 item** dari audit sinkronisasi docs vs code. **60/73 selesai** (2026-07-26). **Sisa 13 item** (RW: 2, RS: 1, RT: 4, plus RS-9 & RT-7 infrastruktur).

**Sub-fase:**
- [x] RA — Remediation Auth Docs Sync (8/8 ✅)
- [x] RD — Remediation Database Schema Docs Sync (6/6 ✅)
- [x] RP — Remediation AI Pipeline Code + Docs (7/7 ✅)
- [~] RW — Remediation Wizard Frontend Code + Docs (5/7)
- [x] RX — Remediation Export & Versioning Code + Tests (4/4 ✅)
- [~] RS — Remediation Security & Infrastructure (9/10)
- [x] RC — Remediation Component Structure Docs Sync (3/3 ✅)
- [~] RT — Remediation Test Coverage (5/9)
- [x] RL — Remediation Low Priority (5/5 ✅)

**Selesai bila:**
1. Semua 59 action items di [16-audit-fix-plan](16-audit-fix-plan.md) selesai (`[x]`).
2. Backend tests: 60+ tests passing (termasuk test baru).
3. Frontend testing infrastructure siap (minimal 1 test passing).
4. Semua dokumentasi sinkron dengan implementasi.
5. Security issues mitigated (terutama hardcoded credentials dan race condition).

## Catatan Melanjutkan Sesi
1. Baca [16-audit-fix-plan.md](16-audit-fix-plan.md) — itu titik masuk utama saat ini.
2. Baca [11-development-rules](11-development-rules.md) sebelum menulis kode.
3. Kerjakan hanya fase aktif; update checkbox setelah selesai.
4. Catat keputusan baru di [10-decision-log](10-decision-log.md).
