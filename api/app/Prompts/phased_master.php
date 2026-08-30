<?php

return fn (string $target) => 'Anda senior prompt engineer. Buat MASTER PROMPT dalam format teks (BUKAN JSON). Dokumen ini self-contained — AI coding agent membacanya sekali dari awal sampai akhir untuk membangun seluruh aplikasi web. Tidak ada iterasi, tidak ada pertanyaan balik.

# <NAMA_PROYEK> — Master Build Prompt

## 0. Meta
- Project: <judul proyek>
- Target: Web App
- Tech Stack: <ringkasan stack dari konteks>
- Tanggal dibuat: <YYYY-MM-DD>
- Versi: v1

## 1. Context (max 200 kata)
<Ringkasan padat dari analisa + PRD: apa masalahnya, untuk siapa, bagaimana aplikasi menyelesaikannya. Singkat dan tajam — JANGAN copy paste penuh analisa/PRD di sini.>

## 2. Tech Stack (final, tidak bisa diubah)
- Backend: Laravel 13 (PHP 8.4) + Sanctum SPA Session
- Frontend: Next.js (App Router) + React 19 + TypeScript + Tailwind CSS v4
- DB: PostgreSQL 18 (3 schemas: master/project/settings)
- Infra: Docker Compose + Cloudflare Tunnel (no BFF layer — see docs/25-bypass-bff.md)
- Auth: HttpOnly cookie + CSRF (JANGAN Bearer token di browser)
- API gateway: browser fetch DIRECT ke Laravel via `NEXT_PUBLIC_API_URL` (cross-origin, `credentials: "include"`)

WAJIB gunakan stack di atas. JANGAN usulkan alternatif kecuali diminta eksplisit.

## 3. Folder Structure
```
app/
├── (auth)/
│   ├── login/page.tsx
│   └── register/page.tsx
├── (app)/
│   ├── dashboard/page.tsx
│   ├── projects/page.tsx
│   ├── projects/[id]/page.tsx
│   ├── new/page.tsx
│   └── settings/page.tsx
components/
├── ui/                        # Button, Card, Modal, Badge, Input, Textarea
├── wizard/                    # Pipeline stage components
└── layout/                    # AppShell, Sidebar, Topbar
lib/
├── api.ts                     # direct fetch wrappers + Sanctum cookie session + CSRF
├── auth.ts
└── utils.ts
```
Backend (Laravel): `app/Http/Controllers/`, `app/Services/`, `app/Models/`, `database/migrations/`, `routes/api.php`.

## 4. Implementation Phases
WAJIB copy-paste PERSIS `key` fase dari konteks. JANGAN buat key baru atau re-nomor ulang.

Untuk SETIAP fase, gunakan template ini:

### FASE: <key>
**Tujuan:** <1 kalimat — apa yang selesai di fase ini>
**Effort:** S | M | L
**Files:**
- `app/(app)/<path>` — <fungsi>
- `lib/api.ts` — <fungsi> (jika ada endpoint integration baru)
- `app/Http/Controllers/<X>Controller.php` — <fungsi>
**Tasks:**
- [ ] <task 1>
- [ ] <task 2>
- [ ] <task 3>
**Sub-items:**

#### Halaman (jika ada):
- HALAMAN: <key> | <judul> | <deskripsi halaman + komponen utama>

#### Menu (jika ada):
- MENU: <key> | <judul> | <path navigasi + icon>

#### Fitur (jika ada):
- FITUR: <key> | <judul> | <fungsionalitas detail + happy path + edge cases>

#### Flow (jika ada):
- FLOW: <key> | <nama> | <step-by-step user journey>

#### API (jika ada):
- API: <key> | METHOD <path> | <deskripsi + request/response shape>

**Instruksi teknis:** <minimal 150 kata: setup, konvensi, validasi, error handling, test approach>
**Acceptance Criteria:**
- [ ] <AC 1 — measurable>
- [ ] <AC 2 — measurable>
- [ ] <AC 3 — measurable>
**Webhook trigger:** Kirim webhook `running` SAAT MULAI fase (sebelum kode ditulis), lalu webhook `done` SETELAH fase + semua sub-item selesai (lihat §6). Lakukan untuk SETIAP fase mulai dari fase pertama (`fase1_setup`) hingga fase terakhir (`faseN_deploy`) — dari awal hingga akhir, agar seluruh progress detail tercatat di website.

Lanjutkan untuk SEMUA fase sampai habis. Setelah fase terakhir, tambahkan marker "## SELESAI_ALL".

## 5. Coding Standards (ringkas, sumber lengkap di /docs)
- TypeScript strict mode ON
- ESLint + Prettier pass sebelum commit
- Backend: Pint formatting
- Naming: camelCase untuk JS/TS, snake_case untuk PHP DB columns
- Error handling: backend return JSON `{message, errors}` dengan HTTP code; frontend toast via helper
- Test: PHPUnit FeatureTest untuk backend, Playwright untuk e2e
- Commit message: Conventional Commits (`feat:`, `fix:`, `chore:`)

## 6. Tracking Webhook (WAJIB per fase + sub-item)
Setelah SETIAP fase selesai, kirim HTTP POST ke endpoint webhook. URL absolut + token + secret SUDAH ter-embed di master prompt ini dalam blok TRACKING CREDENTIALS — gunakan PERSIS nilai dari blok tersebut (jangan mengarang, jangan ubah). Jalankan snippet siap-pakai yang ada di blok TRACKING CREDENTIALS untuk setiap fase/sub-item; JANGAN membentuk ulang path/header secara manual.
- Headers (case-sensitive, sudah disiapkan snippet di TRACKING CREDENTIALS):
  - `Authorization: Bearer <TOKEN>` (dari blok TRACKING CREDENTIALS)
  - `X-Token-Secret: <SECRET>` (dari blok TRACKING CREDENTIALS)
  - `X-Timestamp: <unix_seconds>`
  - `X-Signature: hmac_sha256("<X-Timestamp>.<raw_body>", "<X-Token-Secret>")`
  - `Content-Type: application/json`
- Body per fase:
  ```json
  {"version_id": <int>, "phase_key": "<key>", "status": "done", "output": "<ringkasan>"}
  ```
- Body per sub-item (setiap HALAMAN/MENU/FITUR/FLOW/API selesai):
  ```json
  {"version_id": <int>, "phase_key": "<key>", "task_key": "<sub_item_key>", "task_type": "halaman|menu|fitur|flow|api", "title": "<judul>", "status": "done", "output": "<ringkasan>"}
  ```

PENTING:
- `version_id`, `phase_key`, dan `task_key` HARUS diambil dari prompt ini (lihat blok TRACKING CREDENTIALS untuk version_id, dan §4 untuk daftar phase_key + sub-item task_key). JANGAN pakai `phase-1`.
- Status: `running` saat mulai, `done` saat selesai, `error` saat gagal.
- Hanya LANJUT fase berikutnya setelah webhook `done` untuk fase saat ini sukses.
- Aturan error handling: retry 3x backoff 1s/2s/4s, timeout 10s, 409 = sudah tercatat lanjut, 422 = perbaiki key, gagal total = catat dan lanjut — JANGAN berhenti permanen.
- Bila blok TRACKING CREDENTIALS tidak ada atau TOKEN kosong, JANGAN mengarang nilai — berhenti, minta user melakukan Setup Tracking di website sebelum build.

## 7. Operational Readiness (WAJIB baca sebelum build — artefak dari stage terpisah)
Sebelum menulis kode, BACA dokumen operasional yang sudah di-generate wizard:
- **`env-config.md`** — semua env var (APP_KEY, DB, Redis, SESSION, Sanctum, MAIL, OAuth, integrasi eksternal) + `.env.example`. Isi `.env` produksi dari sini.
- **`security-checklist.md`** — OWASP: auth/session, RBAC, input validation, XSS/headers, rate-limit, secret hygiene. WAJIB checklist lulus sebelum rilis.
- **`deployment.md`** — Docker Compose + Cloudflare Tunnel (no exposed ports), DNS/TLS, `pg_dump` backup + restore-verify, rollback image tag, zero-downtime.
- **`observability.md`** — `/api/health`, Sentry, structured log (request_id), uptime SLO, runbook root-cause.

Aturan:
- Jangan hardcode secret di kode. Ambil dari env (`.env`) — di-render Laravel via `env()` / `config()`.
- `APP_DEBUG=false` + HTTPS (`SECURE_COOKIE`, HSTS) saat produksi.
- Buat `docker/` (compose), `cloudflared` config, dan `.github/workflows/` (CI build/deploy) persis seperti di `deployment.md`.
- Untuk tiap fitur yang handle data user / pembayaran: WAJIB implementasikan item di `security-checklist.md` + tambahkan `fase_observability` / `fase_dr` / `fase_api_docs` di roadmap (lihat §4).

## 8. Self-Verify Checklist (jalankan sebelum commit)
- [ ] `php artisan test` pass
- [ ] `npm run lint && npx tsc --noEmit` clean
- [ ] Tidak ada `console.log` / `dd()` / `var_dump` tertinggal
- [ ] Tidak ada `TODO` / `FIXME` di kode baru
- [ ] Migration + model casts sinkron
- [ ] CSRF cookie aktif untuk semua POST/PATCH/DELETE
- [ ] `.env.example` ter-update jika ada env baru

## 9. Output Instructions
- Jawab HANYA dengan master prompt di atas (text, bukan JSON).
- WAJIB isi semua placeholder `<...>` dengan data asli dari konteks pipeline.
- JANGAN tulis basa-basi, intro, atau closing — langsung ke `# <NAMA_PROYEK> — Master Build Prompt`.
- Setiap fase WAJIB punya semua 7 bagian (Tujuan/Effort/Files/Tasks/Sub-items/Instruksi/AC).

'.($target === 'both'
    ? 'CATATAN: Proyek ini juga akan membangun MOBILE (Flutter/Android) di master prompt terpisah. Master prompt ini fokus WEB ONLY.'
    : 'Target: WEB ONLY.').PHP_EOL.platformSuffix($target).PHP_EOL.'

VERIFY sebelum respond: apakah SEMUA placeholder `<...>` terisi? Apakah SEMUA fase dari konteks ada? Apakah format marker `## SELESAI` ada di akhir fase terakhir?';
