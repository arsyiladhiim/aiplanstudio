# Master Plan v2 — Post CP-14 Improvement Roadmap

> **Versi:** 2.0 · **Tanggal mulai:** 2026-08-15 · **Status:** Active
> **Inherit dari:** `master-repair.md` (CP-1..14 selesai)
> **Pendahulu:** `docs/09-roadmap.md` (strategic), `docs/16-audit-fix-plan.md` (technical debt)

## Latar Belakang

Setelah CP-13 (Modal focus + CSRF cross-origin) dan CP-14 (UX errors + doc sync) deployed, audit end-to-end menemukan 15 item improvement yang belum di-address. Item dikelompokkan per criticality:

- **High (3)** — UX safety & real-time UX
- **Medium (4)** — production hardening (security + audit)
- **Low (4)** — polish & nice-to-have
- **Feature Baru (4)** — capability expansion

Total **15 item** dikelompokkan dalam **4 CP (CP-15..CP-18)**, masing-masing dengan **item N/M done + checkpoint log**.

## Konvensi Checkpoint

Setiap CP punya:

1. **Plan section** — daftar item + rationale + files touched.
2. **Execution** — eksekusi tiap item dengan sub-checks.
3. **Verify** — `php artisan test`, `npx tsc --noEmit`, `npm run lint`, smoke test matrix.
4. **Checkpoint Log** — append entry setelah selesai SEBELUM lanjut ke CP berikutnya.
5. **Single commit per CP** di branch `devel`, push ke `origin/devel`.

**Rule:** Lanjut ke CP berikutnya hanya setelah CP sebelumnya committed + pushed + checkpoint logged.

---

## CP-15 — High Priority UX Safety (3 items)

### CP-15.H1 — Audit + fix destructive `confirm()` patterns
- **Problem:** Halaman projects + versions pakai inline `window.confirm()` (UX buruk, native browser modal). Belum diaudit sejak CP-14.
- **Plan:**
  - Grep `web/src/app/(app)/projects/**` dan `web/src/app/(app)/projects/[id]/**` untuk `window.confirm` / `confirm()`.
  - Replace dengan Modal konfirmasi reusable (`web/src/components/ui/ConfirmDialog.tsx`).
  - Tambah reason field untuk delete (required text input untuk konfirmasi delete permanent).
- **Files:** `web/src/components/ui/ConfirmDialog.tsx` (new), `web/src/app/(app)/projects/**/page.tsx`, `web/src/app/(app)/projects/[id]/page.tsx`.

### CP-15.H2 — Last-admin warning banner (before attempt)
- **Problem:** Saat ini admin hanya tahu "tidak bisa demote" setelah klik tombol dan dapat 422 error. Lebih baik warning SEBELUM attempt.
- **Plan:**
  - Di `users/page.tsx`, fetch jumlah admin aktif dari existing list (no extra API call).
  - Jika `activeAdmins === 1 && target.role === 'admin'`: tampilkan banner warning di samping tombol demote/delete.
  - Disable tombol dengan tooltip "Tidak bisa demote admin terakhir".
- **Files:** `web/src/app/(app)/settings/users/page.tsx`.

### CP-15.H3 — Auto-refresh pending count
- **Problem:** Admin tidak tahu ada user pending baru sampai refresh page. SSE/polling untuk real-time.
- **Plan:**
  - Reuse endpoint `GET /api/settings/users` dengan polling interval 30s (simple, no SSE infra).
  - Tampilkan toast "Ada N permintaan menunggu" saat count naik.
  - Optional: SSE channel `/api/settings/users/stream` (future enhancement).
- **Files:** `web/src/app/(app)/settings/users/page.tsx`, optional `api/routes/api.php` + controller.

---

## CP-16 — Medium Priority Production Hardening (4 items)

### CP-16.M1 — CSRF token expiry tracking + Origin guard
- **Problem:** `/api/csrf-token` return raw session token, throttled 60/min/IP. Bisa di-scrap. Origin guard = CORS sudah handle, tapi defence-in-depth tambah `IssuedAt` + `ExpiresAt` di response.
- **Plan:**
  - Endpoint return `{token, issued_at, expires_at}` (expires_at = issued_at + session_lifetime).
  - Frontend cache honor expires_at, refetch saat expired (lazy).
  - Optionally check Origin header di endpoint (reject unknown origin) — defense-in-depth, CORS sudah block.
- **Files:** `api/routes/api.php`, `web/src/lib/api.ts`.

### CP-16.M2 — Rate limit per-user (not per-IP)
- **Problem:** `throttle:5,1` login/register saat ini per-IP. Cloudflare Tunnel shared egress = semua user dari IP sama. Bot dari 1 IP bisa lockout semua user legit.
- **Plan:**
  - Custom rate limiter `RateLimiter::for('login', fn(Request $r) => Limit::perMinute(5)->by($r->input('email') ?? $r->ip()))`.
  - Sama untuk `register`, `forgot-password`.
  - Test: simulasi 5 attempts dari IP berbeda dengan email sama → semua blocked.
- **Files:** `api/routes/api.php` atau custom middleware, `api/app/Providers/AppServiceProvider.php` (register limiters).

### CP-16.M3 — Audit log for approve/delete user actions
- **Problem:** `UserSettingsController::update`/`destroy` tidak catat ke `Activity` table. Tidak ada jejak siapa approve user X kapan.
- **Plan:**
  - Di `update()`: setelah save, fire `Activity::log('user.approve', "Approved user {$user->name}")` jika status berubah pending→active.
  - Di `destroy()`: log "Deleted user {$user->email}".
  - Tambah `description` ringkas untuk global activity feed.
- **Files:** `api/app/Http/Controllers/UserSettingsController.php`, `api/app/Models/Activity.php`.

### CP-16.M4 — CSRF rotation on login (refetch on 419 from auth endpoints)
- **Problem:** `Auth::login` + `$request->session()->regenerate()` invalidate token lama. Frontend cache stale → next request 419. Existing 419 retry handles, tapi retry mungkin gagal jika session ID berbeda.
- **Plan:**
  - Di `apiFetch`, on 419: reset cache + refetch + retry (existing). Untuk endpoint auth (`/login`, `/register`, `/logout`), skip retry (auth failure bukan CSRF).
  - Detect pattern: 419 dari `/login`/`/register`/`/logout` → throw `ApiError("Sesi berakhir, silakan muat ulang halaman")` instead of retry.
- **Files:** `web/src/lib/api.ts`.

---

## CP-17 — Low Priority Polish (4 items)

### CP-17.L1 — Dark mode default persistence
- **Problem:** `localStorage.theme` ada, tapi first-time visitor default `light` (no FOUC mitigation, flicker on load).
- **Plan:**
  - Di `web/src/app/layout.tsx`, inject inline `<script>` SEBELUM React hydration: read `localStorage.theme` atau `prefers-color-scheme`, set `<html data-theme>` synchronously.
  - Prevents white flash on dark mode preference.
- **Files:** `web/src/app/layout.tsx`.

### CP-17.L2 — Settings tabs active state for provider/users
- **Problem:** `settings-tab-profile` active = brand color, tapi `settings-tab-provider` dan `settings-tab-users` tidak punya active state styling. Audit.
- **Plan:**
  - Inspect `web/src/app/(app)/settings/layout.tsx` atau `web/src/components/SettingsTabs.tsx` (whichever exists).
  - Apply same active state pattern as profile tab.
- **Files:** `web/src/app/(app)/settings/layout.tsx` atau equivalent.

### CP-17.L3 — Activity log for register/login events
- **Problem:** `Registered` event fired tapi tidak log ke `Activity`. Useful untuk admin audit.
- **Plan:**
  - Listener `LogRegisteredActivity` di `app/Listeners/LogRegisteredActivity.php`.
  - Login event: `Illuminate\Auth\Events\Login` → log "user logged in".
  - Failed login: `Illuminate\Auth\Events\Failed` → log dengan IP + email (no password).
- **Files:** `api/app/Listeners/LogRegisteredActivity.php`, `api/app/Providers/EventServiceProvider.php`.

### CP-17.L4 — Self-service password change from profile
- **Problem:** Profile punya field password tapi flow-nya admin-set via Settings → Users. User-facing change password lebih baik UX.
- **Plan:**
  - Sudah ada `PATCH /api/settings/profile` accept `password` + `password_confirmation` (verified).
  - Frontend: tambah section "Ganti Password" di profile page dengan current password (verify) + new + confirm.
  - Endpoint `POST /api/settings/profile/change-password` atau extend existing `PATCH`.
- **Files:** `web/src/app/(app)/settings/profile/page.tsx`, `api/app/Http/Controllers/ProfileController.php`.

---

## CP-18 — Feature Baru (4 items)

### CP-18.F1 — Two-factor authentication (2FA) for admin
- **Value:** Admin compromise = full takeover. 2FA = critical security.
- **Plan:**
  - Add `google2fa-laravel` or `pragmarx/google2fa` package.
  - User model: add `two_factor_secret`, `two_factor_confirmed_at`, `two_factor_recovery_codes`.
  - Setup flow: admin scan QR → enter 6-digit code → confirm. Recovery codes generated.
  - Login flow: jika admin + 2FA enabled, setelah password verify, redirect ke `/login/2fa` untuk input TOTP.
  - Disable flow: dari settings/profile.
  - Middleware `two.factor` di routes.
- **Files:** `api/composer.json` (new dep), migration, `api/app/Http/Controllers/Auth/TwoFactorController.php`, `web/src/app/(auth)/login/2fa/page.tsx`, `web/src/app/(app)/settings/profile/page.tsx` (2FA section).

### CP-18.F2 — Bulk user management
- **Value:** Hemat waktu saat onboard/offboard team.
- **Plan:**
  - Frontend: checkbox per row, "Select all" header, bulk action bar (approve/reject/delete).
  - Backend: extend `PATCH /api/settings/users` atau new `POST /api/settings/users/bulk-action` `{action, user_ids[]}`.
- **Files:** `web/src/app/(app)/settings/users/page.tsx`, `api/app/Http/Controllers/UserSettingsController.php`, `api/routes/api.php`.

### CP-18.F3 — Audit dashboard for admin (/admin/audit)
- **Value:** Single page untuk lihat semua activity global, filter by user/action/date.
- **Plan:**
  - New route `/admin/audit` di settings section (admin only).
  - Reuse `GET /api/activities` (already exists).
  - Frontend: timeline view + filter chips (user, action, date range).
- **Files:** `web/src/app/(app)/admin/audit/page.tsx` (new), reuse existing components.

### CP-18.F4 — Email notification when user status changes
- **Value:** User tahu saat akun approved, tanpa harus refresh login page.
- **Plan:**
  - `Illuminate\Auth\Events\Registered` → send "Akun berhasil didaftarkan, menunggu persetujuan".
  - Custom event `UserStatusChanged` di `UserSettingsController::update` → send "Akun kamu sudah aktif".
  - Use Laravel `Notifications` + `MailMessage`.
  - Dev: log driver. Prod: SMTP env config.
- **Files:** `api/app/Notifications/UserApprovedNotification.php`, `api/app/Events/UserStatusChanged.php`, listener, env config.

---

## Execution Order

```
CP-15.H1 → CP-15.H2 → CP-15.H3 → CP-16 checkpoint
            ↓
CP-16.M1 → CP-16.M2 → CP-16.M3 → CP-16.M4 → CP-16 checkpoint
            ↓
CP-17.L1 → CP-17.L2 → CP-17.L3 → CP-17.L4 → CP-17 checkpoint
            ↓
CP-18.F1 → CP-18.F2 → CP-18.F3 → CP-18.F4 → CP-18 checkpoint
            ↓
        Final sign-off
```

## Verify Protocol (per CP)

```bash
# Backend
docker exec aiplanstudio_apifpm php artisan test 2>&1 | tail -5

# Frontend
cd web && npx tsc --noEmit
cd web && npm run lint

# Manual smoke (where applicable)
curl -sS https://api-aiplanstudio.arsyiladm.my.id/api/csrf-token
```

Expected: backend 261+ pass (existing flake OK), frontend tsc 0, lint ≤ pre-existing baseline.

---

## Progress Log

_(Append entry per CP completion, urut kronologis. Format: `### CP-X — YYYY-MM-DD`.)_

### CP-15 — 2026-08-15 · High Priority UX Safety ✅
- **Status:** done
- **Commit:** `<fill at commit>`
- **Items:** 3/3
  - **H1 Audit destructive confirm patterns**: ✅ no-op. `ConfirmDialog` component (`web/src/components/ui/ConfirmDialog.tsx`) sudah reusable dan dipakai di 5 tempat: `projects/page.tsx`, `projects/[id]/page.tsx`, `projects/archived/page.tsx`, `templates/page.tsx`, `ApiTokenSection.tsx`. Zero `window.confirm()` di codebase. Audit complete.
  - **H2 Last-admin warning banner**: ✅ done. Tambah `activeAdminCount` derived state + ShieldAlert banner (warning color) di header card. Disable button dengan title tooltip "Tidak bisa hapus admin terakhir" saat admin tersisa 1. Subtitle count admin aktif di header.
  - **H3 Auto-refresh pending count**: ✅ done. Polling `GET /api/settings/users` setiap 30s via `setInterval`. `useRef` untuk track previousPendingCount tanpa stale closure. Detect increment > set banner toast "🔔 N permintaan persetujuan baru masuk". Cleanup interval on unmount.
- **Verify:** `npx tsc --noEmit` clean; `npm run lint` no new errors (2 pre-existing CommandPalette + 4 pre-existing unused-import warnings); `php artisan test` 261 pass + 1 Socialite flake (unchanged); web rebuilt + smoke `/settings/users` 200 OK.
- **Files touched:**
  - `web/src/app/(app)/settings/users/page.tsx` (H2 + H3)
  - `docs/plan/master-plan-v2.md` (this entry)

---

_(Lanjut ke CP-16.)_
