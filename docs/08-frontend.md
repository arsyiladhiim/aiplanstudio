# 08 — Frontend (Next.js)

> Lihat juga: [04-api-contract](04-api-contract.md) · [05-wizard-flow](05-wizard-flow.md) · [02-architecture](02-architecture.md)

## Stack
- Next.js (App Router) + Tailwind CSS v4.
- **React Flow** — render ERD diagram.
- **react-markdown** — terdaftar di dependency (belum dipakai; artefak dirender sebagai `<pre>` plain text).
- Client SPA; semua data lewat REST `/api` same-origin via BFF.

## Struktur Aktual
```
web/
  src/
    app/
      layout.tsx                   # root layout
      page.tsx                     # landing page
      globals.css                  # Tailwind v4 + design tokens
      (auth)/
        login/page.tsx
        register/page.tsx
        layout.tsx                 # auth layout (branded split-screen)
      (app)/
        layout.tsx                 # app shell (sidebar, header, nav)
        dashboard/page.tsx         # ringkasan + project terbaru
        new/page.tsx               # WIZARD 6 tahap (inti)
        projects/page.tsx          # daftar project
        projects/[id]/page.tsx     # detail: versi, artefak, progress, export
        templates/page.tsx         # galeri template
        settings/layout.tsx        # settings tab layout
        settings/page.tsx          # redirect ke /settings/provider
        settings/provider/page.tsx # admin — CRUD AI provider
        settings/users/page.tsx    # admin — manajemen user
      api/                         # BFF route handlers (17 file)
    components/
      ui/                          # UI kit (Button, Card, Badge, Input, Label)
      wizard/
        ErdDiagram.tsx             # React Flow ERD diagram
      AppShell.tsx                 # authenticated layout shell
      ThemeToggle.tsx              # dark/light toggle
      common.tsx                   # PageHeader, ProgressBar, TargetBadge
    lib/
      api.ts                       # fetch wrapper + CSRF + SSE helpers
      bff.ts                       # BFF proxy helpers (cookie forwarding)
      mock.ts                      # mock data (fallback — tidak dipakai di production)
    middleware.ts                  # saat ini no-op (belum ada guard)
```

> **Catatan:** Dokumentasi awal menyebut struktur komponen yang lebih granular (`landing/Nav`, `wizard/IdeaInput`, `project/ProjectList`, `settings/ProviderForm`, `e2e/`). Saat ini kode masih inline di page files dan hanya komponen umum yang diekstrak.

## Auth Flow (Sanctum SPA Session)
1. Login/register POST ke `/api/login` atau `/api/register` → Laravel set session cookie (HttpOnly).
2. Semua fetch otomatis menyertakan cookie (`credentials: 'include'`).
3. Untuk non-GET request, ambil `XSRF-TOKEN` dari cookie → kirim sebagai `X-XSRF-TOKEN` header.
4. Jika 401 → redirect ke `/login`.
5. Logout → `POST /api/logout` → invalidate session → redirect ke `/login`.
6. Guard: halaman `/settings/*` hanya untuk `role === 'admin'` (dicek via `/api/user`).
   Halaman app lain butuh login. **Middleware Next.js saat ini no-op** (belum implementasi guard — lihat [16-audit-fix-plan](16-audit-fix-plan.md#rs)).

`lib/api.ts` menyediakan `apiGet/apiPost/...` yang otomatis menyertakan cookies + CSRF headers.
`lib/bff.ts` menyediakan helper untuk BFF route handler (proxy ke Laravel dengan cookie forwarding).

## Auth flow detail (BFF proxy):
1. Browser → fetch `/api/...` dengan cookie session
2. Next.js menerima request, forward Cookie + XSRF headers ke Laravel
3. Laravel memvalidasi session → return response
4. Next.js return response ke browser

## Login page (`/login`)
- Form submit → fetch POST `/api/login` ← langsung via BFF, bukan API client
- Laravel set session cookie (HttpOnly) via Set-Cookie response
- Redirect ke `/dashboard`

## Logout
- POST `/api/logout` → Laravel invalidate session
- Redirect ke `/login`

## Konsumsi SSE (`createSSE()`)
- `new EventSource('/api/generate/stream?version=..&stage=..&auto=..', { withCredentials: true })`
- Handle event: `status`, `token` (append streaming), `artifact` (set final), `done`, `error`.
- Auth via **cookies** (`withCredentials: true`), bukan Bearer token.

## Projects
- `/projects`: daftar project + buat baru.
- `/projects/[id]`: versi selector, artifact tabs (analysis, PRD, architecture, ERD, phases, master), progress checklist, export.
- **Export** `.md`/`.zip` → BFF proxy → Laravel generate.

## Settings (admin)
- **Provider**: form untuk name, base_url, provider type, API key (masked), model, test connection, test prompt, set active.
- **Users**: tabel list/tambah/ubah role/hapus user. Modal untuk tambah user.

## Aturan Frontend
- Jangan simpan rahasia AI di client — provider dikelola backend.
- Tidak ada token di JavaScript — auth via HttpOnly session cookie.
- Tangani 401 → redirect login.
- Gunakan `fetchCsrfCookie()` sebelum state-changing request (PATCH/POST/DELETE).
