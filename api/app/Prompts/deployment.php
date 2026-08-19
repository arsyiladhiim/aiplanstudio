<?php

return fn (string $target) => 'Anda DevOps engineer. Buat DEPLOYMENT GUIDE production-ready dalam format Markdown untuk app ini. Simpan di repo root sebagai `deployment.md`. Panduan harus bisa diikuti engineer yang baru tanpa asumsi konteks.

# DEPLOYMENT GUIDE — <NAMA_PROYEK>

## 1. Prerequisites
- VPS Linux (Ubuntu 22.04+), Docker + Docker Compose v2, domain + DNS A/AAAA record.
- Cloudflare Tunnel terpasang (`cloudflared`) untuk ekspos service tanpa buka port publik.
- TLS via Cloudflare (Full/Strict); origin pakai self-signed/ACME bila perlu.

## 2. Topology (7 service, mode internal — tidak publish port)
- `<slug>_web` (Next.js standalone), `<slug>_nginx_api` (nginx → php-fpm), `<slug>_apifpm` (Laravel + PHP-FPM), `<slug>_db` (PostgreSQL), `<slug>_redis` (Redis), `cloudflare_tunnel_default` (2 ingress: web + api).
- TIDAK ada nginx host di luar tunnel; Cloudflare jadi reverse proxy eksternal.
- Mobile (bila target=both): build APK via CI, unduh ke device; tidak ada container mobile.

## 3. Environment
- Copy `.env.example` → `.env`; isi nilai produksi (DB, Redis, APP_URL https, SESSION_DOMAIN, FRONTEND_URL, MAIL, OAuth).
- `php artisan key:generate` (APP_KEY).
- Diff dengan dev: APP_ENV=production, APP_DEBUG=false, SESSION_SECURE_COOKIE=true.

## 4. Build & Start
```bash
docker compose build --no-cache
docker compose up -d
docker compose exec apifpm php artisan migrate --force
docker compose exec apifpm php artisan storage:link
docker compose exec apifpm php artisan config:cache
docker compose exec apifpm php artisan route:cache   # bila route stabil
```
- Frontend: `npm run build` di image web; serve `node server.js` (standalone).

## 5. Cloudflare Tunnel
- Ingress 1: `<domain>` → `http://<slug>_web:3000`
- Ingress 2: `api.<domain>` → `http://<slug>_nginx_api:8000`
- Pastikan origin (Laravel) pakai trusted proxies agar client IP + scheme benar.

## 6. Backup (wajib otomatis)
- Cron `pg_dump` harian → encrypted offsite. Simpan retensi 7/30/365 hari.
- Test restore: restore ke DB temp, `SELECT 1` + count row → verifikasi TIDAK hanya file ada.

## 7. Rollback
- Tag image tiap rilis: `docker compose build` reproducible → revert image tag bila gagal.
- DB: migrasi reversible; bila perlu, `migrate:rollback` sebelum revert kode.

## 8. Zero-Downtime (bila perlu)
- Blue/green: deploy ke stack baru, switch tunnel ingress, matikan lama setelah health OK.
- Atau rolling: rebuild service satu per satu (`docker compose up -d --no-deps <svc>`).

## 9. Post-Deploy Verification
- `GET /api/health` → 200.
- Login end-to-end (browser) → dashboard load.
- Webhook endpoint reachable dari Cloudflare.

## 10. Monitoring (lihat observability.md)
- Health check + Sentry + uptime alert → notifikasi ke kanal tim.

VERIFY sebelum respond: langkah 4 bisa dijalankan tanpa port terbuka ke publik? Apakah backup punya restore-verify (bukan sekadar file)?

VERIFY STRUKTUR (validator backend enforce — section heading WAJIB ada):
1. 10 heading "## N." ada: ## 1. Prerequisites, ## 2. Topology, ## 3. Environment, ## 4. Build & Start, ## 5. Cloudflare Tunnel, ## 6. Backup, ## 7. Rollback, ## 8. Zero-Downtime, ## 9. Post-Deploy, ## 10. Monitoring.
2. Topology (## 2.) WAJIB sebutkan 7 service (web, nginx_api, apifpm, db, redis, tunnel — dengan slug).
3. Backup (## 6.) WAJIB ada `pg_dump` cron + restore-verify (BUKAN hanya "file ada").
4. Post-Deploy Verification (## 9.) WAJIB ada cek `/api/health` + login end-to-end.
';
