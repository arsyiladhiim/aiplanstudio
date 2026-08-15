<?php

return fn(string $target) => 'Anda senior prompt engineer. Buat MASTER PROMPT dalam format teks (BUKAN JSON). Dokumen ini self-contained — AI coding agent membacanya sekali dari awal sampai akhir untuk membangun seluruh aplikasi web. Tidak ada iterasi, tidak ada pertanyaan balik.

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
- Backend: Laravel 11 (PHP 8.4) + Sanctum SPA Session
- Frontend: Next.js (App Router) + React 19 + TypeScript + Tailwind CSS v4
- DB: PostgreSQL 16 (3 schemas: master/project/settings)
- Infra: Docker Compose
- Auth: HttpOnly cookie + CSRF (JANGAN Bearer token di browser)
- API gateway: semua /api/* via Next.js BFF route handlers

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
├── api/                       # BFF route handlers
│   ├── auth/[...]/route.ts
│   ├── projects/[...]/route.ts
│   └── webhooks/[...]/route.ts
components/
├── ui/                        # Button, Card, Modal, Badge, Input, Textarea
├── wizard/                    # Pipeline stage components
└── layout/                    # AppShell, Sidebar, Topbar
lib/
├── api.ts                     # fetch wrappers + types
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
- `app/api/<path>/route.ts` — <fungsi>
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
**Webhook trigger:** Setelah SEMUA sub-items + fase selesai, kirim webhook `done` (lihat §6).

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
Setelah SETIAP fase selesai, kirim HTTP POST:
- URL: `/api/webhooks/phase-complete` (atau env `APP_URL`)
- Headers (case-sensitive, semua WAJIB):
  - `Authorization: Bearer <TOKEN>`
  - `X-Token-Secret: <SECRET>`
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

Token + Secret sudah diberikan user via UI (lihat tombol Setup Tracking). AMBIL dari situ sebelum panggil webhook.

PENTING:
- `phase_key` HARUS key PERSIS dari daftar fase di §4 (contoh: `fase1_setup`). JANGAN pakai `phase-1`.
- `task_key` HARUS key PERSIS dari sub-item di fase.
- Status: `running` saat mulai, `done` saat selesai, `error` saat gagal.
- Hanya LANJUT fase berikutnya setelah webhook `done` untuk fase saat ini sukses.

## 7. Self-Verify Checklist (jalankan sebelum commit)
- [ ] `php artisan test` pass
- [ ] `npm run lint && npx tsc --noEmit` clean
- [ ] Tidak ada `console.log` / `dd()` / `var_dump` tertinggal
- [ ] Tidak ada `TODO` / `FIXME` di kode baru
- [ ] Migration + model casts sinkron
- [ ] CSRF cookie aktif untuk semua POST/PATCH/DELETE
- [ ] `.env.example` ter-update jika ada env baru

## 8. Output Instructions
- Jawab HANYA dengan master prompt di atas (text, bukan JSON).
- WAJIB isi semua placeholder `<...>` dengan data asli dari konteks pipeline.
- JANGAN tulis basa-basi, intro, atau closing — langsung ke `# <NAMA_PROYEK> — Master Build Prompt`.
- Setiap fase WAJIB punya semua 7 bagian (Tujuan/Effort/Files/Tasks/Sub-items/Instruksi/AC).

' . ($target === 'both'
    ? 'CATATAN: Proyek ini juga akan membangun MOBILE (Flutter/Android) di master prompt terpisah. Master prompt ini fokus WEB ONLY.'
    : 'Target: WEB ONLY.') . PHP_EOL . platformSuffix($target) . PHP_EOL . '

VERIFY sebelum respond: apakah SEMUA placeholder `<...>` terisi? Apakah SEMUA fase dari konteks ada? Apakah format marker `## SELESAI` ada di akhir fase terakhir?';
