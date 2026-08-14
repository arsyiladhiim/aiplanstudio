# MASTER PROMPT: AI Plan Studio — Codebase Reference & Build Guide

> **Generated**: 2026-08-12  
> **Git HEAD**: `e57e6fe`  
> **Graph**: 1003 nodes, 1711 edges, 130 communities  
> **Stack**: Laravel 13 (PHP 8.3) + Next.js 16 (React 19) + PostgreSQL 16 + Docker Compose

---

## KONTEKS PROYEK

AI Plan Studio adalah **plan generator** — bukan code executor. Produk ini membantu solo developer menyiapkan input untuk AI coding agent (master prompt, ERD, API contract, phase breakdown, STANDARDS.md, AGENTS.md) melalui pipeline 14-stage yang mengubah ide aplikasi menjadi dokumen spesifikasi lengkap.

**URL Produksi**: `http://localhost:4197` (nginx → Next.js BFF → Laravel API)

**Arsitektur BFF**: Browser → nginx_web (:4197) → Next.js (:3000) → nginx_api (:8000) → PHP-FPM (:9000) → Laravel. Laravel **tidak pernah** diakses langsung dari browser.

---

## RINGKASAN ARTIFAK

### Stack
- **Backend**: Laravel 13 (PHP 8.3), Sanctum SPA Session Auth (HttpOnly cookie + CSRF, **bukan** Bearer token)
- **Frontend**: Next.js 16 (App Router, React 19.2, TypeScript 5), Tailwind CSS v4, standalone build
- **Database**: PostgreSQL 16 — 3 schemas: `aiplanstudio_master`, `aiplanstudio_project`, `aiplanstudio_settings`
- **Cache/Session**: Redis (Redis), Database (session driver)
- **Infra**: Docker Compose — 6 containers (nginx_web, next.js, nginx_api, php-fpm, postgres, redis)
- **AI Providers**: Multi-provider (OpenAI, Anthropic, Mistral, Cohere, Custom) — API key encrypted in DB
- **Monitoring**: GlitchTip/Sentry DISABLED (SDK kept, DSN empty)
- **Testing**: PHPUnit (backend, 186 pass), Playwright (e2e)

### Arsitektur BFF
| Layer | Tech | Port |
|-------|------|------|
| Front proxy | nginx:alpine | :4197 |
| BFF | Next.js 16 (standalone) | :3000 |
| API proxy | nginx:alpine | :8000 |
| App server | PHP-FPM 8.3 | :9000 |
| Database | PostgreSQL 16-alpine | :5432 |
| Cache | Redis:alpine | :6379 |

**Request flow**: `Browser → nginx (:4197) → Next.js BFF (:3000) [route handlers proxy] → nginx_api (:8000) → PHP-FPM (:9000) → Laravel`

### ERD (3 Schemas)
```
aiplanstudio_master:
  users (id, name, email, password, role[admin|member], status[active|pending])
  templates (id, name, target, description, seed)
  password_reset_tokens, personal_access_tokens

aiplanstudio_project:
  projects (id, user_id FK, title, idea, target[web|both], stack, is_favorite)
  versions (id, project_id FK, version_no, source_version_id, stage_status, answers,
            analysis, prd, architecture, erd, api_contract, phases, mobile_phases,
            master_prompt, mobile_master_prompt, standards, agents, tracking_token)
  phase_progress (id, version_id FK, phase_key, done, status, output, started_at, finished_at)
  task_progress (id, phase_progress_id FK, task_key, task_type, title, status, output)
  activities (id, project_id FK, version_id FK, user_id FK, action, description, metadata)
  project_api_tokens (id, project_id FK, name, token_hash, expires_at)

aiplanstudio_settings:
  sessions, cache, cache_locks, jobs, job_batches, failed_jobs
  ai_providers (id, name, base_url, provider_type, api_key[encrypted], model, is_active)
```

### API Contract (key endpoints)
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | /api/register | public | First user=admin, others=member+pending |
| POST | /api/login | public | Sanctum SPA session login |
| POST | /api/logout | sanctum | Session invalidate |
| GET | /api/user | sanctum | Current user |
| GET/POST | /api/projects | sanctum | Project CRUD |
| GET/PATCH/DELETE | /api/projects/{id} | sanctum | Project detail/update/delete |
| POST | /api/projects/{id}/versions | sanctum | Create version (clone baseline) |
| GET | /api/versions/{id} | sanctum | Version with phaseProgress |
| PATCH | /api/versions/{id}/artifacts | sanctum | Inline artifact edit per stage |
| PATCH | /api/versions/{id}/answers | sanctum | Save MCQ answers |
| PATCH | /api/versions/{id}/phases/{phaseKey} | sanctum | Toggle fase done/pending |
| PATCH | /api/versions/{id}/tasks/{taskKey} | sanctum | Toggle sub-item done/pending |
| POST | /api/generate/stream | sanctum | SSE pipeline stream (14 stages) |
| GET | /api/versions/{id}/phase-progress/stream | sanctum | SSE realtime tracking (2s poll) |
| POST | /api/webhooks/phase-complete | project-token | External AI agent webhook (3-level tracking) |
| GET | /api/dashboard/stats | sanctum | Server-computed dashboard stats |
| GET/POST/PATCH/DELETE | /api/settings/provider[/{id}] | admin | AI provider CRUD |
| GET/POST/PATCH/DELETE | /api/settings/users[/{id}] | admin | User management |
| GET | /api/activities | admin | Global activity log |

---

## VIBE-CODING RULES (WAJIB dipatuhi AI agent)

### 1. BFF Pattern — NEVER bypass Next.js
- All API calls from browser MUST go through Next.js route handlers (`web/src/app/api/`)
- Next.js BFF proxies to Laravel via `safeFwd()` / `cookieHeaders()` from `web/src/lib/bff.ts`
- Laravel URL: `process.env.LARAVEL_URL ?? 'http://aiplanstudionginx_api:8000'`
- Cookie forwarding: `Cookie`, `X-XSRF-TOKEN`, `Origin`, `Referer` from browser request → Laravel
- Set-Cookie headers preserved from Laravel response → browser

### 2. Auth: Sanctum SPA Session — NOT Bearer Token
- **User auth**: HttpOnly session cookie + CSRF token. Sanctum guard = `['web']`
- **CSRF flow**: `fetchCsrfCookie()` → GET `/api/sanctum/csrf-cookie` → XSRF-TOKEN cookie (NOT HttpOnly) → `X-XSRF-TOKEN` header on state-changing requests
- **401 handling**: `window.location.href = "/login"` (session expired)
- **419 handling**: Clear CSRF singleton, re-fetch cookie, retry once
- **Webhook auth**: SEPARATE — Bearer `project_api_token` (SHA-256 hash in `project_api_tokens.token_hash`), NOT session
- **First user**: admin + active. Subsequent users: member + pending (admin approval required via `status` column)

### 3. SQL Schema — 3 PostgreSQL Schemas
- `search_path`: `'public, aiplanstudio_master, aiplanstudio_project, aiplanstudio_settings'`
- Tables reference unqualified — schema determined by migration's `schema`
- Cross-schema FK: `projects.user_id` → `aiplanstudio_master.users`, `activities.user_id` → `aiplanstudio_master.users`
- All migration files MUST specify schema explicitly in `Schema::create('schema.table_name', ...)`

### 4. AI Pipeline — 14 Stages, Sequential, Gate-Controlled
```
 1. pertanyaan        MCQ clarification (5-10 questions, JSON)
 2. analisa           Application analysis
 3. prd               Product Requirements Document
 4. architecture      Tech architecture + folder structure
 5. erd               Entity Relationship Diagram (text format → parsed JSON)
 6. api_contract      REST API endpoint list (JSON array)
 7. phases_web        Web phase breakdown (structured text → parsed JSON with sub-items)
 8. standards_web     STANDARDS.md content
 9. master_web        Master prompt (self-contained build doc + webhook tracking block)
10. pertanyaan_mobile Mobile MCQ (ONLY if target='both', GATED on master_web done)
11. phases_mobile     Mobile phase breakdown (Flutter/Dart)
12. standards_mobile  Mobile STANDARDS.md
13. master_mobile     Mobile master prompt
14. agents            AGENTS.md file
```

- **Mobile gate**: stages 10-13 skip if `target !== 'both'`. If `target === 'both'`, mobile stages wait for `master_web` = done.
- **Error handling**: ERD, api_contract, phases_web, master_web, phases_mobile, master_mobile errors halt pipeline. Others continue.
- **MCQ retry**: min 5 questions, max 10, max 180 retries. If < 5 → retry with JSON-only instruction.

### 5. 3-Level Tracking System (fase → sub-item → checkpoint)
```
Level 1: PhaseProgress (version_id, phase_key, status, done, output)
  └── Level 2: TaskProgress (phase_progress_id, task_key, task_type, status, output)
       └── Level 3: task_type categories: halaman | menu | fitur | flow | api
```

**Webhook** (`POST /api/webhooks/phase-complete`):
- Auth: `Authorization: Bearer {project_api_token}` (NOT session)
- Fase-level: `{"version_id", "phase_key", "status", "output"}`
- Sub-item: `{"version_id", "phase_key", "task_key", "task_type", "title", "status", "output"}`
- `phase_key` accepts real key (`fase1_setup`) or `phase-N` / `fase-N` (maps by index)

**SSE realtime**: `GET /api/versions/{id}/phase-progress/stream` — 2s poll, 20min max, emits phase_progress with nested `tasks[]`

### 6. Prompt-to-Stage Mapping
| Stage | Prompt file | Notes |
|-------|-------------|-------|
| pertanyaan | `pertanyaan.php` | MCQ with ambiguities analysis |
| pertanyaan_mobile | `pertanyaan_mobile.php` | Mobile-specific MCQ |
| analisa | `analisa.php` | |
| prd | `prd.php` | |
| architecture | `architecture.php` | |
| erd | `erd.php` | Text format (TABEL:/RELASI:/API:) → AiOutputParser |
| api_contract | `api_contract.php` | JSON array |
| phases_web | `phases.php` | Structured text with HALAMAN/MENU/FITUR/FLOW/API |
| phases_mobile | `phases_mobile.php` | Mobile adaptation (Screen/Drawer) |
| standards_web | `standards.php` | Web coding standards |
| standards_mobile | `standards.php` | Mobile coding standards (Flutter/Dart) |
| master_web | `phased_master.php` | Self-contained build doc + webhook tracking |
| master_mobile | `phased_master_mobile.php` | Mobile master prompt |
| agents | `agents.php` | AI agent behavior rules |

### 7. Artifact Parsing
| Stage | Parser | Output |
|-------|--------|--------|
| pertanyaan | `AiJsonParser::extractJson()` + `tryJsonDecode()` | Pretty-printed JSON text |
| erd | `AiOutputParser::parseErdText()` | `{nodes, edges, api_contract}` + writes `api_contract` column |
| api_contract | `AiJsonParser` | JSON array |
| phases_web/mobile | `AiOutputParser::parsePhasesText()` | Array of `PhaseItem` with sub-items |
| master_web/mobile | `stripTrackingToken()` — redacts token from content | Text with `[REDACTED]` |

### 8. Frontend Design System — Emerald-Teal, Light-First
- Brand: `--color-brand: #10b981` (emerald), `--color-brand-2: #14b8a6` (teal)
- Light theme = default (no `data-theme` attr). Dark = opt-in via `[data-theme="dark"]`
- All colors via CSS variables in `globals.css` — no hardcoded hex
- Theme toggle logic inverted: dark opt-in, light default
- UI components: Button (emerald gradient primary), Card, Badge, Input, Textarea, Modal (`"use client"`)

### 9. Project Detail Tabs
- Tabs: `Overview | Web | Mobile | Tracking | Activity`
- Mobile tab auto-hides when `target !== "both"`
- Tracking tab uses `TrackingPanel` component (hierarchical tree: fase → sub-items → checkpoint)
- 5 sub-item categories per fase: Halaman, Menu, Fitur, Flow, API

### 10. Version Management
- Create version: strategy `from_last` (clone all artifacts + stage_status + source_version_id) or `blank`
- Version delete: cannot delete last version
- Version diff: field-by-field comparison
- Export: `md` (single file) or `zip` (includes erd.json, mobile artifacts)

---

## STANDARS (Coding Conventions)

### Backend (Laravel 13 / PHP 8.3)
- PSR-12 coding style
- Type hints for all params + return types
- Form Request for validation input
- snake_case for DB columns, camelCase for methods
- All queries MUST filter by `user_id` ownership (security)
- SSRF protection: `AiClient::validateBaseUrl()` blocks internal IPs (localhost, 127.0.0.1, Docker names, 0.0.0.0, ::1, host.docker.internal)

### Frontend (Next.js 16 / React 19 / TypeScript 5)
- App Router ONLY (no Pages Router)
- Server Components by default
- `"use client"` ONLY when interactivity/hooks needed (Modal.tsx lesson learned)
- TypeScript strict mode
- Tailwind CSS v4 — utility-first, CSS var tokens from `globals.css`
- No hardcoded hex colors — use `var(--color-*)`
- `apiFetch<T>()` for all API calls via BFF
- `createSSE()` for EventSource streams, `createSSEPost()` for CSRF-protected streams

### Database
- 3 PostgreSQL schemas: `aiplanstudio_master`, `aiplanstudio_project`, `aiplanstudio_settings`
- `search_path`: `'public, aiplanstudio_master, aiplanstudio_project, aiplanstudio_settings'`
- snake_case for table + column names
- `created_at, updated_at` on every table
- JSONB columns for flexible artifacts (stage_status, erd, phases, answers, etc.)

### Git Convention
- `feat(scope): description` — new feature
- `fix(scope): description` — bug fix
- `chore(scope): description` — maintenance
- `docs: description` — documentation

### Testing
- Backend: `docker compose exec -T aiplanstudio_apifpm sh -c 'php artisan test 2>&1'`
- Frontend lint: `cd web && npm run lint`
- TypeScript: `cd web && npx tsc --noEmit`
- Frontend build: `cd web && npm run build`
- E2E: Playwright (`web/e2e/`)

### Docker
- Build: `docker compose up -d --build`
- Down: `docker compose down`
- Status: `docker compose ps`
- Logs: `docker compose logs --tail=20 {container}`
- PHP lint: `docker compose exec -T aiplanstudio_apifpm sh -c 'php -l {file}'`
- Artisan: `docker compose exec -T aiplanstudio_apifpm sh -c 'php artisan {cmd}'`
- Node install: `docker run --rm -v "$PWD/web":/work -w /work node:20-alpine npm {cmd}` (DON'T chown node_modules)

### Host Permission (sudo)
- Root-owned bind mounts from Docker: normal. `echo "bismillah" | sudo -S chown -R $(id -u):$(id -g) {path}` when needed
- DON'T chown `web/node_modules/`, `api/vendor/`, `api/database/database.sqlite`, Docker data dirs

### NVM Required
```bash
export NVM_DIR="$HOME/.nvm"; [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
```
Node v24.19.0 active for graphify and frontend tooling.

---

## AGENTS (AI Behavior Rules)

### Rules
1. Read STANDARDS.md (this section) BEFORE writing any code
2. Don't delete/rename files without explicit instruction
3. Follow existing folder structure
4. Every change committed with conventional format: `feat(scope): desc`, `fix(scope): desc`
5. If unsure about tech decision, ASK — don't assume
6. Prioritize existing code over rewrite from scratch
7. Use installed dependencies; don't add new ones without strong reason
8. Run `npm run lint` + `npx tsc --noEmit` after TS edits
9. Run `php artisan test` after PHP edits
10. Run `graphify update .` after code changes to keep graph current
11. Use Context7 MCP for library/framework documentation lookups
12. All API calls go through Next.js BFF — NEVER direct to Laravel

### File Structure
```
aiplanstudio/
├── api/
│   ├── app/
│   │   ├── Http/Controllers/    (14 controllers)
│   │   ├── Http/Middleware/      (AuthenticateProjectToken, EnsureUserIsAdmin, StartSessionIfStateless)
│   │   ├── Models/              (9 models: User, Project, Version, PhaseProgress, TaskProgress, etc.)
│   │   ├── Prompts/              (15 prompt files: pertanyaan, analisa, prd, architecture, erd, ...)
│   │   ├── Services/             (PipelineRunner, AiClient, AiOutputParser, AiJsonParser, SseEmitter)
│   │   └── Providers/
│   ├── config/
│   ├── database/migrations/     (26 migrations across 3 schemas)
│   ├── routes/api.php
│   └── tests/                    (19 feature tests, 2 unit tests)
├── web/
│   ├── src/
│   │   ├── app/
│   │   │   ├── (app)/            (authenticated pages: dashboard, projects, new, settings, templates, activities)
│   │   │   ├── (auth)/           (unauthenticated: login, register, forgot/reset-password)
│   │   │   ├── api/              (44 BFF route handlers — proxy to Laravel)
│   │   │   └── globals.css       (emerald-teal design tokens, light-first)
│   │   ├── components/
│   │   │   ├── ui/               (Button, Card, Badge, Input, Modal, ConfirmDialog, Markdown, cva)
│   │   │   ├── wizard/           (TrackingPanel, PhaseBreakdownCard, McqForm, ErdDiagram, ApiContractTable, TrackingPhases)
│   │   │   ├── project/          (ApiTokenSection)
│   │   │   ├── AppShell.tsx
│   │   │   ├── UserContext.tsx
│   │   │   ├── ThemeToggle.tsx
│   │   │   └── common.tsx
│   │   └── lib/
│   │       ├── api.ts            (fetch wrapper, CSRF, SSE, types, toggleTask)
│   │       ├── bff.ts            (cookie forwarding, safeFwd, sseCookieHeaders, sanitizePathSegment)
│   │       ├── mock.ts           (getStages, samplePhases, PhaseItem type)
│   │       └── format.ts
│   ├── e2e/                      (auth.spec, projects.spec, wizard.spec)
│   └── package.json
├── docker-compose.yml
├── .graphify/                    (knowledge graph: 1003 nodes, 1711 edges, 130 communities)
└── docs/                         (20 documentation files: 00-README through 20-dynamic-mcq)
```

### Commands
| Command | Purpose |
|---------|---------|
| `docker compose up -d --build` | Build + start all containers |
| `docker compose down` | Stop all containers |
| `docker compose exec -T aiplanstudio_apifpm sh -c 'php artisan test 2>&1'` | Backend tests |
| `docker compose exec -T aiplanstudio_apifpm sh -c 'php artisan {cmd}'` | Artisan commands |
| `docker compose exec -T aiplanstudio_apifpm sh -c 'php -l {file}'` | PHP lint |
| `cd web && npm run lint` | Frontend lint |
| `cd web && npx tsc --noEmit` | TypeScript check |
| `cd web && npm run build` | Frontend build |
| `graphify update .` | Update knowledge graph (AST-only, no LLM) |
| `graphify query "<question>"` | Query knowledge graph |

### Graphify
- Knowledge graph at `.graphify/graph.json` — 1003 nodes, 1711 edges, 130 communities
- `graphify update .` = AST-only incremental rebuild (free, no LLM)
- `graphify extract . --backend claude-cli --semantic <path>` = full rebuild with semantic
- Git hooks installed: auto-update on commit, checkout, merge, rewrite
- Query tools: `graphify query`, `graphify path`, `graphify explain`, `graphify summary`
- Review tools: `graphify review-analysis`, `graphify recommend-commits`

---

## KNOWN ISSUES & TECHNICAL DEBT

### Stale References in Prompts
- `helpers.php` line 9-12: references "Laravel 11 (PHP 8.4)" — actual stack is Laravel 13 (PHP 8.3)
- `agents.php` line 107: references "PostgreSQL 18" — actual is PostgreSQL 16
- `agents.php` line 107: references "Laravel Horizon" — not installed
- These are in AI-generated output prompts ( templates for downstream projects), not the codebase itself

### Pre-existing Test Failures (2)
1. `AiClientSsrfTest` — fails due to DNS resolution in Docker (expected)
2. `GenerateStreamTest::auto mode runs multiple stages` — SSE empty in test env (expected)
- All other 186 tests pass, including 8 new TaskProgressTest

### GlitchTip/Sentry
- DISABLED in docker-compose.yml. SDK code preserved (`@sentry/nextjs`, `sentry/sentry-laravel`) but DSN endpoints empty.

---

## DECISION LOG (key design decisions)

| ID | Decision | Rationale |
|----|----------|-----------|
| D-001 | Product = Generator (not executor) | Solo dev needs input for AI agent; keeps MVP scope |
| D-002 | Multi-User + Versioning | User request: User Management + Projects with versions |
| D-004 | Sanctum SPA Cookie + CSRF | Same-origin → no CORS; secrets stay backend; no token in JS |
| D-006 | Docker + nginx-only expose | Network isolation; single entry point (nginx) |
| D-007 | Wizard + Checkpoint | Solo dev needs correction points; wrong analysis → wrong downstream |
| D-008 | Documentation First | Large multi-service project; dev must be resumable |
| D-009 | Testing Wajib + Playwright | Guarantee UI works in real browser; auditable trail |
| D-010 | BFF Architecture | Better cookie/cookie control, consistent error handling |
| D-016 | Back to Sanctum SPA (from Bearer) | Bearer only in docs — code always was SPA. Sync docs > migrate |
| D-028 | Pipeline 13→14 Stage Split | phased_master overload → split into 4 stages for quality |

---

> Master prompt ini adalah dokumen hidup. Update setiap ada perubahan arsitektur major.  
> Graphify graph: `graphify query "<question>"` untuk navigasi codebase.  
> Git hooks: auto-update graph setiap commit/checkout/merge.
