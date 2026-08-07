# 02 — Architecture

> Lihat juga: [07-docker-setup](07-docker-setup.md) · [04-api-contract](04-api-contract.md) · [10-decision-log](10-decision-log.md)

## Ringkasan
- **Backend:** Laravel (REST API + orkestrasi AI pipeline).
- **Frontend:** Next.js (App Router, SPA client).
- **DB:** PostgreSQL dengan 3 schema (`aiplanstudio_master`, `aiplanstudio_project`, `aiplanstudio_settings`).
- **Reverse proxy:** Nginx — satu-satunya service yang expose port ke host.
- **BFF Pattern:** Semua traffic masuk via nginx → Next.js. Next.js proxy `/api/*` ke Laravel internal (`http://api:8000`). Tidak ada route langsung nginx ke Laravel.

## Topologi (BFF Pattern)
```
                    host :4197
                       │
                   ┌───▼────┐
                   │ nginx  │   (satu-satunya port: 4197:80)
                   └──┬───┬──┘
           location /  │   │  location /api, /sanctum (ke web:3000)
                       │
               ┌───────▼┐
               │  web   │   Next.js BFF (port 3000 internal, standalone)
               └────┬───┘
                    │ proxy /api/* → http://api:8000
                ┌────▼─────────┐
                │     api      │   nginx front (port 8000 internal) → php-fpm
                └──┬────┬──────┘
             ┌─────▼┐ ┌─▼──────┐
             │  db  │ │ redis  │
             │  pg  │ └────────┘
             └──────┘
                   (tanpa ports host)
```

## Routing Nginx
| Path | Tujuan |
|------|--------|
| `/` | `web:3000` (Next.js) |
| `/_next/*` | `web:3000` (static assets) |
| `/*` (semua) | `web:3000` (BFF handles routing) |

Semua `/api/*`, `/sanctum/*` masuk ke Next.js → Next.js proxy ke Laravel internal.

## Service
| Service | Image/Base | Expose | Publish ke host |
|---------|-----------|--------|-----------------|
| `nginx` | nginx:alpine | 80 | **4197:80 (satu-satunya)** |
| `web` | node:20-alpine (Next.js) | 3000 (internal) | tidak |
| `api` | nginx:alpine (front Laravel) | 8000 (internal) | tidak |
| `api-fpm` | php:8.3-fpm-alpine (`php-fpm -F`) | 9000 (internal) | tidak |
| `db` | postgres:16-alpine | 5432 (internal) | **tidak** |
| `redis` | redis:alpine | 6379 (internal) | tidak |
| `migrate` | one-shot (sama image dengan api-fpm) | — | tidak |
| `glitchtip` | glitchtip/glitchtip:6 | 8000 (internal) | tidak |

> **Catatan serve:** API berjalan di **php-fpm (production-ready)** — `api-fpm` (`php:8.3-fpm-alpine`, `CMD ["php-fpm","-F"]`, expose 9000) di-fronting nginx service `api` (listen 8000 → `fastcgi_pass api-fpm:9000`). Bukan lagi `php artisan serve` (RS-9 ✅).

> **Error monitoring:** GlitchTip (Sentry-compatible) self-hosted reuse `db` (PostgreSQL DB `glitchtip`) + `redis` (DB index 2). SDK `sentry/sentry-laravel` (backend) + `@sentry/nextjs` (frontend) → DSN internal (P8 ✅).

## Jaringan
- Satu Docker network internal (`aistack`).
- Referensi antar-service pakai hostname = nama service: `db`, `api`, `web`, `redis`.
- Contoh Laravel `.env`: `DB_HOST=db`, `REDIS_HOST=redis`.
- Contoh nginx: `proxy_pass http://web:3000;` (BFF — semua melalui Next.js).

## Aliran Request Utama (BFF)
1. Browser → `http://localhost:4197/` → nginx → `web` (render UI).
2. Frontend fetch `http://localhost:4197/api/...` (dengan cookie session + CSRF header) → nginx → `web` (BFF) → `api:8000` (Laravel).
3. Pipeline AI: frontend POST ke BFF `/api/generate/stream` → Next.js proxy GET ke Laravel → AI Provider streaming → relay token & status per stage ke frontend realtime via SSE.

## Pipeline AI (7 Stages)
Pipeline `PipelineRunner` menjalankan 7 stage secara berurutan:

```
pertanyaan → analisa → prd → architecture → erd → phased_master → phased_master_mobile
```
- `pertanyaan`: generate 5-7 pertanyaan klarifikasi (output via SSE, tidak di-persist ke DB; jawaban disimpan ke `answers`)
- `analisa`: analisa kebutuhan dari ide + jawaban
- `prd`: Product Requirements Document
- `architecture`: arsitektur & tech stack (target-aware)
- `erd`: ERD diagram + API contract (JSON, `parseErdText()` mengekstrak `api_contract`)
- `phased_master`: phases breakdown + master prompt + standards + agents
- `phased_master_mobile`: phases + master prompt untuk mobile (**hanya** jika target=`both`)

Setiap stage streaming via SSE. Stage berikutnya mendapat konteks dari output stage sebelumnya. Lihat [05-wizard-flow](05-wizard-flow.md) dan [06-ai-pipeline](06-ai-pipeline.md).

## Fitur Kunci
### Dashboard Analytics (`GET /api/dashboard/stats`)
- Server-computed stats: total projects, versions, active projects, weekly counts, recent projects, favorite projects, recent activities.
- BFF route: `web/src/app/api/dashboard/stats/route.ts`.
- Frontend: `web/src/app/(app)/dashboard/page.tsx`.

### Activity Log
- Setiap aksi bermakna (create version, delete version, toggle phase, update artifact) dicatat via `Project::logActivity()`.
- Tabel `activities` dengan `project_id`, `version_id`, `user_id`, `action`, `description`, `metadata`.
- Bisa dilihat per-project atau global (admin).
- Frontend tab di project detail page.

### Inline Artifact Editing (`PATCH /api/versions/{id}/artifacts`)
- Wizard page allows editing any completed artifact inline via textarea.
- Stage key → DB column mapping: `pertanyaan`→(none), `analisa`→analysis, `prd`→prd, `architecture`→architecture, `erd`→erd, `phased_master`→master_prompt, `phased_master_mobile`→mobile_master_prompt.
- ERD content JSON-decoded before storage.

### Version Diff (`GET /api/versions/{id}/diff?compare={otherId}`)
- Compares all 6 artifact fields (analysis, prd, architecture, erd, master_prompt, mobile_master_prompt) between two versions.
- Returns structured diff with `changed` boolean per field + character-level diff.
- Frontend: side-by-side diff view at `web/src/app/(app)/projects/[id]/diff/page.tsx`.

### Project API Tokens
- Three BFF routes under `/api/projects/{id}/tokens`: GET (list), POST (create—shows once), DELETE (revoke).
- UI embedded as collapsible card in project detail page.
- Tokens authenticate webhook callbacks (`POST /api/webhooks/phase-complete`) via middleware `auth.project-token`.

### Favorites & Search
- `PATCH /api/projects/{id}/favorite` toggle.
- `GET /api/projects?q=&favorite=` untuk search dan filter.

## Keamanan Arsitektur
- Auth: **Sanctum SPA Session** — HttpOnly session cookie + CSRF (`XSRF-TOKEN`).
- CSRF aktif: state-changing requests wajib menyertakan `X-XSRF-TOKEN` header.
- Session lifetime: **120 menit** (konfigurasi di `config/session.php`).
- User approval: user non-pertama dibuat `status: pending`, harus di-approve admin sebelum login.
- API key AI Provider disimpan **encrypted di DB**, hanya dipakai backend. Tak pernah dikirim ke client.
- DB/redis/api/web tidak dapat diakses dari host langsung — hanya lewat nginx.
- Nginx menambahkan header keamanan: CSP, HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy.
- SSRF protection: `AiClient::validateBaseUrl()` block internal IPs, allow Docker container names.

Detail deployment & perintah: [07-docker-setup](07-docker-setup.md).
