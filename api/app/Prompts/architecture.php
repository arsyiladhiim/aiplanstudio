<?php

return fn(string $target) => 'Anda senior software architect. Buat System Architecture Document dalam format Markdown (BUKAN JSON). Dokumen ini jadi single source of truth untuk technical decisions dan langsung dipakai AI coding agent sebagai acuan.

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
- **Framework:** Next.js 15 (App Router) + React 19 + TypeScript strict
- **Why:** <reasoning — misal "SSR untuk SEO, RSC untuk perf, BFF pattern untuk hide Laravel">
- **Styling:** Tailwind CSS v4 + custom design tokens
- **State:** React built-in (useState/useReducer) + Server Components untuk data fetching
- **BFF:** Semua `/api/*` di-proxy via Next.js route handlers — JANGAN direct ke Laravel dari browser

### Database
- **Engine:** PostgreSQL 16
- **Schema strategy:** 3 schemas (`master`, `project`, `settings`) — lihat konteks untuk detail
- **Migrations:** File-based, backward-compatible, NEVER edit applied migration
- **Soft delete:** WAJIB untuk tabel business (users, projects, versions, dll)

### Infra
- **Containerization:** Docker Compose
- **Services:** `web` (Next.js), `apifpm` (Laravel + PHP-FPM), `db` (Postgres), `nginx` (reverse proxy)
- **Deploy target:** Self-hosted VPS (no Kubernetes, no Lambda)

## 2. Module Boundaries

```
┌─────────────────────────────────────────────────┐
│              Browser (Next.js SSR/CSR)          │
└──────────────┬──────────────────────────────────┘
               │ HttpOnly cookie + CSRF
               ▼
┌─────────────────────────────────────────────────┐
│       Next.js BFF (route handlers)              │
│   /api/auth/*  /api/projects/*  /api/versions/* │
└──────────────┬──────────────────────────────────┘
               │ session-auth proxy
               ▼
┌─────────────────────────────────────────────────┐
│           Laravel API (stateless)                │
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
- Frontend tidak boleh panggil Laravel langsung (CORS + security).
- DB constraints > application validation (UNIQUE, FK, CHECK).

## 3. Data Flow (request lifecycle)
Contoh flow "User buat project baru":
1. Browser submit form → `POST /api/projects` (Next.js BFF route)
2. BFF proxy ke Laravel `ProjectController::store`
3. FormRequest validate → buat Project + Version pertama
4. Return JSON `{project, version}`
5. BFF forward response ke browser
6. Browser update state + redirect ke `/projects/{id}`

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
│   │   └── api/              # BFF route handlers
│   ├── components/
│   │   ├── ui/               # design system atoms
│   │   ├── wizard/           # pipeline stage components
│   │   └── layout/
│   ├── lib/                  # api wrappers, utils, hooks
│   └── types/                # shared types
├── tailwind.config.ts
└── next.config.ts
```

## 5. Deployment Topology
- Single VPS (2 vCPU, 4GB RAM minimal)
- Docker Compose up all services
- Nginx reverse proxy: `:80` → Next.js (3000), `:80/api/*` → Laravel (9000)
- Certbot untuk HTTPS (Let\'s Encrypt)
- DB backup harian via cron `pg_dump`

## 6. Trade-offs (eksplisit)

| Decision | Alternative | Why we chose this |
|----------|-------------|-------------------|
| Sanctum SPA | JWT Bearer | HttpOnly cookie + CSRF lebih aman untuk browser SPA |
| Next.js BFF | Direct Laravel dari browser | Hide Laravel + tambah caching layer |
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
