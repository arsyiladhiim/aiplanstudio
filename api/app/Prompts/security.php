<?php

return fn (string $target) => 'Anda appsec engineer. Buat SECURITY CHECKLIST production-ready dalam format Markdown. Bisa langsung dijalankan sebagai acceptance test security sebelum rilis. Simpan di repo root sebagai `security-checklist.md`.

# SECURITY CHECKLIST — <NAMA_PROYEK>

## 1. Autentikasi & Session
- Password: min 8, hashed (bcrypt default), rate-limit login per user (bukan per IP) — blokir brute force.
- Session: HttpOnly + Secure cookie, SameSite sesuai kebutuhan cross-origin (None bila API subdomain), expire.
- CSRF: aktif untuk semua POST/PATCH/DELETE; token masuk header (X-CSRF-TOKEN).
- 2FA untuk admin (bila app punya role admin).
- Logout invalidate session + regenerate token.

## 2. Otorisasi
- Setiap endpoint cek ownership `where(user_id, ...)` — jangan derive owner dari client-supplied value.
- Policy/FormRequest untuk tiap resource; deny by default.
- RBAC bila ada peran; blokir admin-only route di middleware.

## 3. Input Validation & Injection
- Validasi server-side di SEMUA trust boundary (form, upload, webhook, header).
- Parameterized query / ORM — TIDAK pernah interpolasi user input ke SQL.
- SSRF protection untuk fitur yang fetch URL eksternal (block internal IP).
- File upload: whitelist type + size limit + simpan di luar public dir.

## 4. XSS & Output Encoding
- Escape semua output; jangan render user HTML mentah; gunakan markdown library aman.
- CSP header strict; nonce bila inline script dibutuhkan.

## 5. Data Protection & Privacy
- Kolom sensitif (token/secret) encrypted at rest; mask di response.
- PII: minimalkan collection; retensi & penghapusan.
- Backup terenkripsi.

## 6. Dependencies & Secrets
- `composer audit` / `npm audit` clean sebelum rilis; pin versi mayor.
- Secret TIDAK pernah di commit; scan history.
- `.env` di .gitignore; `.env.example` placeholder saja.

## 7. Transport & Headers
- HTTPS/TLS (Cloudflare + HSTS preload).
- Security headers: X-Frame-Options DENY, X-Content-Type-Options nosniff, Referrer-Policy, Permissions-Policy.

## 8. Rate Limiting & Abuse
- Throttle per-user pada login/register/forgot-password; per-route untuk AI/expensive endpoint.
- Webhook signature verification (HMAC + timestamp + replay protection).

## 9. Checklist Verifikasi Akhir (untuk dijalankan sebelum release)
- [ ] `composer audit` + `npm audit` = 0 critical
- [ ] Test negatif: akses resource user lain → 403/404 (bukan 200)
- [ ] Skip CSRF/test tanpa cookie → 419
- [ ] Upload type tidak wajar tertolak
- [ ] Secret scan di git history = bersih
- [ ] Login brute-force: 5x gagal → blocked

VERIFY sebelum respond: checklist bisa dieksekusi satu per satu untuk app ini? Semua kontrol relevan dengan stack (Laravel Sanctum cookie + Next.js + PostgreSQL)?

VERIFY STRUKTUR (validator backend enforce — section heading WAJIB ada):
1. 9 heading "## N." ada: ## 1. Autentikasi, ## 2. Otorisasi, ## 3. Input Validation, ## 4. XSS, ## 5. Data Protection, ## 6. Dependencies, ## 7. Transport, ## 8. Rate Limiting, ## 9. Checklist.
2. Setiap section WAJIB punya actionable control (bukan teori).
3. Checklist Verifikasi Akhir (## 9.) minimal 6 item `- [ ]` applicable untuk stack ini.
4. Kontrol WAJIB applicable untuk Laravel Sanctum cookie + Next.js + PostgreSQL.
';
