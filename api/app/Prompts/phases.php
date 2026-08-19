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
9. **HARUS inklusif fase non-fungsional:** `fase7_security` WAJIB untuk app apa pun yang menyimpan data user / memproses pembayaran (bukan opsional). `fase_observability` + `fase_api_docs` + `fase_dr` WAJIB untuk production.
10. **Jangan pernah menyingkat fase security/polish/testing/deploy** — roadmap harus mencakup seluruh fase sampai production-ready (lihat §7 master prompt).

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
INSTRUKSI: Setup monorepo structure (api/ dan web/). Backend: Laravel fresh install, Sanctum config, CORS, CSRF. Frontend: Next.js init, TypeScript strict, Tailwind v4, base layout + AppShell. Docker Compose dengan services: db, <project_slug>_apifpm, <project_slug>_web, <project_slug>_nginx_api. Health check endpoint di backend. Verify `npm run dev` + `php artisan serve` jalan tanpa error.
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
- `fase7_security` — Security hardening (CSP, HSTS, rate limiting, audit log, OWASP top-10)
- `fase8_polish` — UX polish (loading states, empty states, error boundaries)
- `fase9_testing` — Comprehensive test (e2e, visual regression, perf)
- `fase10_deploy` — Production deploy (CI/CD, monitoring, backup)

Pilih 3-7 fase dari list di atas yang relevan dengan PRD. JANGAN pakai semua — hanya yang punya deliverables jelas.

### Template Fase Security (WAJIB untuk app yang handle data user/payment)

### Fase 7 (jika `fase7_security` relevan)
FASE: fase7_security | Security Hardening
TUJUAN: Semua kontrol keamanan OWASP top-10 aktif, header CSP/HSTS terkonfigurasi, rate limiting aktif, audit log untuk aksi sensitif.
DEPENDENSI: fase4_core_data
EFFORT: M
TASK: Security headers middleware (CSP, HSTS, X-Frame-Options, Permissions-Policy, X-Content-Type-Options)
TASK: Rate limiting middleware (60 req/min per IP untuk public, 600 req/min untuk auth)
TASK: CSRF aktif untuk semua POST/PATCH/DELETE (Sanctum cookie + X-XSRF-TOKEN)
TASK: FormRequest validation untuk semua input user (no inline validate())
TASK: SQL parameterized queries only (Eloquent atau DB::table() dengan binding)
TASK: XSS sanitization via Blade {{ }} escaping + DOMPurify untuk rich text
TASK: Secrets management via env() + Laravel encrypted casting untuk DB
TASK: Audit log via Activity model untuk aksi sensitif (login, payment, data export, role change)
TASK: Dependency audit (`composer audit` + `npm audit`) zero high/critical
TASK: File upload validation (MIME check + size limit + antivirus scan jika ada)
HALAMAN: fase7_security_halaman_1 | Security Settings | Halaman admin untuk lihat audit log + manage API tokens
FITUR: fase7_security_fitur_1 | Rate Limiting | 60 req/min untuk endpoint public, return 429 dengan Retry-After header
FITUR: fase7_security_fitur_2 | Audit Log Viewer | Admin bisa lihat log aksi sensitif dengan filter by user/action/date
INSTRUKSI: Implement security headers middleware di Laravel (register di bootstrap/app.php). Rate limiting pakai Laravel built-in `throttle:` middleware + custom RateLimiter di AppServiceProvider. CSRF otomatis aktif via Sanctum + VerifyCsrfToken. FormRequest WAJIB untuk setiap endpoint yang terima input — jangan inline validate(). Untuk SQL, Eloquent atau DB::table() dengan parameter binding (DB::raw() HANYA untuk SELECT read-only). XSS: Blade `{{ }}` auto-escape; untuk rich text pakai DOMPurify di frontend. Secrets: env() untuk runtime, encrypted cast untuk DB column. Audit log: pakai Activity::create() di observer atau middleware. File upload: validasi MIME via `mimetypes:` rule + size via `max:` rule + antivirus scan via ClamAV jika production.
AC: `composer audit` zero high/critical; `npm audit` zero high/critical; security headers present di response (verify via `curl -I`); rate limiting returns 429 setelah threshold; audit log entry tercatat untuk login + payment + data export; CSRF token required untuk POST/PATCH/DELETE (reject 419 jika missing).
---

### Template Fase Performance (WAJIB untuk app dengan traffic/SEO)

### Fase Performance (contoh: `fase_perf` atau gabung ke `fase9_testing`)
FASE: fase_perf | Performance & Lighthouse Testing
TUJUAN: Lighthouse score Performance > 90, A11y > 95; API p95 < 300ms; DB query < 100ms.
DEPENDENSI: fase7_polish
EFFORT: M
TASK: Lighthouse CI integration (target Performance > 90, A11y > 95, Best Practices > 95, SEO > 90)
TASK: Bundle analyzer untuk frontend (`@next/bundle-analyzer`) — flag chunk > 200KB
TASK: Image optimization (Next.js Image component, WebP format, lazy loading)
TASK: Database index untuk kolom sering di-query (verify via EXPLAIN ANALYZE)
TASK: API response time p95 < 300ms verification via k6/autocannon load test
TASK: Database query time < 100ms verification via telescope/log query log
TASK: Caching strategy: Redis untuk hot data (session, rate limit, frequently-read queries)
TASK: CDN setup untuk static assets (Cloudflare cache + Next.js static export)
FITUR: fase_perf_fitur_1 | Performance Budget | CI check fail jika bundle > 200KB atau Lighthouse score < target
INSTRUKSI: Setup Lighthouse CI via GitHub Actions (treosh/lighthouse-ci-action). Jalankan audit terhadap production build (`npm run build && npm run start`). Bundle analyzer: tambahkan di next.config.js, jalankan `ANALYZE=true npm run build`. Image: pakai `next/image` dengan `priority` untuk above-the-fold, `loading="lazy"` untuk sisanya. DB index: jalankan `EXPLAIN ANALYZE` untuk slow query, tambahkan index di migration baru (JANGAN edit applied migration). Load test: k6 script untuk simulate 100 concurrent user, measure p95 response time. Caching: pakai `Cache::remember()` untuk query yang rarely change. CDN: enable Cloudflare cache untuk static asset + page rule untuk `/api/*` no-cache.
AC: Lighthouse CI score: Performance > 90, A11y > 95, Best Practices > 95; API p95 < 300ms untuk semua endpoint critical; DB query < 100ms (verify via slow query log); bundle size < 200KB per chunk; image WebP served ke browser yang support; Redis cache hit ratio > 70% untuk hot data.
---

### Template Fase Observability (WAJIB untuk production)

### Fase Observability (contoh: gabung ke `fase10_deploy` atau pisah jadi `fase_observability`)
FASE: fase_observability | Monitoring & Logging
TUJUAN: Sentry error tracking aktif, structured logging aktif, health check endpoint respond < 100ms, uptime monitoring configured.
DEPENDENSI: fase_perf
EFFORT: S
TASK: Sentry SDK integration (Laravel + Next.js) — capture exception + performance trace
TASK: Structured logging via Laravel Log (JSON format dengan request_id, user_id, action)
TASK: Health check endpoint `/api/health` — verify DB + Redis + storage respond 200
TASK: Uptime monitoring (UptimeRobot / Better Uptime) — alert jika downtime > 1 menit
TASK: Error budget tracking (SLO: 99.5% uptime monthly)
TASK: Performance monitoring (APM via Sentry atau New Relic)
TASK: Log retention policy (30 hari hot, 1 tahun cold storage)
FITUR: fase_observability_fitur_1 | Health Check | `/api/health` return 200 dengan status DB/Redis/storage
FITUR: fase_observability_fitur_2 | Error Tracking | Sentry capture semua exception dengan context (user, request, stack)
INSTRUKSI: Sentry: install `sentry/sentry-laravel` untuk backend + `@sentry/nextjs` untuk frontend, set DSN via env. Structured logging: pakai Laravel Log channel `daily` dengan formatter JSON (atau Monolog processor). Health check: implement di `routes/api.php` dengan DB ping (`DB::connection()->getPdo()`) + Redis ping (`Redis::ping()`) + storage check (`Storage::disk(\"local\")->exists(\"health\")`). Uptime monitoring: setup di UptimeRobot dengan interval 1 menit, alert ke email/Slack. Log retention: pakai Laravel logrotate atau CloudWatch. APM: enable Sentry performance monitoring dengan sample rate 0.1 untuk production.
AC: Sentry capture test exception (trigger manual error, verify muncul di dashboard); `/api/health` respond < 100ms dengan status semua service; uptime monitor aktif dengan alert configured; log JSON format dengan request_id; error budget dashboard accessible.
---

### Template Fase API Documentation (WAJIB untuk app dengan API public)

### Fase API Docs (contoh: gabung ke `fase6_integration` atau pisah jadi `fase_api_docs`)
FASE: fase_api_docs | API Documentation Generation
TUJUAN: OpenAPI spec auto-generated, Swagger UI accessible, Postman collection exportable, README API reference lengkap dengan curl example.
DEPENDENSI: fase4_core_data
EFFORT: S
TASK: OpenAPI spec generation via `darkaonline/l5-swagger` atau scrape dari routes
TASK: Swagger UI route `/api/docs` (hanya accessible untuk authenticated user dengan role admin)
TASK: Postman collection export (import OpenAPI spec ke Postman, export sebagai JSON)
TASK: README API reference section dengan curl example untuk setiap endpoint
TASK: API changelog (auto-generated dari git tag + breaking change annotation)
TASK: Webhook documentation (request/response schema + signature verification example)
FITUR: fase_api_docs_fitur_1 | Interactive API Docs | Swagger UI dengan try-it-out functionality
FITUR: fase_api_docs_fitur_2 | Postman Collection | Download collection JSON untuk testing
INSTRUKSI: Install `darkaonline/l5-swagger` via composer. Tambahkan `@OA\\Info()` annotation di `app/Http/Controllers/Controller.php` untuk metadata. Generate annotation per controller method (`@OA\\Get()`, `@OA\\Post()`, dll). Jalankan `php artisan l5-swagger:generate` untuk regenerate spec. Mount Swagger UI di `/api/docs` dengan middleware `auth:sanctum` + role check. Postman: import OpenAPI JSON di Postman → export sebagai collection v2.1. README: tambahkan section "API Reference" dengan curl example + link ke Swagger UI. Webhook docs: jelaskan signature verification dengan Python/Node.js example.
AC: OpenAPI spec valid (verify di swagger.io/validator); Swagger UI accessible di `/api/docs` dengan auth; Postman collection importable + runnable; README API reference minimal 5 endpoint examples; webhook signature verification example tested dengan real request.
---

### Template Fase Rollback & DR (WAJIB untuk production)

### Fase DR (contoh: gabung ke `fase10_deploy` atau pisah jadi `fase_dr`)
FASE: fase_dr | Disaster Recovery & Rollback
TUJUAN: Migration rollback tested, application rollback via image tag, backup verification otomatis, incident response runbook tersedia.
DEPENDENSI: fase_observability
EFFORT: S
TASK: Migration rollback testing (jalankan `php artisan migrate:rollback` di staging, verify no data loss)
TASK: Application rollback via Docker image tag (`<project_slug>_apifpm:v1.0.0` → `v0.9.0`)
TASK: Database backup harian via cron `pg_dump` + retention 30 hari
TASK: Backup verification mingguan (restore backup ke staging, verify data integrity)
TASK: Incident response runbook di `docs/incident-response.md` (oncall rotation, escalation path)
TASK: Database point-in-time recovery (PITR) enabled di production
TASK: Failover testing (jika multi-region, test failover setiap quarter)
FITUR: fase_dr_fitur_1 | Backup Verification | Automated weekly restore test ke staging dengan report
FITUR: fase_dr_fitur_2 | Incident Dashboard | Status page untuk user (status.yourdomain.com) dengan incident history
INSTRUKSI: Migration rollback: setiap migration WAJIB punya method `down()` yang tested. Setup CI job untuk run `migrate:rollback --step=1 && migrate` di test DB. Application rollback: tag Docker image dengan semantic version, simpan 5 versi terakhir di registry. Backup: cron job harian `pg_dump` ke volume backup, retention 30 hari, old backup auto-delete. Backup verification: weekly cron restore ke staging DB, jalankan smoke test, kirim report ke email/Slack. PITR: enable di managed Postgres (AWS RDS, DigitalOcean, dll) untuk point-in-time recovery. Runbook: tulis step-by-step untuk common incident (DB down, API down, deploy failed, security breach). Status page: pakai self-hosted (statuspage alternatives) atau SaaS (Statuspage, Better Uptime).
AC: `migrate:rollback` tested di staging tanpa data loss; backup verification cron jalan tiap minggu + kirim report; incident response runbook ada di docs/ + tested via tabletop exercise; rollback Docker image verified dapat di-deploy dalam < 5 menit; PITR configured + tested restore dari 1 jam sebelumnya; status page accessible + update incident dalam 15 menit.
---

'.platformSuffix($target).PHP_EOL.'

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
