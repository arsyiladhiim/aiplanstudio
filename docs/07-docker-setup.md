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
