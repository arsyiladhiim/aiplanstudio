# 08 — Frontend (Next.js)

> Lihat juga: [04-api-contract](04-api-contract.md) · [05-wizard-flow](05-wizard-flow.md) · [02-architecture](02-architecture.md)

## Stack
- Next.js (App Router) + Tailwind CSS v4.
- **React Flow** — render ERD diagram.
- **react-markdown** — terdaftar di dependency (belum dipakai; artefak dirender sebagai `<pre>` plain text).
- Client SPA; semua data lewat REST `/api` direct ke Laravel via `NEXT_PUBLIC_API_URL` dengan `credentials: "include".

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
        new/page.tsx               # WIZARD 14/10 tahap (inti)
        projects/page.tsx          # daftar project
        projects/[id]/page.tsx     # detail: versi, artefak, progress, export
        templates/page.tsx         # galeri template
        settings/layout.tsx        # settings tab layout
        settings/page.tsx          # redirect ke /settings/provider
        settings/provider/page.tsx # admin — CRUD AI provider
        settings/users/page.tsx    # admin — manajemen user
    components/
      ui/                          # UI kit (Button, Card, Badge, Input, Label)
      wizard/
        ErdDiagram.tsx             # React Flow ERD diagram
      AppShell.tsx                 # authenticated layout shell
      ThemeToggle.tsx              # dark/light toggle
      common.tsx                   # PageHeader, ProgressBar, TargetBadge
    lib/
      api.ts                       # direct fetch wrapper + CSRF + SSE helpers
      mock.ts                      # mock data (fallback — tidak dipakai di production)
    proxy.ts                       # route guard (Next.js 16) — redirect unauthenticated ke /login
```

> **Catatan:** Dokumentasi awal menyebut struktur komponen yang lebih granular (`landing/Nav`, `wizard/IdeaInput`, `project/ProjectList`, `settings/ProviderForm`, `e2e/`). Saat ini kode masih inline di page files dan hanya komponen umum yang diekstrak.

## Auth Flow (Sanctum SPA Session — Direct Routing)
1. Login/register POST ke `${NEXT_PUBLIC_API_URL}/api/login` atau `/api/register` → Laravel set session cookie (HttpOnly, `SameSite=None; Secure` untuk cross-origin).
2. Semua fetch otomatis menyertakan cookie (`credentials: "include"`).
3. Untuk non-GET request, ambil `XSRF-TOKEN` dari cookie → kirim sebagai `X-XSRF-TOKEN` header.
4. Jika 401 → redirect ke `/login`.
5. Logout → `POST /api/logout` → invalidate session → redirect ke `/login`.
6. Guard: halaman `/settings/*` hanya untuk `role === 'admin'` (dicek via `/api/user`).
   Halaman app lain butuh login. **Guard route via `web/src/proxy.ts`** (Next.js 16 rename `middleware.ts` → `proxy.ts`): redirect unauthenticated users ke `/login?redirect={path}` untuk protected paths (`/dashboard`, `/projects`, `/new`, `/templates`, `/settings`, `/activities`, `/help`).

`lib/api.ts` menyediakan `apiGet/apiPost/...` yang otomatis menyertakan cookies + CSRF headers + `credentials: "include"` untuk cross-origin.

## Auth flow detail (Direct Routing):
1. Browser → fetch `${NEXT_PUBLIC_API_URL}/api/...` dengan `credentials: "include"` (cookie session otomatis)
2. Browser kirim CSRF: ambil `XSRF-TOKEN` dari cookie → header `X-XSRF-TOKEN`
3. Laravel validasi session cookie (stateful domain match) + CSRF → return response
4. Browser handle response (success / 401 redirect / error)

## Login page (`/login`)
- Form submit → fetch POST `${NEXT_PUBLIC_API_URL}/api/login` ← direct call ke Laravel
- Laravel set session cookie (HttpOnly + `SameSite=None; Secure` cross-origin) via Set-Cookie response
- Redirect ke `/dashboard`

## Logout
- POST `/api/logout` → Laravel invalidate session
- Redirect ke `/login`

## Konsumsi SSE (`createSSE()`)
- `new EventSource(`${NEXT_PUBLIC_API_URL}/api/generate/stream?version=..&stage=..&auto=..`, { withCredentials: true })`
- Handle event: `status`, `token` (append streaming), `artifact` (set final), `done`, `error`.
- Auth via **cookies** (`withCredentials: true`), bukan Bearer token.
- Native EventSource.

## Projects
- `/projects`: daftar project + buat baru.
- `/projects/[id]`: versi selector, artifact tabs (analysis, PRD, architecture, ERD, standards, agents, phases, master prompt, mobile phases/standards/agents/master), progress checklist, export.
- **Export** `.md`/`.zip` → direct fetch ke Laravel generate.

## Settings (admin)
- **Provider**: form untuk name, base_url, provider type, API key (masked), model, test connection, test prompt, set active.
- **Users**: tabel list/tambah/ubah role/hapus user. Modal untuk tambah user.

## Aturan Frontend
- Jangan simpan rahasia AI di client — provider dikelola backend.
- Tidak ada token di JavaScript — auth via HttpOnly session cookie.
- Tangani 401 → redirect login.
- Gunakan `fetchCsrfCookie()` sebelum state-changing request (PATCH/POST/DELETE).
