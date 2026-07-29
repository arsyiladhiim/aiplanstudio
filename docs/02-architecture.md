# 02 — Architecture

> Lihat juga: [07-docker-setup](07-docker-setup.md) · [04-api-contract](04-api-contract.md) · [10-decision-log](10-decision-log.md)

## Ringkasan
- **Backend:** Laravel (REST API + orkestrasi AI pipeline).
- **Frontend:** Next.js (App Router, SPA client).
- **DB:** PostgreSQL.
- **Reverse proxy:** Nginx — satu-satunya service yang expose port ke host.
- **BFF Pattern:** Semua traffic masuk via nginx → Next.js. Next.js proxy `/api/*` ke Laravel internal (`http://api:8000`). Tidak ada route langsung nginx ke Laravel. Semua service jalan di Docker.

## Topologi (BFF Pattern)
```
                    host :80
                       │
                   ┌───▼────┐
                   │ nginx  │   (satu-satunya ports: 80:80)
                   └──┬───┬─┘
           location /  │   │  location /api, /sanctum (ke web:3000)
                       │
               ┌───────▼┐
               │  web   │   Next.js BFF (port 3000 internal)
               └────┬───┘
                    │ proxy /api/* → http://api:8000
               ┌────▼─────────┐
               │     api      │   Laravel php (port 8000 internal)
               └──┬────┬──────┘
            ┌─────▼┐ ┌─▼──────┐
            │  db  │ │ redis  │
            │  pg  │ └────────┘
            └──────┘
                  (tanpa ports host)
```

## Routing Nginx
| Path | Tujuan |
|------|--------|
| `/` | `web:3000` (Next.js) |
| `/_next/*` | `web:3000` (static assets) |
| `/*` (semua) | `web:3000` (BFF handles routing) |

Semua `/api/*`, `/sanctum/*` masuk ke Next.js → Next.js proxy ke Laravel internal.

## Service
| Service | Image/Base | Expose | Publish ke host |
|---------|-----------|--------|-----------------|
| `nginx` | nginx:alpine | 80 | **80:80 (satu-satunya)** |
| `web` | node (Next.js) | 3000 (internal) | tidak |
| `api` | php (Laravel) | 8000 (internal) | tidak |
| `db` | postgres:16-alpine | 5432 (internal) | **tidak** |
| `redis` | redis:alpine | 6379 (internal) | tidak |

## Jaringan
- Satu Docker network internal (`aistack`).
- Referensi antar-service pakai hostname = nama service: `db`, `api`, `web`, `redis`.
- Contoh Laravel `.env`: `DB_HOST=db`, `REDIS_HOST=redis`.
- Contoh nginx: `proxy_pass http://web:3000;` (BFF — semua melalui Next.js).

## Aliran Request Utama (BFF)
1. Browser → `http://localhost/` → nginx → `web` (render UI).
2. Frontend fetch `http://localhost/api/...` (dengan cookie session + CSRF header) → nginx → `web` (BFF) → `api:8000` (Laravel).
3. Pipeline AI: frontend buka **SSE** ke `/api/generate/stream` → Next.js BFF proxy → Laravel → AI Provider streaming → relay token & status per stage ke frontend realtime.

## Keamanan Arsitektur
- Auth: **Sanctum SPA Session** — HttpOnly session cookie + CSRF (`XSRF-TOKEN`).
- CSRF aktif: state-changing requests wajib menyertakan `X-XSRF-TOKEN` header.
- Session lifetime: **120 menit** (konfigurasi di `config/session.php`).
- API key AI Provider disimpan **encrypted di DB**, hanya dipakai backend. Tak pernah dikirim ke client.
- DB/redis/api/web tidak dapat diakses dari host langsung — hanya lewat nginx.
- Nginx menambahkan header keamanan: CSP, HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy.

Detail deployment & perintah: [07-docker-setup](07-docker-setup.md).
