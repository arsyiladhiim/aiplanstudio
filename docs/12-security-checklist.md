# 12 — Security Checklist

> Lihat juga: [11-development-rules](11-development-rules.md) · [02-architecture](02-architecture.md) · [04-api-contract](04-api-contract.md)
> Checklist dikerjakan per fase. Tandai `[x]` bila lulus + catat di [15-dev-log](15-dev-log.md).

## A. Infrastruktur & Docker
- [x] Hanya `nginx` yang publish port ke host (`docker compose ps` → nginx:80 saja).
- [x] `db`, `api`, `web`, `redis` **tanpa** `ports:` (host `:5432`/`:3000`/`:8000` tertutup).
- [x] Antar-service via nama container (`db`, `api`, `web`, `redis`), bukan IP/localhost.
- [x] Volume DB baru (fresh `aistack_db` volume).
- [x] `.env` tidak di-commit; hanya `.env.example` (tanpa rahasia).
- [x] Image dasar versi terkunci (`postgres:16-alpine`, `nginx:alpine`, `node:22-alpine`).
- [x] (R1) Hardcoded credentials di docker-compose.yml dipindah ke env — semua `${POSTGRES_*}`/`${REDIS_PASSWORD}` dari `.env` (RS-1 ✅).

## B. Autentikasi — Sanctum SPA Session (HttpOnly Cookie + CSRF)
- [x] Auth menggunakan **Sanctum SPA session** — HttpOnly cookie, bukan Bearer token.
- [x] Session lifetime: **120 menit** (`session.lifetime = 120`).
- [x] Cookie `HttpOnly=true`, `SameSite=Lax` — tidak bisa dibaca JavaScript.
- [x] CSRF aktif: `XSRF-TOKEN` cookie (readable JS) → `X-XSRF-TOKEN` header untuk non-GET.
- [x] Password hash **bcrypt** (bawaan Laravel); tak pernah simpan plaintext.
- [x] Logout **invalidate session** — session cookie tidak bisa dipakai lagi.
- [x] (R1) `SESSION_SECURE_COOKIE=true` di `.env.example` untuk production — dicontohkan di `api/.env.production.example` + catatan production (RS-4 ✅).

## C. Otorisasi (RBAC & kepemilikan)
- [x] Endpoint admin dijaga middleware `role.admin` (uji member → 403).
- [x] Semua query Project/Version difilter `user_id` pemilik (uji akses lintas-user → 403/404).
- [x] Tidak ada IDOR: akses `/api/versions/{id}` milik user lain ditolak.
- [x] Mass-assignment dijaga (`$fillable`/FormRequest), role tak bisa di-set via request biasa.
- [x] GenerateStreamController divalidasi: `version` + `stage` required, stage whitelist.

## D. Rahasia AI Provider
- [x] `ai_providers.api_key` disimpan **encrypted** (cast Laravel `encrypted`).
- [x] Response API selalu **masked** (`sk-...abcd`), tak pernah kirim key mentah.
- [x] Key tak pernah muncul di log, error, atau response SSE.
- [x] Panggilan ke provider hanya dari backend (tak ada API key di client JavaScript).
- [x] (R1) SSRF mitigation: validasi `base_url` tidak指向 internal IP — `AiClient::validateBaseUrl()` (RS-3 ✅).

## E. Input & Output
- [x] Semua input divalidasi (Validator) di trust boundary.
- [x] Output AI (JSON `erd`/`phases`/`master`) divalidasi sebelum simpan (anti-sampah/injection).
- [x] Render konten aman (React auto-escape, tanpa `dangerouslySetInnerHTML`).
- [x] Query pakai Eloquent/binding (tak ada SQL string concat) — anti SQL injection.
- [x] Error ke client tak bocorkan stack/detail sensitif (`APP_DEBUG=false` di non-lokal).
- [x] (R1) Error handling: batasi error message exposure di `api.ts` — parse JSON response dulu, fallback generic (RS-5 ✅).

## F. Transport & Header
- [ ] (Produksi) HTTPS + redirect http→https.
- [x] Header keamanan via nginx: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Content-Security-Policy`, `Strict-Transport-Security`, `Permissions-Policy`.
- [x] CORS: karena same-origin via BFF, tidak perlu CORS.
- [x] SSE header aman (`X-Accel-Buffering: no`) tanpa membocorkan info.
- [x] Session cookie HttpOnly + SameSite=Lax.

## G. Dependency & Build
- [x] `composer audit` / `npm audit` bersih dari kerentanan kritikal.
- [x] Tak ada dependency tak terpakai (VerifyServiceToken, SERVICE_TOKEN dihapus).
- [x] `.dockerignore`/`.gitignore` mengecualikan `node_modules`, `vendor`, `.env`, volume.

## H. Network Level (Laravel hanya accessible dari BFF)
- [x] Laravel expose port 8000 **internal** (tidak ada `ports:` di docker-compose).
- [x] Docker network `aistack`: hanya service `web` dan `nginx` yang resolve ke `api`.
- [x] Dari host, `curl localhost:8000` → **connection refused** (tidak ada port mapping).
- [x] Dari host ke Laravel harus lewat nginx → Next.js (BFF) → Laravel.

## I. Error Monitoring (GlitchTip self-hosted)
- [x] GlitchTip service internal-only (`glitchtip:8000`, tidak di-expose ke host).
- [x] Reuse existing `db` (PostgreSQL DB `glitchtip`) + `redis` (DB index 2) — no extra containers.
- [x] `GLITCHTIP_SECRET_KEY` di root `.env` (tidak di-commit).
- [x] DSN backend (Laravel) via env `SENTRY_LARAVEL_DSN` — internal Docker DNS `glitchtip:8000`.
- [x] DSN frontend server-side (Next.js SSR) via env `SENTRY_DSN` — internal Docker DNS.
- [x] DSN browser-side (`NEXT_PUBLIC_SENTRY_DSN`) di-set → route nginx `/glitchtip` sudah ada (P14).
- [x] SDK no-op bila DSN kosong (`enabled: false`) — dev tanpa GlitchTip tetap aman.
- [x] Tidak ada API key/info sensitif bocor ke GlitchTip (sanitize default Sentry SDK).

## Verifikasi Cepat
```bash
docker compose ps                       # hanya nginx publish (80:80)
docker compose exec web wget -qO- http://api:8000/api/health   # internal OK
# dari host, Laravel TIDAK reachable langsung:
curl localhost:8000                     # connection refused ✅
psql -h localhost -p 5432               # connection refused ✅
curl http://localhost/api/user           # 401 (no session) ✅
# BFF bekerja:
curl -c /tmp/cookies -b /tmp/cookies http://localhost/api/user  # harus login dulu ✅
```
