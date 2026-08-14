# 02 — Architecture

> Lihat juga: [07-docker-setup](07-docker-setup.md) · [04-api-contract](04-api-contract.md) · [10-decision-log](10-decision-log.md)

## Ringkasan
- **Backend:** Laravel (REST API + orkestrasi AI pipeline).
- **Frontend:** Next.js (App Router, SPA client).
- **DB:** PostgreSQL dengan 3 schema (`aiplanstudio_master`, `aiplanstudio_project`, `aiplanstudio_settings`).
- **Reverse proxy:** Cloudflare Tunnel (`cloudflared`) — external reverse proxy, tidak ada service di repo yang publish port ke host.
- **Direct routing:** Cloudflare Tunnel routing langsung ke `aiplanstudio_web` (web origin) dan `aiplanstudionginx_api` (API origin). Frontend call API cross-origin dengan Sanctum session cookie + CSRF.

## Topologi (Direct Tunnel Routing)
```
                  Cloudflare Tunnel (external)
                  aiplanstudio.arsyiladm.my.id    api-aiplanstudio.arsyiladm.my.id
                            │                            │
                            │ HTTP                       │ HTTP
                            ▼                            ▼
                   ┌─────────────────┐         ┌─────────────────────────┐
                   │ aiplanstudio_web │         │ aiplanstudionginx_api    │
                   │  Next.js (3000)  │         │ nginx → php-fpm (8000)  │
                   └────────┬─────────┘         └────────┬─────────────────┘
                            │                           │
                  ┌─────────┴─────────┐         ┌───────┴────────┐
                  ▼                   ▼         ▼                ▼
         ┌────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌──────────────────┐
         │ aiplanstudio_db │ │ aiplanstudio_redis │ │ aiplanstudio_apifpm │ │ (no host ports) │
         └────────────────┘ └─────────────────┘ └─────────────────┘ └──────────────────┘
```

## Routing Cloudflare Tunnel
| Hostname | Tujuan |
|----------|--------|
| `aiplanstudio.arsyiladm.my.id` | `aiplanstudio_web:3000` (Next.js standalone) |
| `api-aiplanstudio.arsyiladm.my.id` | `aiplanstudionginx_api:8000` (Laravel via nginx → php-fpm) |

Frontend call API langsung ke `api-aiplanstudio.arsyiladm.my.id` (cross-origin, cookie `SameSite=None; Secure`).

## Service
| Service | Image/Base | Expose | Publish ke host |
|---------|-----------|--------|-----------------|
| `aiplanstudio_web` | node:20-alpine (Next.js standalone) | 3000 (internal) | tidak |
| `aiplanstudionginx_api` | nginx:alpine (front Laravel) | 8000 (internal) | tidak |
| `aiplanstudio_apifpm` | php:8.3-fpm-alpine (`php-fpm -F`) | 9000 (internal) | tidak |
| `aiplanstudio_db` | postgres:16-alpine | 5432 (internal) | tidak |
| `aiplanstudio_redis` | redis:alpine | 6379 (internal) | tidak |
| `migrate` | one-shot (sama image dengan api-fpm) | — | tidak |
| `glitchtip` | glitchtip/glitchtip:6 — **DISABLED** (service di-comment) | 8000 (internal) | tidak |

> **Catatan serve:** API berjalan di **php-fpm (production-ready)** — `aiplanstudio_apifpm` (`php:8.3-fpm-alpine`, `CMD ["php-fpm","-F"]`, expose 9000) di-fronting nginx service `aiplanstudionginx_api` (listen 8000 → `fastcgi_pass api-fpm:9000`). Bukan lagi `php artisan serve` (RS-9 ✅).

> **Error monitoring:** GlitchTip (Sentry-compatible) — **DISABLED saat ini** (service di-comment, DSN dikosongkan, route nginx di-comment). SDK `sentry/sentry-laravel` (backend) + `@sentry/nextjs` (frontend) dipertahankan sebagai no-op; aktifkan kembali bila dibutuhkan.

## Jaringan
- Docker network internal `aiplanstudio` (bridge) + external `cloudflare_tunnel_default` (attach container tunnel).
- Referensi antar-service pakai hostname = nama service: `aiplanstudio_db`, `aiplanstudionginx_api`, `aiplanstudio_web`, `aiplanstudio_redis`.
- Contoh Laravel `.env`: `BOOT_HOST=aiplanstudio_db`, `REDIS_HOST=aiplanstudio_redis`.
- Tunnel container (`cloudflare_tunnel-cloudflare-tunnel-1`, di project terpisah) di-attach ke `aiplanstudio_aiplanstudio` network untuk resolve `aiplanstudio_web` + `aiplanstudionginx_api`.

## Aliran Request Utama (Direct Tunnel)
1. Browser → `https://aiplanstudio.arsyiladm.my.id/` → Cloudflare Tunnel → `aiplanstudio_web:3000` (render UI standalone).
2. Browser → `https://api-aiplanstudio.arsyiladm.my.id/api/...` (dengan cookie session + CSRF header) → Cloudflare Tunnel → `aiplanstudionginx_api:8000` → `aiplanstudio_apifpm:9000` (Laravel).
3. Pipeline AI: frontend POST ke `https://api-aiplanstudio.arsyiladm.my.id/api/generate/stream` → nginx → Laravel → AI Provider streaming → relay token & status per stage ke frontend realtime via SSE.

## Pipeline AI (14 Stages)
Pipeline `PipelineRunner` menjalankan 14 stage (target `both`) / 10 stage (target `web`) secara berurutan:

```
INTI (1-6): pertanyaan → analisa → prd → architecture → erd → api_contract
WEB TRACK (7-9): phases_web → standards_web → master_web
MOBILE TRACK (10-13, hanya both): pertanyaan_mobile → phases_mobile → standards_mobile → master_mobile
AGENTS (14): agents
```
- `pertanyaan`: MCQ klarifikasi (output → `pertanyaan`, jawaban → `answers`)
- `analisa`: analisa kebutuhan dari ide + jawaban
- `prd`: Product Requirements Document
- `architecture`: arsitektur & tech stack (target-aware)
- `erd`: ERD diagram (JSON, `parseErdText()` mengekstrak `api_contract`)
- `api_contract`: API contract JSON (array endpoint)
- `phases_web` → `phases` (jsonb, breakdown fase web)
- `standards_web` → `standards` (STANDARDS.md web)
- `master_web` → `master_prompt` (master prompt self-contained web + auto token tracking)
- `pertanyaan_mobile` → `pertanyaan_mobile` + `mobile_answers` (MCQ mobile)
- `phases_mobile` → `mobile_phases` (jsonb)
- `standards_mobile` → `mobile_standards` (STANDARDS.md mobile)
- `master_mobile` → `mobile_master_prompt`
- `agents` → `agents` (AGENTS.md, setelah semua track selesai)

**Gate:** Mobile track (stage 10-13) hanya berjalan jika `master_web` done. Target `web` → stage 1-10. Setelah stage besar selesai, wizard meminta **konfirmasi** bila tracking fase web belum selesai.

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
- Stage key → DB column mapping: `analisa`→analysis, `prd`→prd, `architecture`→architecture, `erd`→erd, `api_contract`→api_contract, `standards_web`→standards, `phases_web`→phases, `master_web`→master_prompt, `pertanyaan_mobile`→pertanyaan_mobile, `phases_mobile`→mobile_phases, `standards_mobile`→mobile_standards, `master_mobile`→mobile_master_prompt, `agents`→agents.
- ERD content JSON-decoded before storage.

### Version Diff (`GET /api/versions/{id}/diff?compare={otherId}`)
- Compares all 10+ artifact fields (analysis, prd, architecture, erd, standards, agents, phases, master_prompt, mobile_phases, mobile_standards, mobile_agents, mobile_master_prompt) between two versions.
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
