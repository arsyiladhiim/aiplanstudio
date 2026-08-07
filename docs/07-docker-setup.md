# 07 — Docker Setup

> Lihat juga: [02-architecture](02-architecture.md) · [09-roadmap](09-roadmap.md) · [11-development-rules](11-development-rules.md)
> Aturan mutlak: **semua service di Docker**, antar-service via nama container, **hanya nginx expose ke host**. BFF pattern: nginx → Next.js → Laravel.
> Requirement: host Docker **Linux containers** (image php/node/postgres semuanya Linux; Windows-only daemon tidak bisa build).

## Struktur Repo
```
aiplanstudio/
  docker-compose.yml
  .dockerignore
  docker/
    nginx/default.conf        # nginx utama (expose ke host, ke web)
    api-nginx/default.conf    # nginx front untuk Laravel (php-fpm upstream)
    postgres/data_/           # bind mount data PostgreSQL
    redis/data/               # bind mount data Redis
  api/                        # Laravel
    Dockerfile                # php:8.3-fpm-alpine, CMD php-fpm -F (RS-9 ✅)
    .env.production.example   # template env produksi (termasuk SMTP)
  web/                        # Next.js (BFF)
    Dockerfile                # 3-stage build → standalone output
  docs/                       # dokumentasi ini
```

## Topologi Service
```
Host :4197
  └─ nginx (nginx:alpine) ── / → web:3000 (Next.js standalone, BFF)
                                └─ /api/* → Laravel via BFF route handler
  web (Next.js) ── LARAVEL_URL=http://api:8000 ──> api
  api (nginx:alpine :8000, root /app/public) ── fastcgi ──> api-fpm:9000
  api-fpm (php:8.3-fpm-alpine, php-fpm -F, /app) ──> db / redis
  migrate (one-shot, image yang sama dengan api)
  db (postgres:16-alpine) · redis (redis:alpine)
  glitchtip (glitchtip/glitchtip:6, :8000 internal) ──> db (DB `glitchtip`) / redis (DB 2)
```

## docker-compose.yml (ringkas)
```yaml
services:
  nginx:                    # SATU-SATUNYA publish ke host
    image: nginx:alpine
    ports: ["4197:80"]
    volumes: [./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro, ./api/storage/logs:/var/log/nginx]
    depends_on: { web: { condition: service_started }, api: { condition: service_healthy } }

  web:                      # Next.js (BFF) — standalone, user nextjs
    build: { context: ./web, dockerfile: Dockerfile }
    expose: ["3000"]
    environment: [LARAVEL_URL=http://api:8000]

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

  glitchtip:
    image: glitchtip/glitchtip:6
    expose: ["8000"]
    environment: { DATABASE_URL: postgres://{POSTGRES_USER}:{POSTGRES_PASSWORD}@db:5432/glitchtip, REDIS_URL: redis://:{REDIS_PASSWORD}@redis:6379/2, SECRET_KEY: ${GLITCHTIP_SECRET_KEY}, EMAIL_URL: consolemail://, GLITCHTIP_DOMAIN: http://glitchtip:8000, DEFAULT_FROM_EMAIL: glitchtip@localhost, SERVER_ROLE: all_in_one }
    volumes: [glitchtip-uploads:/code/uploads]

networks: { aiplanstudio: { driver: bridge } }
volumes: { glitchtip-uploads: }
```

## nginx/default.conf (nginx utama)
- gzip, `client_max_body_size 20m`, security headers (CSP, HSTS, X-Frame-Options).
- Semua request `location /` → `proxy_pass http://web:3000`, `proxy_buffering off`, `proxy_read_timeout 86400` (SSE).
- Semua `/api/*` dan `/sanctum/*` ditangani Next.js BFF (route `src/app/api/**`).

## docker/api-nginx/default.conf (nginx api)
- `root /app/public; index index.php` → `fastcgi_pass api-fpm:9000`, `fastcgi_buffering off` (SSE).
- `try_files $uri $uri/ /index.php?$query_string`; blok `.php$`; deny dotfiles.

## Env
- **`api/.env`** (Laravel, dibaca langsung oleh `api-fpm` — **tanpa** `env_file`, agar PHPUnit bisa meng-override `DB_*` untuk test DB): `APP_KEY` (wajib `php artisan key:generate`), `DB_HOST=db`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST=redis`, `REDIS_PASSWORD`, `SESSION_DRIVER=database`, `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, `APP_URL`, `FRONTEND_URL`, blok `MAIL_*`.
- **`api/.env.production.example`**: template produksi lengkap — `APP_ENV=production`, `APP_URL`/`FRONTEND_URL` https publik, `SANCTUM_STATEFUL_DOMAINS` + `SESSION_DOMAIN` domain produksi, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `GOOGLE_REDIRECT_URI`, SMTP (`MAIL_MAILER=smtp`, `MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION/FROM`), `LOG_LEVEL=error`.
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

# 5. Antar-service via hostname (uji BFF)
curl http://localhost:4197/api/health                 # nginx → web → api → "ok"
docker compose exec web wget -qO- http://api:8000/api/health  # internal OK
```

## Checklist Keamanan Infra
- [x] Hanya `nginx` punya `ports:` (host `:5432`/`:8000`/`:3000`/`:9000` tertutup).
- [x] `db`, `api`, `api-fpm`, `web`, `redis`, `glitchtip` tanpa `ports:`.
- [x] `migrate` one-shot (`restart: "no"`), depend `api-fpm` menunggu `service_completed_successfully`.
- [x] Healthcheck di `db` (`pg_isready`) & `api` (wget `/api/health`).
- [x] `mem_limit` di semua service; `restart: unless-stopped` untuk daemon.
- [x] Rahasia via env (`.env`/`.env.production`), tidak di-commit; `REDIS_PASSWORD` via compose.
- [x] BFF: semua request masuk via nginx → Next.js, tidak ada route langsung ke Laravel.

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

# 4. Akses
# Frontend: http://localhost:3000
# API:      http://localhost:8000/api/health → {"status":"ok"}
# BFF:      http://localhost:3000/api/health → {"status":"ok"} (via proxy Next.js)
```

### Catatan
- `APP_DEBUG=true` di `.env` untuk melihat stack trace saat development.
- User pertama yang register otomatis jadi admin.
- Pipeline SSE streaming kompatibel dengan `php artisan serve`.
- Untuk beralih ke Docker, cukup setel ulang `.env` (lihat bagian Docker Setup di atas) — Docker butuh host dengan **Linux containers**.
