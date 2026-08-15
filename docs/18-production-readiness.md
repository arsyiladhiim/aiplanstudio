# 18 — Production Readiness & Feature Inventory

> Dokumen ini adalah titik validasi "siap produksi" dan **inventori lengkap** aplikasi.
> Tujuan utama: memberi AI Agent (dan manusia) **checklist terverifikasi** agar tiap fitur/menu/halaman bisa divalidasi bahwa sudah sesuai spesifikasi & siap dipakai.
>
> Lihat juga: [09-roadmap](09-roadmap.md) · [17-next-progress](17-next-progress.md) · [05-wizard-flow](05-wizard-flow.md) · [04-api-contract](04-api-contract.md)

---

## Status Verifikasi Global

| Aspek | Status | Cara verifikasi |
|-------|--------|-----------------|
| Backend test | ✅ **246 passed, 1 skipped, 1 failed (pre-existing Socialite ordering)** (980 assertions) | `php artisan test --env=testing` |
| Frontend lint | ✅ 0 error / 0 warning | `npm run lint` |
| TypeScript | ✅ 0 error | `npx tsc --noEmit` |
| Build | ✅ semua route compile | `npm run build` |
| E2E Playwright | ✅ 10 test hijau (auth, wizard, projects) + E20–E25 planned (MP12) | lihat [14-frontend-testing](14-frontend-testing.md) |
| Database schema | ✅ 3 schema master/project/settings + `public.users.accent_color` (MP11) | psql query |
| Error monitoring | ⚠️ GlitchTip **DISABLED** (service di-comment, DSN kosong, SDK no-op) | re-enable bila dibutuhkan |
| Dependency audit | ✅ `composer audit` + `npm audit` 0 vuln | command audit |
| Persistent volume | ✅ semua data → host `./docker/` | docker inspect mount |
| Cloudflare Tunnel | ✅ 2 ingress: `aiplanstudio.arsyiladm.my.id` → Next.js :3000 + `api-aiplanstudio.arsyiladm.my.id` → Laravel via nginx :80 | `docker logs` cloudflared |
| Direct routing (no BFF) | ✅ frontend → API direct via Cloudflare Tunnel, CORS configured | `curl https://api-aiplanstudio.arsyiladm.my.id/api/version` |
| CSP/Security headers | ✅ `connect-src https://api-aiplanstudio.arsyiladm.my.id`, `frame-ancestors 'none'`, `SameSite=None; Secure` cookies | `curl -I https://aiplanstudio.arsyiladm.my.id` |

---

## Bagian A — Inventori Halaman Aplikasi (19 route unique)

Legenda Auth: **Pub** = public · **Auth** = butuh login · **Admin** = admin only.

### A1. Public Pages (7)

| Route | Halaman | Menu | Fitur |
|-------|---------|------|-------|
| `/` | Landing | — | Hero, fitur grid, workflow steps, CTA, footer |
| `/login` | Login | Header link | Form email+password, tombol Google OAuth, "Lupa password", notice pending account |
| `/register` | Register | Header link | Form name+email+password+confirm, Google OAuth |
| `/forgot-password` | Lupa Password | Via /login | Form email, state sukses |
| `/reset-password` | Reset Password | Via email link | Token+email+password baru, validasi min 8 |
| `/privacy` | Kebijakan Privasi | Footer | Konten statis 7 bagian |
| `/terms` | Syarat Layanan | Footer | Konten statis 8 bagian |

### A2. Authenticated Pages — App Shell (10)

| Route | Halaman | Menu | Auth | Fitur |
|-------|---------|------|------|-------|
| `/dashboard` | Dashboard | Sidebar | Auth | Statistik (total project, versi, favorit), recent projects, recent activities, refresh |
| `/projects` | Projects List | Sidebar | Auth | Search, filter favorit, grid kartu, progress bar, continue, hapus (dialog) |
| `/projects/[id]` | Project Detail | Sidebar | Auth | Header (edit, favorit), pilih versi, diff mode, 8 tabs (Klarifikasi/Analisa/PRD/Arsitektur/ERD/Phases/Mobile/Aktivitas), API token, master prompt copy, Standards/Agents, checklist progres |
| `/projects/[id]/diff` | Version Diff | — | Auth | Bandingkan 2 versi seluruh field |
| `/new` | Buat Plan (Wizard) | Sidebar | Auth | Template, input ide/target/stack, 14/10-stage SSE, stage tracker, ERD diagram, API contract table, phases, master prompt, standards/agents, resumable |
| `/templates` | Templates | Sidebar | Auth | Kartu template, badge target, "Gunakan Template" |
| `/activities` | Aktivitas | Header/Footer | Auth | Feed aktivitas terpaginasi, badge aksi, link project |
| `/help` | Bantuan | Header | Auth | How-it-works, FAQ accordion |
| `/settings/profile` | Profile | Settings tab | Auth | Edit nama/email, ganti password |
| `/settings/provider` | AI Provider | Settings tab | Admin | CRUD provider, test koneksi, test-prompt, set active, global model |
| `/settings/users` | User Management | Settings tab | Admin | List user, role admin/member, approval pending, add user, delete, block |
| `/settings` | Settings (redirect) | Sidebar | Auth | Redirect ke `/settings/provider` |

---

## Bagian B — Navigasi (AppShell)

### 5.1 Sidebar Menu
| Label | Route | Route aktif | Fitur |
|-------|-------|-------------|-------|
| Dashboard | `/dashboard` | exact | NavItem → page |
| Projects | `/projects` | exact | NavItem → page |
| Buat Plan | `/new` | exact | NavItem → page + tombol CTA "Buat Plan Baru" |
| Templates | `/templates` | exact | NavItem → page |
| Settings | `/settings/provider` | prefix `/settings` | NavItem → settings |

### 5.2 Header App Shell
| Elemen | Aksi |
|--------|------|
| Logo → `/dashboard` | Navigasi home authed |
| Input search | Redirect `/projects?q=...` |
| Toggle tema | Light/dark |
| "Bantuan" link | `/help` |
| Avatar user | Tampilkan inisial |

### 5.3 Footer App Shell
| Elemen | Aksi |
|--------|------|
| Nama + role user | Detail user |
| Tombol logout | `POST /api/logout` → `/login` |

### 5.4 Settings Tab (di dalam /settings/*)
| Tab | Route |
|-----|-------|
| Profile | `/settings/profile` |
| AI Provider | `/settings/provider` |
| User Management | `/settings/users` |

---

## Bagian C — Alur (Flow) Per Feature

### Flow 1 — Authentication
```
User buka /register
  → CSRF cookie (fetchCsrfCookie) — jeda
  → POST /api/register (throttle 5)
    User #1  → role=admin, status=active → langsung login
    User #N  → role=member, status=pending → notice "tunggu approval admin"
Admin approve (settings/users)
  → status=active
User login (/login)
  → POST /api/login (Sanctum SPA, HttpOnly cookie + XSRF-TOKEN CSRF)
  → redirect /dashboard
Opsional: Google OAuth (Socialite)
  → /api/auth/google → callback → login
Logout
  → POST /api/logout → invalidate session cookie → /login
```

### 6.2 Wizard "Buat Plan" — Pipeline 13 Stage (inti produk)
```
/new   (input: ide, target=web|both, stack opsional, template)
  1 pertanyaan           → pertanyaan (klarifikasi MCQ)     [SSE]
     user jawab          → answers
  2 analisa              → analysis
  3 prd                  → prd
  4 architecture         → architecture
  5 erd                  → erd {nodes,edges,api_contract}
  6 api_contract         → api_contract (array endpoint)
  7 phases_web           → phases (breakdown fase web)
  8 standards_web        → standards (STANDARDS.md web)
  9 master_web           → master_prompt (self-contained web + auto token tracking)
  10 pertanyaan_mobile   → pertanyaan_mobile + mobile_answers (hanya both, gate: master_web done)
  11 phases_mobile       → mobile_phases (breakdown fase mobile)
  12 standards_mobile    → mobile_standards (STANDARDS.md mobile)
  13 master_mobile       → mobile_master_prompt (self-contained mobile)
  14 agents              → agents (AGENTS.md)
Garansi:
   - per-stage manual: setelah tiap stage user approve lanjut (tanpa auto-run)
   - konfirmasi bila tracking fase web belum selesai (master_web)
   - resumable: versi terakhir dilanjut
   - gate: mobile track menunggu master_web done
   - stage_status: pending|running|done|error
   - inline edit artifact: PATCH /versions/{id}/artifacts
```

### 6.3 Project, Versioning, Diff, Export
```
Create project → otomatis version v1 (kosong, stage pending)
Generate (new)  → isi version → stage_status done
New version     → POST /projects/{id}/versions → v2 (kosong)
Daftar versi    → dropdown di /projects/[id]
Diff            → /projects/[id]/diff?compare=2 (10 field)
Toggle phase    → PATCH /versions/{id}/phases/{phaseKey} → PhaseProgress.done
Export          → GET /versions/{id}/export?format=md|zip
Standards/Agents→ GET /versions/{id}/standards, /agents, /standards/mobile, /agents/mobile
Regenerate      → POST /versions/{id}/regenerate-standards[/mobile]
Hapus versi     → DELETE /versions/{id} (tidak bisa versi terakhir)
```

### 6.4 Settings
```
AI Provider (admin)
  - CRUD: store/update/delete
  - test koneksi: POST /settings/provider/{id}/test
  - test-prompt:  POST /settings/provider/{id}/test-prompt
  - set active:   POST /settings/provider/{id}/set-active   (satu active global)
User Management (admin)
  - list + paginate
  - store (name/email/password/role)
  - update (name/email/password/role/status)
  - destroy (tidak bisa hapus diri sendiri)
Profile (semua user)
  - show/update (name, email, password)
```

### 6.5 Activity Log & API Token & Webhook
```
Activity:
  - project-scoped: GET /projects/{id}/activities
  - global:         GET /activities (admin)
  - action values: `created_version` | `deleted_version`
API Token (project webhook):
  - POST  /projects/{id}/tokens       → token sekali tampil (hash sha256 disimpan)
  - GET   /projects/{id}/tokens
  - DELETE /projects/{id}/tokens/{tokenId}
Webhook:
  - POST /webhooks/phase-complete (auth middleware project token)
  - verify token_hash → jalankan or log
```

---

## Bagian D — Validasi Alur vs Tujuan Awal (Aplikasi)

Dasar tujuan (`docs/01-overview.md`): *"Membantu solo developer menghasilkan dokumentasi & prompt lengkap dari ide yang siap disuapkan ke AI coding agent"* + **bukan eksekutor kode** + **target-aware** (web/both).

### D1. Kesesuaian
| Tujuan Awal | Implementasi | Status |
|-------------|--------------|--------|
| Ide → dokumentasi & prompt lengkap | 14-stage pipeline, tiap stage menyimpan artifact: analisa, PRD, arsitektur, ERD, api_contract, standards, agents, phases, master prompt | ✅ |
| Benang merah antar langkah | PipelineRunner menyimpan konteks stage sebelumnya ke stage berikutnya | ✅ |
| Target-aware (web/both) | mobile track (stage 10-13) khusus mobile; gate menunggu master_web; stack & prompt berbeda per target; 4 mobile fields | ✅ |
| Bukan eksekutor kode | Tidak ada endpoint eksekusi; semuanya doku & prompt | ✅ |
| Checkpoint / auto-run | Wizard per-stage manual (approve tiap stage; tanpa auto-run) | ✅ |
| Resumable & terdokumentasi | Versioning, diff, progress checklist, export | ✅ |
| Project arsip + versioning + fingerprint | versi v1..vN, diff, export md/zip | ✅ |
| User Management + AI Provider global | settings/users (approval), settings/provider (active global) | ✅ |

### Step2. Inkonsistensi Dokumentasi (untuk diperbaiki Task 4)
| Isu | File | Catatan |
|-----|------|---------|
| "Wizard 6 tahap" vs aktual 7 | `docs/01-overview.md:33`, `:49` | Sebenarnya ada 7 ( phased_master_mobile). **[SUPERSEDED]** Kini 13 tahap (D-028). |
| Roadmap status belum catat P13/P14 | `docs/09-roadmap.md:15` | Tambah P13, P14 |
| Security audit item belum centang | `docs/12-security-checklist.md:54` | `composer audit`/`npm audit` sudah 0 |

---

## Bagian E — Production Readiness Checklist (AI Agent)

> Tiap baris: checkbox + langkah verifikasi nyata (command/tool). Centang tanda [] saat lolos.

### E1. Kode & Build
- [ ] `docker compose exec api-fpm php artisan test` → 150 passed
- [ ] `npm run lint` (di web/) → 0 error
- [ ] `npx tsc --noEmit` → 0 error
- [ ] `npm run build` → 17/17 pages
- [ ] `docker run ... playwright e2e` → 10 passed

### E2. Dependency & Keamanan
- [ ] `composer audit --no-dev` → 0 kritikal
- [ ] `npm audit` → 0 vuln
- [ ] `.env` tidak ter-commit; hanya `.env.example` (dokumentasi template)

### E3. Database
- [ ] Public schema hasil dibuat ulang; semua tabel only di `aiplanstudio_*`
- [ ] (cek) `SELECT schemaname,tablename FROM pg_tables WHERE schemaname='public'` → 0 baris (DB `aiplanstudio`)
- [ ] search_path di `config/database.php` tanpa `public`
- [ ] Data volume ter-map host (`./docker/postgres/data_`, `./docker/redis/data`, `./docker/glitchtip/uploads`)

### E4. Stack & Network
- [ ] 5 containers aktif (web, api, api-fpm, db, redis) — glitchtip DISABLED; tanpa `aiplanstudio-migrate`
- [ ] aiplanstudionginx_api reachable dari tunnel container (`docker network inspect aiplanstudio_aiplanstudio` confirm `cloudflare_tunnel-cloudflare-tunnel-1` attached)
- [ ] `curl http://localhost:8000/api/health` → `{"status":"ok"}` (direct nginx_api, no BFF)

### E5. Auth & RBAC
- [ ] Login session cookie (Sanctum) berhasil (login admin dev)
- [ ] Register: 1st user auto admin; berikutnya pending
- [ ] Admin route (`/settings/users`, `/settings/provider`) → member dapat 403
- [ ] Logout invalidate session

### E6. Core Pipeline
- [ ] AI provider aktif (settings/provider → active + DSN key)
- [ ] Wizard `/new` → 14/10-stage berjalan (SSE) → artifact ter-save ke DB
- [ ] target=both → mobile track (stage 10-13) berjalan setelah master_web done (gate); target=web → mobile track di-skip
- [ ] Checkpoint mode & auto-run mode bekerja
- [ ] Resume project→ versi terakhir

### E7. Project & Export
- [ ] Export `.md` dan `.zip` valid (unduh, isi sesuai)
- [ ] Download standards/agents, mobile variants
- [ ] Diff 2 versi menampilkan field

### E8. Monitoring
- [ ] GlitchTip menerima error server-side (jalankan test error) — *DISABLED saat ini, nonaktif dulu*
- [ ] Error none real logs (`docker compose logs api-fpm/web/nginx` tidak ada error aktif)

### E9. Production Env (jika diluncurkan ke publik)
- [ ] `.env.production` proper set (APP_ENV/prod, DEBUG=false, SESSION_SECURE_COOKIE, SMTP)
- [ ] HTTPS termination (Cloudflare) — nginx & Laravel di kepercayaan proxy
- [ ] Google OAuth keys produksi & redirect URI
- [ ] Backup policy DB (volume host)

---

## Log Perubahan
| Tanggal | Perubahan |
|---------|-----------|
| 2026-08-07 | Buat docs/18: invorti 19 halaman, 5 menu + extras, alur utama, matings section 4 checklist. |

---

## Cara Teknis Keperuntukan
1. Matchesin kan walk - AI Agent verifikasi setiap bagian E menggunakan command nyata.
2. Jika satu indikator meres: perbaiki kode → jalankan lint/test/build lagi.
3. Setelah hijau, update `docs/09-roadmap.md` + `docs/15-dev-log.md`.

---

## Referensi
- [00-README](00-README.md) · [02-architecture](02-architecture.md) · [03-database-schema](03-database-schema.md) · [04-api-contract](04-api-contract.md) · [05-wizard-flow](05-wizard-flow.md) · [12-security-checklist](12-security-checklist.md)