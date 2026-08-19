<?php

$flutterAgents = '
# AGENTS.md — Mobile AI Coding Rules

## Project Context
- Target Platform: Mobile Android (APK) — Flutter
- Tech Stack: Flutter, Dart (null safety), Riverpod, GoRouter, Material Design 3
- Local DB: drift/sqflite
- Backend API: REST JSON + session cookie via dio_cookie_manager

## Agent Roles

### 🎨 flutter-ui-agent (Frontend)
- **Scope:** Widget tree, screen layout, navigation, theming, animations
- **Owns:** `lib/features/**/presentation/`, `lib/core/theme/`, `lib/shared/widgets/`
- **Tools:** Context7 (Flutter docs), web_search
- **Constraint:** Pakai MD3, JANGAN custom color hardcoded, pakai design tokens
- **Handoff:** Setelah screen jadi, lempar ke `flutter-data-agent` untuk wire ke API

### 🔌 flutter-data-agent (Data Layer)
- **Scope:** Repository, DTOs, JSON parsing, local DB, HTTP client
- **Owns:** `lib/features/**/data/`, `lib/features/**/domain/`, `lib/core/api/`
- **Tools:** Context7 (drift/dio docs)
- **Constraint:** Pakai freezed + json_serializable, JANGAN manual JSON parsing
- **Handoff:** Setelah contract siap, lempar ke `flutter-ui-agent` untuk consume

### 🧪 flutter-test-agent (QA)
- **Scope:** Unit test, widget test, integration test
- **Owns:** `test/`, `integration_test/`
- **Tools:** mocktail, flutter_test, integration_test
- **Constraint:** Coverage 70% domain/, 50% presentation/
- **Handoff:** Test failure → route balik ke owning agent

## Hard Rules (WAJIB untuk SEMUA agent)
1. **WAJIB baca STANDARDS.md** sebelum nulis kode
2. **JANGAN hapus/rename file existing** tanpa konfirmasi user
3. **JANGAN tambah dependency** baru kalau ada stdlib/library existing
4. **Commit WAJIB per logical unit** dengan Conventional Commits
5. **JANGAN asumsi** — kalau tidak yakin, TANYA user
6. **Pakai feature-first structure** — JANGAN campur features dalam 1 folder
7. **Pakai freezed** untuk semua data class
8. **Pakai Riverpod** untuk semua state management (JANGAN setState untuk business logic)
9. **Run `flutter analyze` + `flutter test` sebelum commit**

## File Structure (WAJIB)
```
lib/
├── main.dart
├── app.dart                      # MaterialApp.router + MD3 theme
├── core/
│   ├── api/                      # dio, endpoints, interceptors
│   ├── theme/                    # MD3 ColorScheme + typography
│   ├── router/                   # GoRouter config + guards
│   └── utils/                    # helpers, formatters, validators
├── features/
│   └── <feature>/
│       ├── data/                 # repositories, sources, DTOs
│       ├── domain/               # entities, use cases
│       └── presentation/         # screens, widgets, controllers
└── shared/
    ├── widgets/
    └── models/
```

## Environment
- Dev: `flutter run -d <device>` 
- Build APK: `flutter build apk --release`
- Build AAB: `flutter build appbundle --release`
- Flutter SDK >= 3.24, Dart >= 3.5, Android SDK 34+, Java 17+

## Commands
- `flutter pub get` — install dependencies
- `flutter analyze` — static analysis
- `flutter test` — unit + widget tests
- `dart format .` — format code

## Tools Available
- **Context7:** Fetch library docs (Flutter, Riverpod, drift, dll). WAJIB pakai sebelum pakai library baru.
- **web_search:** Cari best practice + pattern terbaru
';

$webAgents = '
# AGENTS.md — Web AI Coding Rules

## Project Context
- Target Platform: Web App
- Tech Stack: Laravel 13 (PHP 8.3) + Next.js (App Router, React 19) + TypeScript + Tailwind CSS v4 + PostgreSQL 16
- Auth: Sanctum SPA Session (HttpOnly cookie + CSRF, BUKAN Bearer token di browser)
- API call: Browser fetch direct ke Laravel via `NEXT_PUBLIC_API_URL` (no BFF layer — see docs/25-bypass-bff.md)

## Agent Roles

### 🎨 web-frontend-agent (UI/UX)
- **Scope:** Pages, components, layouts, theming, animations, client-side state
- **Owns:** `web/src/app/(app)/`, `web/src/components/`, `web/src/lib/hooks/`
- **Tools:** Context7 (Next.js/React/Tailwind docs), web_search
- **Constraint:** Server Components default, "use client" hanya kalau perlu interactivity. JANGAN hardcoded color — pakai design tokens. JANGAN setState di useEffect (React 19 Compiler rules).
- **Handoff:** Setelah UI ready, lempar ke `web-integration-agent` untuk define API client contract

### ⚙️ web-backend-agent (Laravel API)
- **Scope:** Controllers, FormRequest, Service, Repository, Migration, Model, Policy
- **Owns:** `api/app/Http/`, `api/app/Services/`, `api/app/Models/`, `api/database/migrations/`, `api/routes/api.php`
- **Tools:** Context7 (Laravel/Sanctum docs)
- **Constraint:** Type hints WAJIB. FormRequest untuk validasi. Policy untuk authorization. Migration forward-only. Pint formatting.
- **Handoff:** Setelah endpoint ready, update `routes/api.php` + publish API contract ke `docs/api-contract.json` untuk consume dari `web-integration-agent`

### 🔌 web-integration-agent (Direct API Client)
- **Scope:** `web/src/lib/api.ts` + `web/src/hooks/useAuth.ts` + `web/src/components/SetupTrackingCard.tsx` — direct API client dengan Sanctum cookie session
- **Owns:** Direct fetch wrappers (`apiGet/apiPost/apiPatch/apiDelete`), CSRF cookie handling, error mapping (401 → redirect /login), retry logic, SSE streaming helpers
- **Tools:** Context7 (Next.js fetch docs)
- **Constraint:** Browser call Laravel DIRECT via `process.env.NEXT_PUBLIC_API_URL`. Set `credentials: "include"` untuk cookie session. Selalu panggil `fetchCsrfCookie()` sebelum state-changing request. Handle CORS preflight kalau cross-origin.
- **Handoff:** Integration layer ready → consume dari `web-frontend-agent`

### 🗄️ web-db-agent (Schema + Migration)
- **Scope:** Migration files, schema design, indexes, FK constraints
- **Owns:** `api/database/migrations/`
- **Tools:** Context7 (PostgreSQL docs)
- **Constraint:** snake_case naming, timestamps WAJIB, soft delete untuk tabel business, FK dengan cascade sesuai lifecycle. JANGAN edit applied migration — buat migration baru.
- **Handoff:** Migration applied → consume dari `web-backend-agent`

### 🧪 web-test-agent (QA)
- **Scope:** PHPUnit FeatureTest (backend), Playwright e2e (frontend)
- **Owns:** `api/tests/Feature/`, `web/e2e/`
- **Tools:** PHPUnit, Playwright
- **Constraint:** Coverage 60% backend, 40% frontend. Test naming `test_<action>_<expected>`.
- **Handoff:** Failure → route balik ke owning agent

## Hard Rules (WAJIB untuk SEMUA agent)
1. **WAJIB baca STANDARDS.md** sebelum nulis kode
2. **JANGAN hapus/rename file existing** tanpa konfirmasi user
3. **JANGAN tambah dependency** baru kalau ada stdlib/library existing
4. **Commit WAJIB per logical unit** dengan Conventional Commits (`feat:`, `fix:`, `chore:`)
5. **JANGAN asumsi** — kalau tidak yakin, TANYA user
6. **Format:** PHP pakai Pint, TS/JS pakai Prettier
7. **Pre-commit check:** `php artisan test` + `npm run lint` + `npx tsc --noEmit` harus pass
8. **API call:** Browser boleh panggil Laravel DIRECT via `NEXT_PUBLIC_API_URL` dengan `credentials: "include"` (Sanctum cookie session aman, CORS configured di `api/config/cors.php`)
9. **CSRF WAJIB aktif** untuk semua POST/PATCH/DELETE dari browser (panggil `fetchCsrfCookie()` dulu)
10. **JANGAN pakai Bearer token di browser** — pakai session cookie (HttpOnly + SameSite=None; Secure untuk cross-origin)

## File Structure (WAJIB)

### Backend
```
api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/        # FormRequest
│   │   ├── Resources/       # API Resource
│   │   └── Middleware/
│   ├── Services/            # Business logic
│   ├── Policies/            # Authorization
│   ├── Models/
│   └── Prompts/             # AI prompt templates
├── database/migrations/
├── routes/api.php
└── tests/Feature/
```

### Frontend
```
web/
├── src/
│   ├── app/
│   │   ├── (auth)/          # login, register
│   │   └── (app)/           # dashboard, projects, new, settings
│   ├── components/
│   │   ├── ui/              # design system
│   │   ├── wizard/          # pipeline components
│   │   └── layout/
│   ├── lib/                 # api.ts (direct client), hooks, utils
│   └── types/
```

## Environment
- Dev (full stack): `docker compose up`
- Dev (BE only): `php artisan serve` di port 8000
- Dev (FE only): `npm run dev` di port 3000
- Database: PostgreSQL 16 (3 schemas: master, project, settings)

## Commands
- `php artisan test` — backend tests
- `php artisan pint` — format PHP
- `php artisan migrate` — apply migration
- `npm run lint` — lint TS/JS
- `npm run build` — build production
- `npx tsc --noEmit` — TypeScript type check
- `npx playwright test` — e2e tests

## Tools Available
- **Context7:** Fetch library docs (Next.js, React, Laravel, Sanctum, dll). WAJIB pakai sebelum pakai library baru.
- **web_search:** Cari best practice + pattern terbaru
- **docker:** Container management
';

return fn (string $target) => 'Buat AGENTS.md untuk proyek ini. Output dalam format Markdown. AGENTS.md adalah panduan perilaku untuk AI coding agent — WAJIB berisi role definitions, hard rules, dan file structure.

# AGENTS.md — AI Coding Agent Rules
' . ($target === 'mobile'
    ? $flutterAgents
    : ($target === 'both'
        ? $webAgents . PHP_EOL . PHP_EOL . $flutterAgents
        : $webAgents)) . '

' . platformSuffix($target) . PHP_EOL . '

[ATURAN]
- AGENTS.md WAJIB punya section "Agent Roles" dengan owner + scope + handoff eksplisit per agent.
- Hard Rules WAJIB di awal (sebelum struktur) dan dipakai sebagai hard constraint.
- File structure WAJIB match dengan codebase real (lihat konteks).
- Bahasa Indonesia untuk penjelasan, English untuk code identifier + technical terms.

[OUTPUT INSTRUCTIONS]
- Jawab HANYA dengan AGENTS.md di atas.
- Tidak ada intro/closing.
- Hard rules pakai numbered list, JANGAN bullet (konsisten dengan enforcement).

VERIFY: Apakah setiap agent punya scope + handoff yang jelas? Apakah hard rules 10+ dan enforceable? Apakah file structure match dengan codebase?

VERIFY STRUKTUR (validator backend enforce — hard rules + file structure WAJIB ada):
1. "Hard Rules" section WAJIB ada dengan minimal 10 item numbered (`1. **...**`).
2. Hard rules pakai numbered list (JANGAN bullet) untuk konsistensi enforcement.
3. "Agent Roles" section WAJIB punya minimal 3 agent dengan scope + handoff eksplisit.
4. "File Structure" section WAJIB ada code block (```) yang match codebase real.
5. Format pakai Bahasa Indonesia untuk penjelasan, English untuk code identifier + technical terms.
';
