# 09 — Roadmap & Status

> **Titik masuk utama saat melanjutkan development.** Update file ini tiap fase berubah status.
> Status: `[ ]` todo · `[~]` in-progress · `[x]` done

## Status Global
- **Terakhir diupdate:** 2026-08-08
- **Kode aplikasi:** Backend 100% ✅, Frontend UI 100% ✅, **Full BFF 100% ✅**, **Error Monitoring 100% ✅** (P8 GlitchTip)
- **Lint:** 0 errors ✅
- **TypeScript:** 0 errors ✅
- **Build:** 17/17 pages ✅
- **Auth:** Session-based (Sanctum SPA — HttpOnly cookie + CSRF) + User approval flow
- **Pipeline:** 14 stages (pertanyaan-MCQ → analisa → prd → architecture → erd → api_contract → phases_web → standards_web → master_web → pertanyaan_mobile-MCQ → phases_mobile → standards_mobile → master_mobile → agents)
- **Testing:** Backend PHPUnit 150 pass ✅, Playwright E2E 10 test hijau ✅ (3 specs: auth, wizard, projects)
- **Status:** Semua fase utama + next-progress P1-P16 selesai. **Deep audit Buat Plan + Projects: 41 item / 5 checkpoint repair plan → [plan/master-repair.md](plan/master-repair.md)**. CP-1 ⏳ pending.

## Aturan Lintas-Fase (wajib tiap fase)
Setiap fase baru boleh ditandai `[x]` bila:
1. **Backend test** terkait lulus (`php artisan test`).
2. **Frontend Playwright** terkait lulus — tiap button/menu tertes, error diperbaiki **sampai fix**.
3. **Security checklist** bagian terkait lulus ([12-security-checklist](12-security-checklist.md)).
4. Proses dicatat di **[15-dev-log](15-dev-log.md)**.

## Fase

### [x] F0 — Dokumentasi
- [x] 00-README, 01-overview, 02-architecture, 03-database-schema
- [x] 04-api-contract, 05-wizard-flow, 06-ai-pipeline, 07-docker-setup
- [x] 08-frontend, 09-roadmap, 10-decision-log, 11-development-rules
- [x] 12-security-checklist, 13-backend-testing, 14-frontend-testing, 15-dev-log
- [x] AUTH.md (root, auth flow lengkap)

### [x] F1 — Skeleton Docker + Full BFF Pattern
- [x] `docker-compose.yml` dengan services: nginx, web, api, migrate, db, redis
- [x] **Full BFF:** nginx routes SEMUA ke Next.js → proxy `/api/*` ke Laravel internal
- [x] **~60 BFF routes** di Next.js proxy ke Laravel
- [x] Laravel (api:8000) HANYA accessible dari internal containers
- [x] Only nginx expose port 4197:80

### [x] F2 — Database & Migrasi
- [x] 20+ migration files (3 PostgreSQL schemas: master, project, settings)
- [x] Sanctum installed (`personal_access_tokens`)
- [x] DatabaseSeeder: admin user, AI Provider preset, 3 templates
- [x] Tables: users, projects, versions, phase_progress, activities, project_api_tokens, ai_providers, templates, dll.

### [x] F3 — Auth & RBAC + User Approval
- [x] AuthController: register/login/logout/user (Sanctum SPA Session — HttpOnly cookie + CSRF)
- [x] User approval flow: user non-pertama = `status: pending`, perlu approve admin
- [x] Middleware `role.admin` configured; user scoping di semua endpoint
- [x] Frontend: login/register/forgot-password/reset-password pages integrated
- [x] Google OAuth via Socialite

### [x] F4 — Settings
- [x] ProviderSettingsController: CRUD AI providers + test connection
- [x] UserSettingsController: CRUD users + role management + status approval
- [x] ProfileController: get/update user profile
- [x] All admin-only routes protected by `role.admin` middleware

### [x] F5 — Pipeline Backend (14 stages)
- [x] `AiClient.php`: streaming OpenAI-compatible + Anthropic Claude
- [x] `PipelineRunner.php`: 14 stages (pertanyaan→analisa→prd→architecture→erd→api_contract→phases_web→standards_web→master_web→pertanyaan_mobile→phases_mobile→standards_mobile→master_mobile→agents) + gate mobile
- [x] SSE events: status, token, artifact, done, fail
- [x] Auto-run & checkpoint modes
- [x] JSON validation dengan multi-strategy retry
- [x] Continuation untuk output terpotong (length finish reason)
- [x] DB locking untuk race condition prevention

### [x] F6 — Wizard Frontend
- [x] UI `/new`: IdeaInput, TargetBadge, StageTracker, ArtifactPanel, ErdDiagram (React Flow)
- [x] Checkpoint & auto-run modes toggle
- [x] SSE streaming: pertanyaan→master prompt, real-time token accumulation
- [x] Questions derivation from artifacts.pertanyaan (useMemo)
- [x] Inline artifact editing (PATCH /api/versions/{id}/artifacts)

### [x] F7 — Projects, Versioning, Progress, Export
- [x] ProjectController: CRUD + favorites + search/filter
- [x] VersionController: create, show, togglePhase, export (.md/.zip), updateArtifact, updateAnswers, diff
- [x] Activity Log tracking
- [x] Standards & Agents download/regenerate
- [x] Project API Tokens (webhook auth)

### [x] F8 — Landing + Templates + Polish
- [x] Landing page with hero, fitur, CTA
- [x] Templates CRUD (admin)
- [x] Dark mode, responsif, empty states
- [x] Activities, Help, Privacy, Terms pages

### [x] F9 — Verifikasi End-to-End
- [x] Backend tests: AuthTest, ProjectTest, SettingsTest, TemplateTest, HealthCheckTest, VersionTest, GenerateStreamTest, PipelineRunnerTest, AiClientTest, ModelTest, dll.
- [x] Playwright config + 1 smoke spec
- [x] SESSION_DRIVER=database, StartSession middleware
- [x] BFF cookie forwarding working

### [x] F10 — Dashboard, Inline Editing, Diff, Token Management
- [x] Dashboard analytics with recent_activities
- [x] Inline artifact editing + version diff
- [x] Project API tokens + webhook endpoint

### [x] R1 — Remediasi Audit (2026-07-26)
- [x] RA — Auth Docs Sync (8/8)
- [x] RD — Database Schema Docs Sync (6/6)
- [x] RP — AI Pipeline Code + Docs (7/7)
- [x] RW — Wizard Frontend Code + Docs (7/7)
- [x] RX — Export & Versioning Code + Tests (4/4)
- [x] RS — Security & Infrastructure (10/10, RS-9 FPM done)
- [x] RC — Component Structure Docs Sync (3/3)
- [x] RT — Test Coverage (9/9)
- [x] RL — Low Priority (5/5)
- Catatan: RS-7 (middleware Next.js) adalah false-positive — middleware guard tidak perlu karena auth via session cookie sudah ditangani Laravel. RS-9 (FPM production) terkonfirmasi sudah terimplementasi (php-fpm + nginx api upstream).

### [x] Phase 4 — Activity Log, Favorites, Search/Filter, Provider Health, Dashboard
- [x] Activity Log: migration, model, controller, BFF, UI tab
- [x] Favorites: toggle endpoint, heart button, filter
- [x] Search/Filter: `q` + `favorite` params
- [x] Provider Health: status dot
- [x] Dashboard: favorite_projects + recent_activities feed
- [x] Lint sweep: 27→0 errors
- [x] Build fixes: ThemeToggle SSR guard, Suspense boundary
- [x] tsc --noEmit: 0 errors, next build: 17/17 pages

### [x] Phase 5 — Monitoring + DX + UX Polish (MP0–MP13) (2026-08-13)
- [x] MP0: `/api/version` + InfoController + 3 test
- [x] MP1: RequestContext middleware (X-Request-ID) + 3 test
- [x] MP2: DemoDataSeeder (idempotent) + 4 test
- [x] MP3: `/api/admin/health` + 3 test
- [x] MP4: `/api/admin/migrations` + 2 test
- [x] MP5: Footer version badge + `/settings/about`
- [x] MP6: LiveProgressWidget (polling `/api/projects`)
- [x] MP7: WhatsNewModal (`localStorage app:lastSeenVersion`)
- [x] MP8: `/api/changelog` + 4 test
- [x] MP9: OnboardingTour 4 steps (`localStorage onboarding:completed`)
- [x] MP10: Confetti (CSS, `prefers-reduced-motion`)
- [x] MP11: Accent color picker (migration + ProfileTest 5/5)
- [x] MP12: E20–E25 sync `docs/22-e2e-test-plan.md`
- [x] MP13: Final pass — backend 243 test pass, frontend lint/tsc/build 0

### [x] Phase 6 — Pertanyaan Stage Performance (2026-08-13)
- [x] `MAX_MCQ_RETRIES = 180 → 10` (`PipelineRunner.php:35`)
- [x] Exponential backoff di retry loop (`PipelineRunner.php:477`)
- [x] Log retry count (Log::info/warning)
- [x] Truncate `master_prompt` pertanyaan_mobile 2000 char (`PipelineRunner.php:294`)
- [x] Helper `truncateForContext()` static
- [x] Frontend spinner + retry counter inline
- [x] Backend test 246 pass (+3 baru), frontend lint/tsc clean
- [x] Plan saved di `docs/24-pertanyaan-performance.md`

### [x] Phase 7 — Bypass BFF + Direct Domain Routing (2026-08-14)
- [x] Backend CORS config (`config/cors.php`): allowlist `https://aiplanstudio.arsyiladm.my.id` + localhost dev
- [x] Backend session config: `SESSION_SAME_SITE=none`, `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`
- [x] Frontend `NEXT_PUBLIC_API_URL` env (production + development)
- [x] Frontend `api.ts`: BASE = env var (direct, no BFF)
- [x] Hapus `web/src/lib/bff.ts` + `web/src/app/api/**` (40+ BFF routes)
- [x] Frontend `middleware.ts`: pass-through (cookie cross-origin tidak readable)
- [x] Frontend `next.config.ts`: CSP `connect-src ${API_URL}`, `frame-ancestors 'none'`
- [x] Docker compose: build arg `NEXT_PUBLIC_API_URL`
- [x] nginx_api: listen 80 (sebelumnya hanya 8000)
- [x] Cloudflare tunnel: `aiplanstudio.arsyiladm.my.id` + `api-aiplanstudio.arsyiladm.my.id` (verified)
- [x] Backend test 246 pass, lint/tsc 0
- [x] CORS preflight verified (valid origin allowed, evil origin blocked)
- [x] Cookies verified: `secure; samesite=none`
- [x] Plan saved di `docs/25-bypass-bff.md`

## Catatan Melanjutkan Sesi
1. Baca [17-next-progress.md](17-next-progress.md) untuk next steps terprioritas.
2. Baca [11-development-rules](11-development-rules.md) sebelum menulis kode.
3. Kerjakan hanya fase aktif; update checkbox setelah selesai.
4. Catat keputusan baru di [10-decision-log](10-decision-log.md).
5. Sesudah mengubah kode, update dokumentasi terkait **terlebih dahulu** sebelum commit.
