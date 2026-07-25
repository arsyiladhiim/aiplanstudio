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

## B. Autentikasi — Bearer Token (PersonalAccessTokens)
- [x] Auth menggunakan **Sanctum PersonalAccessTokens** — Bearer token, bukan session cookie.
- [x] Token expiry: **120 menit** (`sanctum.expiration = 120`).
- [x] Token tersimpan di **sessionStorage** (client-side, hilang saat tab ditutup).
- [x] Tidak ada CSRF — Browser tidak otomatis mengirim Authorization header.
- [x] Password hash **bcrypt** (bawaan Laravel); tak pernah simpan plaintext.
- [x] Logout **revoke token** — token tidak bisa dipakai lagi.
- [x] `HasApiTokens` trait di User model, `personal_access_tokens` table ter-migrate.

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

## E. Input & Output
- [x] Semua input divalidasi (FormRequest/Validator) di trust boundary.
- [x] Output AI (JSON `erd`/`phases`) divalidasi sebelum simpan/render (anti-sampah/injection).
- [x] Render markdown aman (react-markdown, tanpa `dangerouslySetInnerHTML`).
- [x] Query pakai Eloquent/binding (tak ada SQL string concat) — anti SQL injection.
- [x] Error ke client tak bocorkan stack/detail sensitif (`APP_DEBUG=false` di non-lokal).

## F. Transport & Header
- [ ] (Produksi) HTTPS + redirect http→https.
- [x] Header keamanan via nginx: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Content-Security-Policy`, `Strict-Transport-Security`.
- [x] CORS: karena same-origin via BFF, tidak perlu CORS.
- [x] SSE header aman (`X-Accel-Buffering: no`) tanpa membocorkan info.
- [x] Tidak ada cookie session — tidak perlu `SameSite`/`Secure` cookie config.

## G. Dependency & Build
- [ ] `composer audit` / `npm audit` bersih dari kerentanan kritikal.
- [x] Tak ada dependency tak terpakai (VerifyServiceToken, SERVICE_TOKEN dihapus).
- [x] `.dockerignore`/`.gitignore` mengecualikan `node_modules`, `vendor`, `.env`, volume.

## H. Network Level (Laravel hanya accessible dari BFF)
- [x] Laravel expose port 8000 **internal** (tidak ada `ports:` di docker-compose).
- [x] Docker network `aistack`: hanya service `web` dan `nginx` yang resolve ke `api`.
- [x] Dari host, `curl localhost:8000` → **connection refused** (tidak ada port mapping).
- [x] Dari host ke Laravel harus lewat nginx → Next.js (BFF) → Laravel.

## Verifikasi Cepat
```bash
docker compose ps                       # hanya nginx publish (80:80)
docker compose exec web wget -qO- http://api:8000/api/health   # internal OK
# dari host, Laravel TIDAK reachable langsung:
curl localhost:8000                     # connection refused ✅
psql -h localhost -p 5432               # connection refused ✅
curl http://localhost/api/user           # 401 (no Bearer token) ✅
curl -H "Authorization: Bearer test" http://localhost/api/user # 401 (invalid token) ✅
```
