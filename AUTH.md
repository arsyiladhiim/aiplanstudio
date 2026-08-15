# Autentikasi — AI Plan Studio

## Arsitektur

- **Auth**: Sanctum SPA Session (HttpOnly cookie + CSRF). Bukan Bearer token.
- **Alur**: Browser → fetch `${NEXT_PUBLIC_API_URL}/api/*` (cross-origin, `credentials: "include"`) → Laravel API. **Direct routing, no BFF** — see `docs/25-bypass-bff.md`. Frontend call Laravel langsung tanpa Next.js sebagai proxy.
- **CSRF**: Cookie `XSRF-TOKEN` (tidak HttpOnly). Setiap mutasi wajib `fetchCsrfCookie()` dulu, lalu kirim header `X-XSRF-TOKEN` (di-decode dengan `decodeURIComponent`).
- **Session cookie**: `ai-planning-studio-session` (HttpOnly, `SameSite=None; Secure` untuk cross-origin). Laravel set via Set-Cookie; browser otomatis kirim balik.

## Alur user baru (approval)

1. User pertama yang daftar → otomatis `admin` + `active` + auto-login.
2. User berikutnya → `member` + `pending`, **tidak** auto-login, diarahkan ke `/login?status=pending`.
3. Login menolak user `pending` (pesan generik "Kredensial tidak cocok.").
4. Admin approve/reject di **Settings → Pengguna**:
   - Approve → `PATCH /api/settings/users/{id}` `{ status: "active" }`
   - Reject → hapus user (`DELETE /api/settings/users/{id}`)
5. Guard keamanan: admin **terakhir** (satu-satunya admin aktif) tidak bisa di-demote ke `member` atau di-set `pending` — mencegah lockout sistem.

## Reset password

- `POST /api/forgot-password` `{ email }` → kirim notifikasi `ResetPassword` dengan link ke frontend `/reset-password?token=..&email=..`.
- `POST /api/reset-password` `{ token, email, password, password_confirmation }`.
- Link selalu mengarah ke `config('app.frontend_url')`.
- Di dev, mailer `log` → cek `api/storage/logs/laravel.log` untuk link reset.

## Google OAuth

Paket `laravel/socialite`. Flow: browser → `GET ${APP_URL}/api/auth/google` → Laravel redirect ke Google → Google redirect balik ke callback `${APP_URL}/api/auth/google/callback` → Laravel login.

### Env vars (`api/.env`)

```
FRONTEND_URL=http://localhost:3000
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

### Checklist lokal (dev)

1. Buat OAuth Client di [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → "OAuth 2.0 Client IDs" → Web application.
2. Tambahkan **Authorized redirect URI**: `http://localhost:8000/api/auth/google/callback` (= `$APP_URL/api/auth/google/callback`, port nginx_api direct).
3. Isi `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` di `api/.env`.
4. Pastikan `FRONTEND_URL=http://localhost:3000` (Next.js dev direct, no BFF).
5. Restart `aiplanstudio_apifpm` container (atau `php artisan config:clear`).

### Checklist Docker (nginx_api port 8000)

1. Redirect URI Google Cloud Console: `http://localhost:8000/api/auth/google/callback`.
2. Di `api/.env` (yang dipakai `docker-compose.yml` via `env_file`):

```
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

3. `SESSION_DOMAIN=localhost` tetap berlaku karena cookie pakai domain `localhost`.

### Catatan perilaku Google login

- User pertama login Google → `admin` + `active` + auto-login.
- User berikutnya → `member` + `pending` → diarahkan `/login?status=pending`.
- User `pending` yang sudah ada → `/login?status=pending`.
- User `active` yang sudah ada → langsung login.
- Gagal (kredensial salah / jaringan) → `/login?error=google`.

## Test

- Backend: `php artisan test` — mencakup `AuthTest`, `SettingsTest`, `PasswordResetTest`, `SocialiteControllerTest` (fake Google provider, tanpa kredensial asli).
- Frontend: `npm run lint`, `npx tsc --noEmit`, `npm run build`.
