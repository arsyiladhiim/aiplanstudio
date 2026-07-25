# 08 — Frontend (Next.js)

> Lihat juga: [04-api-contract](04-api-contract.md) · [05-wizard-flow](05-wizard-flow.md) · [02-architecture](02-architecture.md)

## Stack
- Next.js (App Router) + Tailwind CSS.
- **React Flow** — render ERD diagram.
- **react-markdown** — render artefak (analisa, PRD, prompt) + tombol copy.
- Client SPA; semua data lewat REST `/api` same-origin via BFF.

## Struktur
```
web/
  app/
    layout.tsx
    page.tsx                 # landing
    (auth)/login/page.tsx
    (auth)/register/page.tsx
    dashboard/page.tsx       # ringkasan + lanjutkan project
    new/page.tsx             # WIZARD 6 tahap (inti)
    projects/page.tsx        # arsip
    projects/[id]/page.tsx   # detail: versi, artefak, progress, export
    templates/page.tsx
    settings/provider/page.tsx  # admin
    settings/users/page.tsx     # admin
  components/
    landing/{Nav,Hero,Features,CTA,Footer}.tsx
    wizard/{IdeaInput,StageTracker,ArtifactPanel,ErdDiagram,CheckpointBar}.tsx
    project/{ProjectList,VersionList,ProgressBar,ExportButton}.tsx
    settings/{ProviderForm,UserTable}.tsx
    apps/                    # aplikasi utama
  lib/
    api.ts                   # fetch wrapper + Bearer token helpers
    bff.ts                   # BFF route helpers (token forwarding)
    mock.ts                  # mock data (hanya untuk fallback)
  e2e/
    helpers.ts               # shared login helpers
    auth.spec.ts, register.spec.ts, wizard.spec.ts, ...  # 11 spec files
```

## Auth Flow (Bearer Token)
1. Login/register POST ke `/api/login` atau `/api/register` → Laravel return `{token, user}`.
2. Token disimpan di **sessionStorage** (hilang saat tab ditutup).
3. Semua fetch otomatis menyertakan `Authorization: Bearer <token>` header.
4. Jika 401 → token dihapus, redirect ke `/login`.
5. Logout → revoke token via `POST /api/logout` + hapus dari sessionStorage.
6. Guard: halaman `/settings/*` hanya untuk `role === 'admin'` (dicek via `/api/user`).
   Halaman app lain butuh login. Middleware Next.js redirect ke `/login` bila token tidak ada.

`lib/api.ts` menyediakan `apiGet/apiPost/...` yang otomatis menyertakan Bearer token.
`lib/bff.ts` menyediakan helper untuk BFF route handler (proxy ke Laravel dengan token forwarding).

## Auth flow detail (BFF proxy):
1. Browser → fetch `/api/...` dengan Authorization Bearer header
2. Next.js menerima request, extract token dari header
3. Next.js proxy fetch ke Laravel (`http://api:8000`) dengan token yang sama
4. Laravel memvalidasi PersonalAccessToken → return response
5. Next.js return response ke browser

## Login page (`/login`)
- Form submit → fetch POST `/api/login` ← langsung via BFF, bukan API client
- Terima `{token, user}` dari response
- Simpan token ke sessionStorage
- Redirect ke `/dashboard`

## Logout
- POST `/api/logout` dengan token → Laravel revoke token current
- Hapus dari sessionStorage
- Redirect ke `/login`

## Konsumsi SSE (`createSSE()`)
- `EventSource('/api/generate/stream?version=..&stage=..&auto=..')`
- Handle event: `status`, `token` (append streaming), `artifact` (set final), `done`, `error`.
- Token auth via Authorization header (bukan cookie).

## Projects
- `/projects`: daftar project + filter, buat baru.
- `/projects/[id]`: versi selector, artifact tabs, progress checklist, export.
- **Export** `.md`/`.zip` → BFF proxy → Laravel generate.

## Settings (admin)
- `ProviderForm`: base_url, API key (password, masked), model, Test Koneksi.
- `UserTable`: list/tambah/ubah role/hapus user. Modal untuk tambah user.

## Aturan Frontend
- Jangan simpan rahasia AI di client — provider dikelola backend.
- Token disimpan di sessionStorage (bukan localStorage) — lebih aman terhadap XSS persist.
- Gunakan `getToken()`, `setToken()`, `clearToken()` dari `lib/api.ts`.
- Tangani 401 → clear token + redirect login.
