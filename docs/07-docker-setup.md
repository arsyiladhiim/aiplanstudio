# 07 — Docker Setup

> Lihat juga: [02-architecture](02-architecture.md) · [09-roadmap](09-roadmap.md) · [11-development-rules](11-development-rules.md)
> Aturan mutlak: **semua service di Docker**, antar-service via nama container, **tidak ada publish port ke host**. Reverse proxy = Cloudflare Tunnel (external). Arsitektur direct routing: `aiplanstudio_web:3000` (Next.js) + `aiplanstudionginx_api:8000` (Laravel via nginx → php-fpm). See [25-bypass-bff.md](25-bypass-bff.md).
> Requirement: host Docker **Linux containers** (image php/node/postgres semuanya Linux; Windows-only daemon tidak bisa build).

## Struktur Repo
```
aiplanstudio/
  docker-compose.yml
  .dockerignore
  docker/
    api-nginx/default.conf    # nginx front untuk Laravel (php-fpm upstream) — di belakang Cloudflare Tunnel
    postgres/data_/           # bind mount data PostgreSQL
    redis/data/               # bind mount data Redis
  api/                        # Laravel
    Dockerfile                # php:8.3-fpm-alpine, CMD php-fpm -F (RS-9 ✅)
    .env.example              # template env (dokumentasi; salin jadi .env untuk dev/produksi)
  web/                        # Next.js (standalone, direct API call)
    Dockerfile                # 3-stage build → standalone output
  docs/                       # dokumentasi ini
```

## Topologi Service
```
Cloudflare Tunnel (external reverse proxy)
  ├─ aiplanstudio.arsyiladm.my.id      → http://aiplanstudio_web:3000       (Next.js standalone)
  └─ api-aiplanstudio.arsyiladm.my.id  → http://aiplanstudionginx_api:8000 (Laravel via nginx → php-fpm)

aiplanstudio_web       (Next.js standalone)            :3000 internal — tidak publish ke host
aiplanstudionginx_api  (nginx:alpine, root /app/public):8000 internal — tidak publish ke host
aiplanstudio_apifpm    (php-fpm -F)                    :9000 internal
aiplanstudio_db        (postgres:16-alpine)            :5432 internal
aiplanstudio_redis     (redis:alpine)                  :6379 internal
glitchtip              (glitchtip/glitchtip:6, :8000)  — **DISABLED** (service di-comment)
```

## docker-compose.yml (ringkas)
```yaml
services:
  nginx:                    # SATU-SATUNYA publish ke host
    image: nginx:alpine
    ports: ["4197:80"]
    volumes: [./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro, ./api/storage/logs:/var/log/nginx]
    depends_on: { web: { condition: service_started }, api: { condition: service_healthy } }

  web:                      # Next.js — standalone, user nextjs
    build: { context: ./web, dockerfile: Dockerfile, args: { NEXT_PUBLIC_API_URL } }
    expose: ["3000"]
    # Browser fetch direct ke Laravel via NEXT_PUBLIC_API_URL (no BFF).

  api:                      # nginx front untuk Laravel
    image: nginx:alpine
    expose: ["8000"]
    volumes: [./docker/api-nginx/default.conf:ro, ./api/public:/app/public:ro]
    depends_on: [api-fpm]
    healthcheck: wget http://localhost:8000/api/health

  api-fpm:                  # php-fpm Laravel
    build: { context: ., dockerfile: api/Dockerfile }
    image: aiplanstudio-api
    expose: ["9000"]
    env_file: ./api/.env
    working_dir: /app
    volumes: [./api:/app]
    depends_on: { db: { condition: service_healthy }, migrate: { condition: service_completed_successfully } }

  migrate:                  # one-shot
    image: aiplanstudio-api
    command: ["php", "artisan", "migrate", "--force", "--no-interaction"]
    depends_on: { db: { condition: service_healthy } }
    restart: "no"

  db:                       # TANPA ports — tidak diekspos ke host
    image: postgres:16-alpine
    environment: { POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD }
    volumes: [./docker/postgres/data_:/var/lib/postgresql/data]
    healthcheck: pg_isready

  redis:
    image: redis:alpine
    command: ["redis-server", "--requirepass", "${REDIS_PASSWORD}"]
    expose: ["6379"]

  # glitchtip: — DISABLED. Service di-comment di docker-compose.yml.
  #   image: glitchtip/glitchtip:6
  #   expose: ["8000"]
  #   environment: { DATABASE_URL: postgres://{POSTGRES_USER}:{POSTGRES_PASSWORD}@db:5432/glitchtip, REDIS_URL: redis://:{REDIS_PASSWORD}@redis:6379/2, SECRET_KEY: ${GLITCHTIP_SECRET_KEY}, EMAIL_URL: consolemail://, GLITCHTIP_DOMAIN: http://glitchtip:8000, DEFAULT_FROM_EMAIL: glitchtip@localhost, SERVER_ROLE: all_in_one }
  #   volumes: [glitchtip-uploads:/code/uploads]

networks: { aiplanstudio: { driver: bridge } }
```

## api-nginx/default.conf (nginx front Laravel — di belakang Cloudflare Tunnel)
- gzip, `client_max_body_size 20m`, security headers (CSP `frame-ancestors 'none'`, HSTS, X-Frame-Options DENY, Permissions-Policy, Referrer-Policy, X-Content-Type-Options).
- Rate limiting: `limit_req_zone api_limit 30r/s`, `csrf_limit 5r/s`.
- Block hidden files (`.env`, `composer.json`, dll) sebagai defense-in-depth.
- Listen 8000 (local dev) + 80 (Cloudflare Tunnel default).
- Semua `/api/*` + `/sanctum/*` → `fastcgi_pass aiplanstudio_apifpm:9000`.

## docker/api-nginx/default.conf (nginx api)
- `root /app/public; index index.php` → `fastcgi_pass api-fpm:9000`, `fastcgi_buffering off` (SSE).
- `try_files $uri $uri/ /index.php?$query_string`; blok `.php$`; deny dotfiles.

## Env
- **`api/.env`** (Laravel, dibaca langsung oleh `aiplanstudio_apifpm` — **tanpa** `env_file`, agar PHPUnit bisa meng-override `DB_*` untuk test DB): `APP_KEY` (wajib `php artisan key:generate`), `DB_HOST=db`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST=redis`, `REDIS_PASSWORD`, `SESSION_DRIVER=database`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `APP_URL`, `FRONTEND_URL`, blok `MAIL_*`.
- **`api/.env.example`**: template env (dokumentasi) — salin jadi `.env`, lalu isi. Catatan produksi: `APP_ENV=production`, `APP_URL`/`FRONTEND_URL` https publik, `SANCTUM_STATEFUL_DOMAINS` + `SESSION_DOMAIN` domain produksi, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `GOOGLE_REDIRECT_URI`, SMTP (`MAIL_MAILER=smtp`, `MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM`).
- **`web/.env`** (Next.js): `LARAVEL_URL=http://api:8000` di Docker; `http://localhost:8000` saat dev tanpa Docker.
- **`.env` root compose**: `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`, `REDIS_PASSWORD`.

### Trusted Proxies
`api/bootstrap/app.php` sudah set `trustProxies(at: ['api','nginx','localhost','127.0.0.1'], headers: FORWARDED_FOR|HOST|PORT|PROTO)` agar Laravel tahu `https`/host asli di balik nginx (dipakai untuk URL generation & Secure cookies).

### Multi-Schema
Database menggunakan 3 PostgreSQL schema: `aiplanstudio_master`, `aiplanstudio_project`, `aiplanstudio_settings`. `search_path` di `config/database.php` mencakup ketiganya agar mapping model tetap sederhana.

## Perintah Kunci
```bash
# 1. Build & jalankan semua (migrasi jalan otomatis via service `migrate`)
docker compose up -d --build

# 2. Verifikasi healthcheck (api harus healthy)
docker compose ps
docker compose logs api-fpm migrate

# 3. Inisialisasi awal (sekali saja, jika migrate belum jalan)
docker compose run --rm migrate

# 4. Cek tabel
docker compose exec db psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c '\dt'

# 5. Antar-service via hostname (uji direct routing, no BFF)
curl http://localhost:8000/api/health                 # nginx_api → api-fpm → "ok" (direct)
docker compose exec web wget -qO- http://aiplanstudionginx_api:8000/api/health  # internal OK
```

## Catatan Arsitektur Direct Routing
- **Tidak ada BFF layer.** Browser fetch langsung ke `NEXT_PUBLIC_API_URL` (= `http://localhost:8000` dev / `https://api-aiplanstudio.arsyiladm.my.id` prod).
- `web/src/lib/api.ts` pakai `fetch(url, { credentials: "include" })` untuk cookie session + CSRF.
- Sanctum stateful domain (`SANCTUM_STATEFUL_DOMAINS`) di backend allowlist frontend origin.
- CORS allowlist (`api/config/cors.php`) + `supports_credentials: true` untuk cross-origin.
- Detail lengkap migration BFF → direct: `docs/25-bypass-bff.md`.

## Checklist Keamanan Infra
- [x] Tidak ada service yang publish port ke host (`docker compose ps` — semua `0.0.0.0:xxx` kosong); akses publik via Cloudflare Tunnel.
- [x] `aiplanstudio_db`, `aiplanstudionginx_api`, `aiplanstudio_apifpm`, `aiplanstudio_web`, `aiplanstudio_redis` tanpa `ports:` (glitchtip DISABLED — service di-comment).
- [x] `migrate` one-shot (`restart: "no"`), depend `aiplanstudio_apifpm` menunggu `service_completed_successfully`.
- [x] Healthcheck di `aiplanstudio_db` (`pg_isready`) & `aiplanstudionginx_api` (wget `/api/health`).
- [x] `mem_limit` di semua service; `restart: unless-stopped` untuk daemon.
- [x] Rahasia via env (`.env`/`.env.production`), tidak di-commit; `REDIS_PASSWORD` via compose.
- [x] BFF removed (Phase 7): request publik masuk via Cloudflare Tunnel → `aiplanstudio_web:3000` (web) atau `aiplanstudionginx_api:8000` (API), tidak ada reverse-proxy di repo.
- [x] Semua volume pakai bind mount ke `./docker/*/` (postgres, redis, glitchtip) — tidak ada named Docker volume.

### Host Permission (root-owned files)

Beberapa path jadi root-owned karena Docker container menulis ke bind mount. Normal — bukan bug.

| Path | Penanganan |
|------|-----------|
| `web/node_modules/` | JANGAN chown. Gunakan `docker run --rm -v "$PWD/web":/work -w /work node:20-alpine npm <cmd>` untuk install/update. |
| `web/e2e/.auth/`, `web/test-results/` | gitignored; `sudo rm -rf` sebelum e2e Docker run. |
| `api/vendor/`, `api/.phpunit.result.cache`, `api/database/database.sqlite` | gitignored, biarkan. |
| `api/config/*` (dipublish `artisan vendor:publish`) | `sudo chown -R $(id -u):$(id -g) <path>` bila file tracked git. |
| `docker/postgres/data_/`, `docker/redis/data/`, `docker/glitchtip/uploads/` | data container, biarkan root. |

**Password sudo host: `bismillah`** — `echo "bismillah" | sudo -S chown -R $(id -u):$(id -g) <path>`

---

## Development Tanpa Docker

Untuk development cepat, semua service bisa dijalankan langsung di host tanpa Docker (keputusan saat ini: **dev pakai host, Docker untuk produksi**).

### Prasyarat
- PHP 8.3+ dengan ekstensi `pdo_pgsql`, `curl`, `mbstring`, `xml`, `zip`
- Composer (dependency sudah terinstall: `api/vendor/`)
- Node.js 20+ (dependency sudah terinstall: `web/node_modules/`)
- PostgreSQL berjalan di `localhost:5432`
- **Redis tidak diperlukan** — session/cache/queue semua pakai database

### Konfigurasi

**`api/.env`** — sudah di-set untuk development:
```env
APP_ENV=local
APP_KEY=base64:<isi dari php artisan key:generate>
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=aiplanstudio
DB_USERNAME=postgres
DB_PASSWORD=<password postgres>
SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:3000
FRONTEND_URL=http://localhost
# Redis tidak diperlukan (semua driver pakai database)
# REDIS_HOST=localhost
```

**`web/.env`** — dibuat otomatis saat setup:
```env
LARAVEL_URL=http://localhost:8000
```

### Perintah

```bash
# 1. Setup database (cukup sekali)
cd api
php artisan migrate
php artisan storage:link

# 2. Start backend (terminal 1)
cd api
php artisan serve --port=8000

# 3. Start frontend (terminal 2)
cd web
npm run dev

# 4. Akses (direct routing, no BFF)
# Frontend: http://localhost:3000
# API:      http://localhost:8000/api/health → {"status":"ok"}
# Browser → fetch(`${NEXT_PUBLIC_API_URL}/api/...`) dengan credentials: "include"
```

### Catatan
- `APP_DEBUG=true` di `.env` untuk melihat stack trace saat development.
- User pertama yang register otomatis jadi admin.
- Pipeline SSE streaming kompatibel dengan `php artisan serve`.
- Untuk beralih ke Docker, cukup setel ulang `.env` (lihat bagian Docker Setup di atas) — Docker butuh host dengan **Linux containers**.
