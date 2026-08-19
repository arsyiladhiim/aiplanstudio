<?php

return fn (string $target) => 'Anda DevOps engineer. Buat dokumen ENV/CONFIG lengkap dalam format Markdown. Dokumen ini WAJIB terdaftar di repo root sebagai `env-config.md` dan dipakai user untuk mengisi `.env` production — tanpa env yang benar, app tidak bisa jalan.

# ENV/CONFIG — <NAMA_PROYEK>

## 1. Pendahuluan
- Tujuan: enumerasi SEMUA environment variable yang dibutuhkan app ini (web + mobile bila target=both), nilai contoh dev, dan nilai produksi.
- Platform target: <target> (web / web+mobile).

## 2. Environment Variables (Backend — Laravel)
Tabel per variabel: `Nama` | `Wajib?` | `Nilai Dev` | `Nilai Prod` | `Keterangan`.

WAJIB sertakan minimal:
- `APP_NAME`, `APP_ENV=production`, `APP_KEY`, `APP_DEBUG=false`, `APP_URL` (https), `FRONTEND_URL`
- `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_DRIVER=database`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_DOMAIN`
- `SANCTUM_STATEFUL_DOMAINS`, `SANCTUM_STATEFUL_PORTS` (bila perlu)
- `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST`, `REDIS_PASSWORD`
- `MAIL_MAILER=smtp`, `MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM`
- `GOOGLE_REDIRECT_URI` (OAuth), `GOOGLE_CLIENT_ID/SECRET`
- Setiap integrasi eksternal yang dipakai app (payment, storage, SMS, dsb) → variabelnya WAJIB dicantumkan.

## 3. Environment Variables (Frontend — Next.js)
- `NEXT_PUBLIC_API_URL` (URL absolut API Laravel, cross-origin) — WAJIB.
- Variabel `NEXT_PUBLIC_*` lainnya sesuai kebutuhan app (analytics, feature flag, dsb).

## 4. Environment Variables (Mobile — Flutter) [hanya target=both]
- `--dart-define=API_BASE_URL=<api_domain>` (bukan localhost, pakai HTTPS production)
- `--dart-define=APP_ENV=production`
- Firebase/FCM key bila app pakai push notif; signing keystore path+password.

## 5. File .env & .env.example
- Tulis blok `.env.example` lengkap berisi SEMUA variabel di atas dengan nilai placeholder.
- Aturan: `.env.example` WAJIB di-commit; `.env` WAJIB di-.gitignore; secret TIDAK pernah di-commit.

## 6. Secrets Management (rekomendasi)
- Jangan hardcode secret di kode/config. Pakai env di server + vault/buit-in secret store bila tersedia.
- Rotasi: kapan dan bagaimana rotasi APP_KEY, DB_PASSWORD, OAuth secret.

## 7. Checklist Verifikasi
- [ ] `.env.example` ada di repo root dan synced dengan kode
- [ ] Semua variabel punya nilai prod && nilai dev yang berbeda masuk akal
- [ ] Tidak ada secret aktual di repo (grep .env / commit scan)
- [ ] `APP_DEBUG=false` saat produksi

VERIFY sebelum respond: apakah SEMUA variabel yang digunakan di kode backend/frontend/mobile tercantum? Apakah bisa langsung diisi ke .env dan `php artisan key:generate`?

VERIFY STRUKTUR (validator backend enforce — section heading + .env.example block WAJIB ada):
1. Heading "## 1. Pendahuluan", "## 2. Environment Variables (Backend", "## 3. Environment Variables (Frontend", "## 5. File .env & .env.example", "## 7. Checklist Verifikasi" WAJIB ada.
2. Code fence .env.example (```env atau ```bash atau ```dotenv) WAJIB ada di section 5.
3. .env.example WAJIB punya minimal 8 variabel (KEY=value format).
4. WAJIB ada referensi ke APP_KEY, DB_PASSWORD, APP_URL, SESSION_DOMAIN (variabel backend kritis).
5. Checklist Verifikasi di section 7 minimal 3 item `- [ ]`.
';
