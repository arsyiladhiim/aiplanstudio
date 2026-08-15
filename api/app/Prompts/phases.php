<?php

return fn (string $target) => 'Anda tech lead. Buat Implementation Roadmap dalam format teks (BUKAN JSON). Roadmap ini jadi acuan AI coding agent untuk eksekusi build — setiap fase punya goal jelas, deliverables measurable, dan urutan yang benar.

# Implementation Roadmap: <NAMA_PROYEK>

## Aturan Wajib
1. **WAJIB minimal 5 fase, maksimal 10 fase.**
2. **Fase pertama WAJIB:** `fase1_setup` — Setup Proyek (init repo, env, DB, CI).
3. **Fase kedua WAJIB:** `fase2_design` — Design System & Layout (UI shell, design tokens, navigation).
4. **Fase terakhir WAJIB:** `faseN_deploy` — Testing & Deploy (e2e, lighthouse, production deploy).
5. **Setiap fase WAJIB punya:**
   - `key` (snake_case, format `fase<N>_<deskripsi>`)
   - `title` (human readable)
   - `tujuan` (1 kalimat)
   - `dependensi` (fase sebelumnya yang harus done, atau "tidak ada")
   - `effort` (S = <4 jam, M = 4-8 jam, L = >8 jam)
   - minimal 3 `task`
   - minimal 1 `acceptance_criteria`
   - sub-item breakdown (HALAMAN, FITUR, FLOW, API — yang relevant)
6. **Sub-item key format:** `<fase_key>_<type>_<n>` (e.g. `fase3_auth_halaman_1`, `fase3_auth_fitur_1`).
7. **Tidak semua fase punya semua 5 tipe sub-item** — isi yang relevant saja. WAJIB minimal HALAMAN + FITUR per fase yang melibatkan UI.
8. **Urutan fase mengikuti dependensi logis:** Setup → Design → Backend Core → Fitur Inti → Polish → Testing → Deploy.

## Format per Fase

```
FASE: <key> | <title>
TUJUAN: <1 kalimat spesifik apa yang selesai>
DEPENDENSI: <key fase sebelumnya | tidak ada>
EFFORT: S | M | L
TASK: <task detail 1>
TASK: <task detail 2>
TASK: <task detail 3>
TASK: <task detail 4 — opsional>
HALAMAN: <halaman_key> | <judul> | <deskripsi>
MENU: <menu_key> | <judul> | <parent/navigasi>
FITUR: <fitur_key> | <judul> | <fungsionalitas detail>
FLOW: <flow_key> | <nama flow> | <step1 → step2 → step3 → selesai>
API: <api_key> | METHOD <path> | <deskripsi endpoint>
INSTRUKSI: <instruksi teknis lengkap untuk AI agent, minimal 100 kata. Sertakan: file yang dibuat/diubah, pendekatan teknis, edge cases, integrasi dengan fase lain.>
AC: <acceptance criteria — measurable, cek-able>
---
```

## Template Fase Wajib (WAJIB ada, key TIDAK BOLEH diganti)

### Fase 1
FASE: fase1_setup | Setup Proyek
TUJUAN: Repo initialized, environment variables configured, database migrated, CI pipeline green.
DEPENDENSI: tidak ada
EFFORT: M
TASK: Init git repo + .gitignore + README + LICENSE
TASK: Setup .env.example + docker-compose.yml + Makefile shortcuts
TASK: Database migration pertama + seed admin user
TASK: CI pipeline: lint + test + build (GitHub Actions / GitLab CI)
HALAMAN: fase1_setup_halaman_1 | Health Check | Endpoint /api/health return 200 OK
FITUR: fase1_setup_fitur_1 | Auth Scaffold | Register + Login + Logout working dengan session cookie
INSTRUKSI: Setup monorepo structure (api/ dan web/). Backend: Laravel fresh install, Sanctum config, CORS, CSRF. Frontend: Next.js init, TypeScript strict, Tailwind v4, base layout + AppShell. Docker Compose dengan services: db, aiplanstudio_apifpm, aiplanstudio_web, aiplanstudionginx_api. Health check endpoint di backend. Verify `npm run dev` + `php artisan serve` jalan tanpa error.
AC: `docker compose up` brings all services up healthy; `php artisan test` passes 1 trivial test; `npm run lint && npx tsc --noEmit` clean; `/api/health` returns 200.
---

### Fase 2
FASE: fase2_design | Design System & UI Shell
TUJUAN: Design tokens configured, navigation working, layout responsive, base components reusable.
DEPENDENSI: fase1_setup
EFFORT: M
TASK: Define design tokens di globals.css (color, spacing, typography, radius)
TASK: Build base components: Button, Card, Badge, Input, Modal, ConfirmDialog
TASK: Implement AppShell (sidebar + topbar + main content area)
TASK: Setup auth pages UI (login, register) — visual only, no logic yet
HALAMAN: fase2_design_halaman_1 | Login | Form login dengan field email + password, button submit, link ke register
HALAMAN: fase2_design_halaman_2 | Dashboard (placeholder) | Empty dashboard dengan greeting + "Welcome, {user}"
MENU: fase2_design_menu_1 | Sidebar Nav | Dashboard | /dashboard
MENU: fase2_design_menu_2 | Sidebar Nav | Settings | /settings
FITUR: fase2_design_fitur_1 | Theme Switch | Toggle light/dark mode persisted ke localStorage
FITUR: fase2_design_fitur_2 | Responsive Layout | Sidebar collapse di mobile, hamburger menu
INSTRUKSI: Build design system dengan semantic color tokens (color-brand, color-surface, color-fg, color-border). Base components di components/ui/. Pakai cva (class-variance-authority) untuk variant. Responsive: mobile-first, breakpoint md untuk sidebar. Theme: dark mode default, toggle switch. A11y: aria-label di icon button, focus-visible ring.
AC: All base components reusable; sidebar responsive di mobile; theme toggle persists across reload; WCAG 2.1 AA contrast pass.
---

## Fase-fase Sisanya (3 sampai N)
Sesuaikan dengan PRD. Tipe fase yang umum:
- `fase3_auth` — Full auth (register, login, forgot password, email verify, session mgmt)
- `fase4_core_data` — Core domain entities + CRUD + list/detail
- `fase5_features` — Fitur spesifik dari PRD (group by feature area)
- `fase6_integration` — Third-party integration (payment, email, storage)
- `fase7_polish` — UX polish (loading states, empty states, error boundaries)
- `fase8_testing` — Comprehensive test (e2e, visual regression, perf)
- `fase9_deploy` — Production deploy (CI/CD, monitoring, backup)

Pilih 3-7 fase dari list di atas yang relevan dengan PRD. JANGAN pakai semua — hanya yang punya deliverables jelas.

' . platformSuffix($target) . PHP_EOL . '

[CONTOH PENENTUAN FASE]
- Kalau PRD fokus CRUD sederhana → 5 fase: setup, design, auth, core_crud, deploy.
- Kalau PRD ada payment + email → 7 fase: setup, design, auth, core_crud, payment, email, deploy.
- Kalau PRD mobile-only + ada notifikasi → 6 fase: setup, design, auth, core_crud, notifikasi, deploy.

[OUTPUT INSTRUCTIONS]
- Jawab HANYA dengan roadmap di atas. Mulai dari `# Implementation Roadmap: ...`.
- WAJIB fase 1 dan fase 2 pakai template di atas (key + struktur fixed).
- Fase 3..N boleh custom tapi WAJIB semua field (key, title, tujuan, dependensi, effort, task, sub-item, instruksi, AC).
- JANGAN tulis intro/closing.

VERIFY: Apakah fase 1 + fase 2 sesuai template? Apakah effort realistic? Apakah dependensi logis (tidak circular)? Apakah jumlah fase 5-10?';
