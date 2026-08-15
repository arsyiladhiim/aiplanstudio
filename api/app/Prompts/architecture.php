<?php

return fn (string $target) => 'Anda senior software architect. Buat System Architecture Document dalam format Markdown (BUKAN JSON). Dokumen ini jadi single source of truth untuk technical decisions dan langsung dipakai AI coding agent sebagai acuan.

# Architecture: <NAMA_PROYEK>

## 1. Stack (with reasoning)

### Backend
- **Framework:** Laravel 11 (PHP 8.4)
- **Why:** <1-2 kalimat reasoning — misal "PHP familiar untuk tim, ekosistem Sanctum untuk SPA auth">
- **Auth:** Sanctum SPA Session (HttpOnly cookie + CSRF)
- **API style:** REST + JSON response
- **Validation:** FormRequest classes
- **Test:** PHPUnit + FeatureTest

### Frontend
- **Framework:** Next.js (App Router) + React 19 + TypeScript strict
- **Why:** <reasoning — misal "SSR untuk SEO, RSC untuk perf, direct API call untuk minimal latency">
- **Styling:** Tailwind CSS v4 + custom design tokens
- **State:** React built-in (useState/useReducer) + Server Components untuk data fetching
- **API call:** Browser fetch DIRECT ke Laravel via `NEXT_PUBLIC_API_URL` dengan `credentials: "include"`. CORS allowlist + Sanctum stateful domain di backend. NO BFF layer (see docs/25-bypass-bff.md).

### Database
- **Engine:** PostgreSQL 16
- **Schema strategy:** 3 schemas (`master`, `project`, `settings`) — lihat konteks untuk detail
- **Migrations:** File-based, backward-compatible, NEVER edit applied migration
- **Soft delete:** WAJIB untuk tabel business (users, projects, versions, dll)

### Infra
- **Containerization:** Docker Compose
- **Services:** `aiplanstudio_web` (Next.js standalone), `aiplanstudionginx_api` (nginx front Laravel), `aiplanstudio_apifpm` (Laravel + PHP-FPM), `aiplanstudio_db` (Postgres), `aiplanstudio_redis` (Redis). Tidak ada nginx host — Cloudflare Tunnel jadi reverse proxy eksternal.
- **Reverse proxy:** Cloudflare Tunnel (external container `cloudflare_tunnel_default` network). 2 ingress: web origin + API origin.
- **Deploy target:** Self-hosted VPS (no Kubernetes, no Lambda)

## 2. Module Boundaries

```
┌─────────────────────────────────────────────────┐
│              Browser (Next.js SSR/CSR)          │
└──────────────┬──────────────────────────────────┘
               │ HttpOnly cookie + CSRF
               │ (cross-origin: SameSite=None; Secure)
               ▼
┌─────────────────────────────────────────────────┐
│   Laravel API (stateless, direct call)          │
│   Controllers → Services → Repositories → Models│
└──────────────┬──────────────────────────────────┘
               │ Eloquent
               ▼
┌─────────────────────────────────────────────────┐
│   PostgreSQL (aiplanstudio_master/_project/_settings)│
└─────────────────────────────────────────────────┘
```

**Prinsip:**
- Backend stateless — tidak ada session storage di Laravel selain Sanctum session di cookie.
- Browser call Laravel direct via `NEXT_PUBLIC_API_URL` + `credentials: "include"` (CORS configured).
- DB constraints > application validation (UNIQUE, FK, CHECK).
- No BFF hop — minimal latency, native EventSource untuk SSE.

## 3. Data Flow (request lifecycle)
Contoh flow "User buat project baru":
1. Browser submit form → `POST ${NEXT_PUBLIC_API_URL}/api/projects` (direct, `credentials: "include"`)
2. Sanctum middleware validate session cookie (stateful domain match)
3. Laravel `ProjectController::store` → FormRequest validate → buat Project + Version pertama
4. Return JSON `{project, version}`
5. Browser update state + redirect ke `/projects/{id}`

## 4. Folder Structure (high-level)
```
api/
├── app/
│   ├── Http/Controllers/
│   ├── Http/Requests/        # FormRequest
│   ├── Http/Resources/       # JSON resources
│   ├── Services/             # Business logic
│   ├── Policies/             # Authorization
│   ├── Models/
│   └── Prompts/              # AI prompt templates
├── database/migrations/
├── database/seeders/
├── routes/api.php
└── tests/Feature/

web/
├── src/
│   ├── app/
│   │   ├── (auth)/           # login, register
│   │   ├── (app)/            # dashboard, projects, new, settings
│   │   └── globals.css       # Tailwind v4 + design tokens
│   ├── components/
│   │   ├── ui/               # design system atoms
│   │   ├── wizard/           # pipeline stage components
│   │   └── layout/
│   ├── lib/                  # api.ts (direct client), utils, hooks
│   └── types/                # shared types
├── tailwind.config.ts
└── next.config.ts
```

## 5. Deployment Topology
- Single VPS (2 vCPU, 4GB RAM minimal)
- Docker Compose up all services
- **Cloudflare Tunnel** (external reverse proxy) — 2 ingress: `aiplanstudio.arsyiladm.my.id → http://aiplanstudio_web:3000` (Next.js), `api-aiplanstudio.arsyiladm.my.id → http://aiplanstudionginx_api:8000` (Laravel via nginx → php-fpm)
- TLS termination di Cloudflare; nginx di belakang tunnel tetap kasih defense-in-depth headers (CSP, HSTS, X-Frame-Options, Permissions-Policy)
- DB backup harian via cron `pg_dump`

## 6. Trade-offs (eksplisit)

| Decision | Alternative | Why we chose this |
|----------|-------------|-------------------|
| Sanctum SPA | JWT Bearer | HttpOnly cookie + CSRF lebih aman untuk browser SPA |
| Direct routing (no BFF) | Next.js BFF proxy | Minimal latency + native SSE EventSource + simpler failure mode |
| Single VPS | Kubernetes | Overkill untuk v1, biaya rendah |
| PostgreSQL | MySQL | JSONB support untuk versioning + better concurrency |

' . platformSuffix($target) . PHP_EOL . '

[ATURAN]
- Hindari vendor lock-in explanation kecuali ditanya eksplisit.
- Setiap keputusan teknis WAJIB ada reasoning (bukan "best practice").
- Folder structure WAJIB match dengan struktur yang dipakai di codebase (lihat konteks).

[OUTPUT INSTRUCTIONS]
- Jawab HANYA dengan architecture di atas.
- WAJIB semua 6 section terisi.
- JANGAN basa-basi.

VERIFY: Apakah setiap stack punya reasoning? Apakah module boundary jelas dengan ASCII diagram? Apakah trade-off eksplisit?';
