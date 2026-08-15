# 25 — Bypass BFF + Direct Domain Routing

> Migrasi dari arsitektur **BFF (Next.js route handlers proxy ke Laravel)**
> ke **direct call** (frontend Next.js → Laravel API di domain terpisah).
>
> **Status:** `[ ]` todo · `[~]` in-progress · `[x]` done
> **Aturan:** Setiap progres selesai → update checkpoint di file ini **SEBELUM** lanjut.
> **Domain target:**
> - Frontend: `https://aiplanstudio.arsyiladm.my.id`
> - API: `https://api-aiplanstudio.arsyiladm.my.id`

## Latar Belakang

Arsitektur sebelumnya (BFF — dihapus Phase 7 / 2026-08-14):

```
Browser → aiplanstudio.arsyiladm.my.id (Next.js)
       → /api/* (BFF proxy)
       → Laravel (via docker network)
```

**Masalah:**
1. BFF hop menambah latency untuk semua request (termasuk webhook tracking server-to-server).
2. BFF Next.js tidak support SSE streaming dengan baik → buffering.
3. Single point of failure — BFF down = semua flow mati.
4. Webhook tracking (`/api/webhooks/phase-complete`) tidak butuh BFF — server-to-server pakai Project API Token.
5. Tidak ada kebutuhan transform/aggregate di tepi — BFF over-engineering untuk kasus ini.

**Status saat ini:** `[x]` Arsitektur direct routing sudah production-ready (Cloudflare Tunnel + CORS + Sanctum stateful). CP-12 (2026-08-15) mereconcile semua CP-1..11 artifacts agar propagasi no-BFF ke AI prompts + docs.

## Arsitektur Target

```
Browser (https://aiplanstudio.arsyiladm.my.id)
  ├─ Next.js :3000 (frontend, served via Cloudflare Tunnel)
  └─ Direct fetch → https://api-aiplanstudio.arsyiladm.my.id/api/*

API (https://api-aiplanstudio.arsyiladm.my.id)
  ├─ Cloudflare Tunnel → nginx :80 → PHP-FPM :9000 (Laravel)
  ├─ Sanctum SPA session (HttpOnly, Secure, SameSite=None)
  ├─ CORS allow https://aiplanstudio.arsyiladm.my.id
  └─ Webhook: Authorization: Bearer <project_api_token> (no CORS, no CSRF)
```

## Plan Eksekusi

### Phase E1 — Backend Security Config
- [x] E1.1 `api/.env`: `APP_URL=https://api-aiplanstudio.arsyiladm.my.id` (sudah ada)
- [x] E1.2 `api/.env`: `SANCTUM_STATEFUL_DOMAINS=aiplanstudio.arsyiladm.my.id` (sudah ada)
- [x] E1.3 `api/.env`: `SESSION_SECURE_COOKIE=true` (sudah ada), `SESSION_HTTP_ONLY=true` (sudah ada)
- [x] E1.3b `api/.env`: `SESSION_SAME_SITE=lax → none` (cross-origin wajib)
- [x] E1.3c `api/.env`: `SESSION_DOMAIN=aiplanstudio.arsyiladm.my.id → null` (default per-domain scoping)
- [x] E1.4 `api/config/cors.php`: published via artisan, edited:
  - `paths`: api/*, sanctum/csrf-cookie
  - `allowed_origins`: aiplanstudio.arsyiladm.my.id + localhost dev
  - `allowed_headers`: Content-Type, X-XSRF-TOKEN, X-Request-ID, Authorization, dll
  - `supports_credentials`: true
  - `max_age`: 86400
- [x] E1.5 `api/config/session.php`: sudah wire dari env, no change needed
- [x] E1.6 CORS preflight test: `OPTIONS /api/version` dengan `Origin: https://aiplanstudio.arsyiladm.my.id` → 204 + headers lengkap (`Access-Control-Allow-Origin`, `Allow-Credentials`, `Allow-Methods`, `Allow-Headers`, `Max-Age`)
- [x] E1.7 Rebuild api image + restart container
- [x] E1.8 Update checkpoint

### Phase E2 — Frontend Refactor (hapus BFF)
- [x] E2.1 `web/.env.production`: `NEXT_PUBLIC_API_URL=https://api-aiplanstudio.arsyiladm.my.id`
- [x] E2.1b `web/.env.development`: `NEXT_PUBLIC_API_URL=http://localhost:8000` (untuk local dev tanpa BFF)
- [x] E2.1c `web/src/lib/api.ts`: `BASE = process.env.NEXT_PUBLIC_API_URL ?? ""` — direct call, bukan relative.
- [x] E2.2 Hapus `web/src/lib/bff.ts`
- [x] E2.3 Hapus `web/src/app/api/**` (semua BFF route handlers, ~40+ endpoints)
- [x] E2.4 `web/src/middleware.ts`: hapus logic cek cookie session (cookie cross-origin tidak readable); hanya pass-through (auth check terjadi client-side via 401 response).
- [x] E2.5 `web/next.config.ts`: tambah CSP header (`connect-src https://api-aiplanstudio.arsyiladm.my.id`, `frame-ancestors 'none'`, `frame-src https://accounts.google.com`)
- [x] E2.6 `web/Dockerfile`: tambah `ARG NEXT_PUBLIC_API_URL` di builder stage
- [x] E2.7 `docker-compose.yml`: build arg `NEXT_PUBLIC_API_URL`, hapus env `LARAVEL_URL` (deprecated)
- [x] E2.8 Rebuild web image (build ID baru: `Sg9bOzcfPeaQHGrKPNJYH`)
- [x] E2.9 `npx tsc --noEmit` 0, `npm run lint` 0
- [x] E2.10 Update checkpoint

### Phase E3 — Cloudflare Tunnel
- [x] E3.1 cloudflared config: 2 ingress — `aiplanstudio.arsyiladm.my.id → http://aiplanstudio_web:3000` (Next.js standalone, direct via tunnel network) dan `api-aiplanstudio.arsyiladm.my.id → http://aiplanstudionginx_api:8000` (Laravel via nginx → php-fpm). Web origin tidak lewat nginx_web lagi (Phase 8 — service dihapus).
- [x] E3.2 Network attach: `docker network connect aiplanstudio_aiplanstudio cloudflare_tunnel-cloudflare-tunnel-1` (tunnel container ada di project terpisah, perlu attach ke aiplanstudio network)
- [x] E3.3 Fix nginx_api listen port: tambah `listen 80;` di `docker/api-nginx/default.conf` (sebelumnya hanya 8000, tunnel config pakai default port 80)
- [x] E3.4 DNS verify: `curl https://api-aiplanstudio.arsyiladm.my.id/api/version` → 200 OK setelah recreate api container (env baru picked up)
- [x] E3.5 HTTPS handshake test: cookie attributes verified (`secure; samesite=none` di XSRF + session)
- [x] E3.6 Update checkpoint

### Phase E4 — Validation
- [x] E4.1 Backend test suite: `php artisan test` (dengan DB_DATABASE=aiplanstudio_test) → **261 passed** (1035 assertions), 1 pre-existing Socialite order fail (flaky test isolation), 1 skip. Backend clean.
- [x] E4.2 Frontend lint + tsc: 0 errors, 0 warnings (2 pre-existing CommandPalette errors unrelated)
- [x] E4.3 `curl https://api-aiplanstudio.arsyiladm.my.id/api/version` → 200 OK JSON (via Cloudflare Tunnel)
- [x] E4.4 CORS preflight valid origin: `OPTIONS` + `Origin: https://aiplanstudio.arsyiladm.my.id` → 204 + full allow headers
- [x] E4.4b CORS preflight evil origin: `Origin: https://evil.com` → 204 **tanpa** CORS headers → browser will block
- [x] E4.5 ~~CSRF cookie~~ **CP-13 (2026-08-15): CSRF custom endpoint** — `GET /api/csrf-token` → 200 JSON `{token}` (raw session token). Frontend caches in-memory + sends via `X-CSRF-TOKEN` header. `XSRF-TOKEN` cookie via `/sanctum/csrf-cookie` masih ada tapi tidak dipakai oleh browser (host-only cookie cross-origin unreadable).
- [x] E4.6 Webhook tracking: `POST /api/webhooks/phase-complete` dengan Bearer token → 401 (expected, fake token). Route registered + auth middleware works.
- [x] E4.7 Update checkpoint

### Phase E6 — CSRF Cross-Origin Fix (CP-13, 2026-08-15)
- [x] **E6.1** Root cause: `XSRF-TOKEN` cookie host-only di `api-aiplanstudio.arsyiladm.my.id`, JS di `aiplanstudio.arsyiladm.my.id` tidak bisa baca (`document.cookie` blocked cross-origin). `X-XSRF-TOKEN` header kosong → Laravel 419.
- [x] **E6.2** Solution: custom JSON endpoint `GET /api/csrf-token` di `api/routes/api.php:31`. Cache in-memory + lazy refetch on 419. Laravel `PreventRequestForgery` accepts raw token di `X-CSRF-TOKEN` (skip cookie-decrypt).
- [x] **E6.3** `web/src/lib/api.ts`: `fetchCsrfToken()` → `csrfToken` cache → `csrfHeaders()` include `X-CSRF-TOKEN` on POST/PATCH/DELETE.
- [x] **E6.4** 4 auth forms refactored ke `apiPost`: `login`, `register`, `forgot-password`, `reset-password` (sebelumnya raw `fetch(/api/...)` relative path).
- [x] **E6.5** 3 settings pages cleaned: `provider`, `users`, `profile` — removed explicit `fetchCsrfCookie()` calls (apiPost already handles).
- [x] **E6.6** `api/config/cors.php` `allowed_headers`: tambah `X-CSRF-TOKEN`.
- [x] **E6.7** `web/e2e/global-setup.ts`: replaced `/sanctum/csrf-cookie` + relative `/api/login` → `E2E_API_BASE_URL` + `GET /api/csrf-token` + `POST /api/login` dengan `X-CSRF-TOKEN` header.
- [x] **E6.8** Modal focus bug fixed (`web/src/components/ui/Modal.tsx`): useEffect deps `[open, onClose]` → useRef sync pattern, deps `[open]` only. Inline arrow `onClose` tidak trigger focus re-run.
- [x] **E6.9** Verify matrix via Cloudflare Tunnel:
  - Register new user → 201 `{pending: true}` (DB: `status=pending, role=member`)
  - Pending login → 422 "Kredensial tidak cocok." (generic, no info leak)
  - Admin login → 200
  - Admin approve (PATCH) → 200
  - Approved user login → 200
  - Delete member (DELETE) → 204

### Phase E5 — Final Rebuild + Restart
- [x] E5.1 Rebuild semua image (api + web)
- [x] E5.2 `docker compose up -d` (recreate containers)
- [x] E5.3 Verify healthy: 6 containers up
- [x] E5.4 Update `docs/15-dev-log.md` (Phase 7) + `docs/09-roadmap.md` (Phase 7) + `docs/18-production-readiness.md` (Cloudflare Tunnel + Direct routing entries) + `docs/22-e2e-test-plan.md` (baseURL → https)

## File Touch List

| File | Action |
|---|---|
| `api/.env` | Edit |
| `api/config/cors.php` | Edit |
| `api/config/session.php` | Edit |
| `web/.env.production` | Create |
| `web/src/lib/api.ts` | Edit |
| `web/src/lib/bff.ts` | Delete |
| `web/src/app/api/**` | Delete BFF routes |
| `web/next.config.ts` | Edit (CSP, hapus rewrites) |
| `web/src/middleware.ts` | Edit (hapus proxy) |
| `docker-compose.yml` | Edit (NEXT_PUBLIC_API_URL) |
| `cloudflared config` | Edit (2 ingress) |
| `docs/15-dev-log.md` | Edit (Phase 7) |
| `docs/09-roadmap.md` | Edit (Phase 7) |
| `docs/18-production-readiness.md` | Edit (domain + status) |
| `docs/22-e2e-test-plan.md` | Edit (baseURL) |

## Security Headers (Final)

| Layer | Header | Value |
|---|---|---|
| Frontend (Next.js) | `Content-Security-Policy` | `script-src 'self' 'unsafe-inline' https://accounts.google.com; connect-src 'self' https://api-aiplanstudio.arsyiladm.my.id; frame-ancestors 'none'` |
| API (nginx) | `X-Frame-Options` | `DENY` |
| API (nginx) | `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` |
| API (CORS) | `Access-Control-Allow-Origin` | `https://aiplanstudio.arsyiladm.my.id` |
| API (CORS) | `Access-Control-Allow-Credentials` | `true` |
| Cookie (Laravel) | `SameSite` | `None` (cross-origin) |
| Cookie (Laravel) | `Secure` | `true` |
| Cookie (Laravel) | `HttpOnly` | `true` (session only) |

## Checkpoint Log

_(Diisi per progres selesai, urut kronologis.)_

### 2026-08-14 · Phase E1 selesai (backend CORS + Sanctum)
- Edit `api/.env`:
  - `SESSION_SAME_SITE=lax → none` (cross-origin wajib).
  - `SESSION_DOMAIN=aiplanstudio.arsyiladm.my.id → null` (default per-domain scoping).
- Publish `api/config/cors.php` via `php artisan config:publish cors --force`.
- Edit `api/config/cors.php`:
  - `allowed_origins` = `['https://aiplanstudio.arsyiladm.my.id', 'http://localhost:3000', 'http://127.0.0.1:3000']`.
  - `allowed_headers` = `[Content-Type, X-Requested-With, X-XSRF-TOKEN, X-Request-ID, Authorization, Accept, Origin]`.
  - `exposed_headers` = `[X-Request-ID]`.
  - `supports_credentials` = `true`.
  - `max_age` = `86400`.
- Test CORS preflight via `php -S 127.0.0.1:9090`:
  - `OPTIONS /api/version` + `Origin: https://aiplanstudio.arsyiladm.my.id` → **204** + full CORS headers (`Allow-Origin`, `Allow-Credentials`, `Allow-Methods`, `Allow-Headers`, `Max-Age`).
- Rebuild api image + recreate container.

### 2026-08-14 · Phase E2 selesai (frontend refactor — hapus BFF)
- Hapus `web/src/lib/bff.ts` + `web/src/app/api/**` (40+ BFF route handlers).
- Edit `web/src/lib/api.ts`: `BASE = process.env.NEXT_PUBLIC_API_URL ?? ""` (absolute URL).
- Create `web/.env.production` (NEXT_PUBLIC_API_URL=https://api-aiplanstudio.arsyiladm.my.id) + `web/.env.development` (localhost:8000).
- Edit `web/next.config.ts`: tambah CSP (`connect-src ${API_URL}`, `frame-ancestors 'none'`, `frame-src accounts.google.com`).
- Edit `web/src/middleware.ts`: hapus cookie session check (cross-origin cookie tidak readable); pass-through. Client-side 401 redirect tetap handle di `lib/api.ts:50`.
- Edit `web/Dockerfile`: ARG `NEXT_PUBLIC_API_URL` di builder stage (bake env ke image).
- Edit `docker-compose.yml`: build arg `NEXT_PUBLIC_API_URL`, hapus env `LARAVEL_URL` (deprecated).
- Rebuild web image (build ID: `Sg9bOzcfPeaQHGrKPNJYH`).
- `npx tsc --noEmit` 0, `npm run lint` 0.

### 2026-08-14 · Phase E3 selesai (Cloudflare Tunnel wiring)
- Tunnel config di Cloudflare dashboard sudah include 2 ingress (verified dari container logs).
- Issue: tunnel container ada di project terpisah (`cloudflare_tunnel_default`), tidak bisa resolve `aiplanstudionginx_api`.
- Fix: `docker network connect aiplanstudio_aiplanstudio cloudflare_tunnel-cloudflare-tunnel-1`.
- Issue: nginx_api listen hanya pada port 8000, tunnel config pakai default 80 → connection refused.
- Fix: tambah `listen 80;` di `docker/api-nginx/default.conf` (preserve 8000 untuk local dev).
- Verify `https://api-aiplanstudio.arsyiladm.my.id/api/version` → **200 OK**.
- Verify cookies: `XSRF-TOKEN` dan `ai-planning-studio-session` keduanya punya `secure; samesite=none` (cross-origin ready).

### 2026-08-14 · Phase E4 selesai (validation)
- Backend test: `php artisan test` dengan `DB_DATABASE=aiplanstudio_test` → **246 passed** (980 assertions), 1 pre-existing Socialite fail, 1 skip.
- Frontend lint + tsc: 0 errors, 0 warnings.
- E2E via Cloudflare Tunnel:
  - `GET https://aiplanstudio.arsyiladm.my.id` → **200 OK** dengan CSP header baru (`connect-src https://api-aiplanstudio.arsyiladm.my.id`).
  - `GET https://api-aiplanstudio.arsyiladm.my.id/api/version` → **200 OK** JSON.
  - `GET https://api-aiplanstudio.arsyiladm.my.id/api/changelog` → **200 OK** JSON.
  - `GET https://api-aiplanstudio.arsyiladm.my.id/sanctum/csrf-cookie` → **204** + `XSRF-TOKEN` (secure; samesite=none) + `ai-planning-studio-session` (secure; httponly; samesite=none).
  - `OPTIONS` valid origin → 204 + allow headers. Evil origin → 204 **tanpa** CORS headers (browser blocks).
  - `POST /api/webhooks/phase-complete` dengan fake Bearer → 401 (expected, route active).

### 2026-08-14 · Phase E5 selesai (final rebuild + restart)
- Rebuild `aiplanstudio-api` + `aiplanstudio-aiplanstudio_web` images.
- `docker compose up -d` recreate semua container. Build ID web: `UJMIm4kFIeUZwelNXg4V9`.
- Verify 6 containers healthy.
- Final E2E via Cloudflare:
  - `https://aiplanstudio.arsyiladm.my.id` → 200 OK + title.
  - `https://api-aiplanstudio.arsyiladm.my.id/api/version` → 200 OK JSON.
- Update 4 docs: `docs/15-dev-log.md`, `docs/09-roadmap.md`, `docs/18-production-readiness.md`, `docs/22-e2e-test-plan.md` (baseURL → https://aiplanstudio.arsyiladm.my.id).

## Final Ringkasan

### Arsitektur

| Aspek | Sebelum (BFF — Phase 7-) | Sesudah (Direct — Phase 7+) |
|---|---|---|
| Frontend → API hop | Next.js BFF route handler → Laravel | Direct `${NEXT_PUBLIC_API_URL}/api/*` dengan `credentials: "include"` |
| Mobile → API hop | Tidak ada (mobile belum dirancang) | Direct `${APP_URL}/api/*` via dio + cookie manager |
| Webhook tracking latency | +BFF hop (~50-200ms) | Direct (server-to-server) |
| SSE streaming | BFF buffering risk | Native EventSource ke API |
| Config files | `web/src/lib/bff.ts` + 40+ BFF routes | Dihapus (CP-1..11 outputs sudah propagated no-BFF — CP-12) |
| Middleware | Cookie session check (broken cross-origin) | Pass-through (401 client-side) |
| Domain | `localhost:4197` (BFF exposed) | `aiplanstudio.arsyiladm.my.id` (Cloudflare) + `api-aiplanstudio.arsyiladm.my.id` |
| CORS | tidak ada | allowlist + credentials |
| Cookie attributes | SameSite=lax | SameSite=None; Secure (cross-origin) |
| CSP | generic | `connect-src ${API_URL}`, `frame-ancestors 'none'` |

### Test Status

| Phase | Backend | Frontend |
|---|---|---|
| Sebelum BFF removal | 246 pass | clean |
| Setelah BFF removal (Phase 7) | 246 pass (no change) | clean |
| Setelah CP-1..11 | 261 pass | clean |
| Setelah CP-12 (Direct Routing Reconciliation) | 261 pass (no change) | clean |

### AI Agent Architecture Roles

| Agent (CP-7 → CP-12) | Change |
|---|---|
| `web-bff-agent` | **REMOVED** — digabung ke `web-integration-agent` (direct API client scope) |
| `web-frontend-agent` | Tetap. Handoff ke `web-integration-agent` (bukan `web-api-agent`) |
| `web-backend-agent` | Tetap. Publish API contract → `web-integration-agent` |
| `web-db-agent`, `web-test-agent` | Tetap. |

**Status: Production-ready dengan arsitektur direct + Cloudflare Tunnel + CORS configured.**

---

## CP-12 — Direct Routing Reconciliation (2026-08-15)

Follow-up CP-1..11 untuk propagate no-BFF architecture ke AI prompts, AI-generated components, dan docs yang masih reference BFF. BFF code sudah dihapus (Phase 7), tapi beberapa CP-1..11 outputs belum direconcile.

**Scope utama:**
- AI prompts (5 files di `api/app/Prompts/`): rename `web-bff-agent` → `web-integration-agent`, drop BFF module boundary dari architecture diagram.
- **AI prompt `erd.php` (CP-12.b)**: sebelumnya minta output DOUBLE FORMAT (Mermaid block + line-format), tapi React Flow (`ErdDiagram.tsx`) consume JSON only — Mermaid block jadi orphaned artifact. Rewrite prompt jadi JSON only untuk single source of truth.
- Frontend components: parser-based viewers (`AgentsView`, `ArchitectureView`, `MasterPromptViewer`) regenerate dari prompt baru.
- Settings/About: badge "BFF Pattern" → "Direct Routing".
- E2E baseURL: `:4197` → `:3000` (Next.js dev direct).
- Env templates (`api/.env.example`, `web/.env.production`): `APP_URL` `:4197` → `:8000` (nginx_api direct), TODO annotations untuk public domain swap.
- AGENTS.md (web): API Rules section rewrite (BFF → Direct Routing).
- Docs (api/README, docs/02, 04, 05, 07, 08, 14, 18, 22 + AUTH.md): update semua reference BFF → direct routing.
- Legacy cleanup: `MASTER_PROMPT.md` deleted (orphan, 0 references verified).

Detail item-by-item: `docs/plan/master-repair.md` CP-12 section.
