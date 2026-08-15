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
- Tech Stack: Laravel 11 (PHP 8.4) + Next.js 15 (App Router, React 19) + TypeScript + Tailwind CSS v4 + PostgreSQL 16
- Auth: Sanctum SPA Session (HttpOnly cookie + CSRF, BUKAN Bearer token di browser)
- API gateway: Next.js BFF route handlers proxy ke Laravel

## Agent Roles

### 🎨 web-frontend-agent (UI/UX)
- **Scope:** Pages, components, layouts, theming, animations, client-side state
- **Owns:** `web/src/app/(app)/`, `web/src/components/`, `web/src/lib/hooks/`
- **Tools:** Context7 (Next.js/React/Tailwind docs), web_search
- **Constraint:** Server Components default, "use client" hanya kalau perlu interactivity. JANGAN hardcoded color — pakai design tokens. JANGAN setState di useEffect (React 19 Compiler rules).
- **Handoff:** Setelah UI ready, lempar ke `web-api-agent` untuk wire ke BFF endpoint

### ⚙️ web-backend-agent (Laravel API)
- **Scope:** Controllers, FormRequest, Service, Repository, Migration, Model, Policy
- **Owns:** `api/app/Http/`, `api/app/Services/`, `api/app/Models/`, `api/database/migrations/`, `api/routes/api.php`
- **Tools:** Context7 (Laravel/Sanctum docs)
- **Constraint:** Type hints WAJIB. FormRequest untuk validasi. Policy untuk authorization. Migration forward-only. Pint formatting.
- **Handoff:** Setelah endpoint ready, update `routes/api.php` + lempar ke `web-frontend-agent` untuk consume via BFF

### 🔄 web-bff-agent (Next.js API Routes)
- **Scope:** `web/src/app/api/**/route.ts` — proxy Laravel dengan session cookie forwarding
- **Owns:** All `web/src/app/api/` route handlers
- **Tools:** Context7 (Next.js route handlers docs)
- **Constraint:** Handle 401 dengan redirect ke /login. Forward Set-Cookie header. JANGAN call Laravel langsung dari browser.
- **Handoff:** BFF endpoint ready → consume dari `web-frontend-agent`

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
8. **JANGAN panggil Laravel langsung dari browser** — selalu via BFF
9. **CSRF WAJIB aktif** untuk semua POST/PATCH/DELETE dari browser
10. **JANGAN pakai Bearer token di browser** — pakai session cookie

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
│   │   ├── (app)/           # dashboard, projects, new, settings
│   │   └── api/             # BFF route handlers
│   ├── components/
│   │   ├── ui/              # design system
│   │   ├── wizard/          # pipeline components
│   │   └── layout/
│   ├── lib/                 # api wrappers, utils
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

return fn(string $target) => 'Buat AGENTS.md untuk proyek ini. Output dalam format Markdown. AGENTS.md adalah panduan perilaku untuk AI coding agent — WAJIB berisi role definitions, hard rules, dan file structure.

# AGENTS.md — AI Coding Agent Rules
' . ($target === 'mobile' || $target === 'both'
    ? $flutterAgents
    : $webAgents) . '

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

VERIFY: Apakah setiap agent punya scope + handoff yang jelas? Apakah hard rules 10+ dan enforceable? Apakah file structure match dengan codebase?';
