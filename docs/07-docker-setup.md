# 07 — Docker Setup

> Lihat juga: [02-architecture](02-architecture.md) · [09-roadmap](09-roadmap.md) · [11-development-rules](11-development-rules.md)
> Aturan mutlak: **semua service di Docker**, antar-service via nama container, **hanya nginx expose ke host**. BFF pattern: nginx → Next.js → Laravel.

## Struktur Repo
```
aistack/
  docker-compose.yml
  nginx/
    default.conf
  api/                 # Laravel
    Dockerfile
  web/                 # Next.js
    Dockerfile
  docs/                # dokumentasi ini
```

## docker-compose.yml
```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"            # SATU-SATUNYA publish ke host
    volumes:
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on: [web]
    networks: [aistack]

  web:                     # Next.js (BFF)
    build: ./web
    expose: ["3000"]       # internal saja
    depends_on: [api]
    networks: [aistack]

  api:                     # Laravel
    build: ./api
    expose: ["8000"]       # internal saja (bukan 9000)
    depends_on: [db]
    networks: [aistack]

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: aistack
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - aistack_db:/var/lib/postgresql/data
    # TANPA ports: — tidak diekspos ke host
    networks: [aistack]

  redis:
    image: redis:alpine
    networks: [aistack]

  web-test:               # E2E (profile: test)
    build: ./web --target test
    depends_on: [web, api]
    networks: [aistack]
    profiles: ["test"]

volumes:
  aistack_db:

networks:
  aistack:
```

## nginx/default.conf (BFF pattern)
```nginx
server {
  listen 80;
  location / {
    proxy_pass http://web:3000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  }
  location /_next/ {
    proxy_pass http://web:3000;
  }
}
```
Semua `/api/*` dan `/sanctum/*` ditangani oleh Next.js BFF (route `src/app/api/**`).

## Env
`api/.env` (Laravel): `APP_KEY`, `DB_HOST=db`, `DB_DATABASE=aistack`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER=database`, `SANCTUM_STATEFUL_DOMAINS=localhost`, `SESSION_DOMAIN=localhost`, `REDIS_HOST=redis`, `LARAVEL_URL=http://localhost`.
`web/.env` (Next.js): `LARAVEL_URL=http://api:8000` (BFF points to Laravel internal).
`docker-compose.yml`: `DB_USERNAME`, `DB_PASSWORD` vars.

### Multi-Schema

Database menggunakan 3 PostgreSQL schema: `aiplanstudio_master`, `aiplanstudio_project`, `aiplanstudio_settings`. `search_path` di `config/database.php` mencakup ketiganya agar mapping model tetap sederhana.

## Perintah Kunci
```bash
# 1. Build & jalankan semua
docker compose up -d --build

# 2. Inisialisasi Laravel
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed

# 3. Verifikasi hanya nginx yang publish
docker compose ps

# 4. Jalankan E2E tests
docker compose --profile test up web-test
# Atau dari host: cd web && npx playwright test

# 5. Cek tabel
docker compose exec db psql -U "$DB_USERNAME" -d aistack -c '\dt'

# 6. Antar-service via hostname (uji BFF)
curl http://localhost/api/health        # nginx → web → api → "ok"
docker compose exec web wget -qO- http://api:8000/api/health  # internal OK
```

## Checklist Keamanan Infra
- [x] Hanya `nginx` punya `ports:`.
- [x] `db`, `api`, `web` tanpa `ports:` (host `:5432`/`:8000`/`:3000` tertutup).
- [x] Volume DB baru (fresh).
- [x] Rahasia via env, bukan hardcode; `.env` tidak di-commit.
- [x] BFF: semua request masuk via nginx → Next.js, tidak ada route langsung ke Laravel.

---

## Development Tanpa Docker

Untuk development cepat, semua service bisa dijalankan langsung di host tanpa Docker.

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
APP_KEY=base64:8HPpQ3zWuC7l0QoOJLKz/NCfKK2f+zUxMobQZXDOUD8=
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=aiplanstudio
DB_USERNAME=postgres
DB_PASSWORD=arsyiladhiim
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
- Untuk beralih ke Docker, cukup setel ulang `.env` (lihat bagian Docker Setup di atas).
