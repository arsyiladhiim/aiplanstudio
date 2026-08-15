# AI Plan Studio

> AI-powered planning platform that turns ideas into 1-paste-ready master build prompts for coding agents.

**Stack:** Laravel 13 (PHP 8.3) · Next.js (App Router) + React 19 + TypeScript · PostgreSQL 16 · Tailwind CSS v4 · Docker Compose + Cloudflare Tunnel (direct routing, no BFF)

---

## What it does

1. **Wizard "Buat Plan"** — 13-stage pipeline (10 for web-only target) that transforms a rough idea into a structured build plan: klarifikasi → analisa → PRD → arsitektur → ERD → phases → standards → master prompt → agents.
2. **Master Prompt Showcase** — full-screen viewer with section accordion, inline edit, .md download, copy-all. Auto-opens modal saat master prompt selesai di-generate.
3. **Tracking Webhook** — AI coding agent yang eksekusi master prompt kirim checkpoint per fase/sub-item via signed HTTP webhook ke AI Plan Studio.
4. **Per-version regeneration** — buat versi baru dengan carry-over artifacts (revision, not from-scratch) atau blank start.

Lihat [docs/05-wizard-flow.md](docs/05-wizard-flow.md) untuk flow detail.

---

## Tracking Webhook

Setelah user generate master prompt, mereka Setup Tracking untuk dapat **Token** + **Secret**. Coding agent yang pakai master prompt akan kirim webhook checkpoint per fase + sub-item.

### Setup

```bash
# User dapat token via UI: TrackingPanel → "Setup Tracking" button → Modal
# Atau programmatically (cookies + CSRF dari session browser):
curl -X POST $APP_URL/api/projects/{project}/versions/{version}/tokens/auto-tracking \
  -H "Cookie: ai-planning-studio-session=..." \
  -H "X-XSRF-TOKEN: $CSRF" \
  -H "Content-Type: application/json"
# Response: { "token": "...", "secret": "...", "id": 123, "name": "auto-tracking-...", "existing": false }
# Save secret SEKARANG — tidak akan ditampilkan lagi.
```

> **Direct routing (no BFF):** Browser fetch `$APP_URL/api/...` langsung dari Next.js. Tidak ada hop BFF.

### Webhook call

```bash
TIMESTAMP=$(date +%s)
BODY='{"version_id":1,"phase_key":"fase1_setup","task_key":"fase1_setup_fitur_1","task_type":"fitur","title":"Auth Login","status":"done","output":"completed"}'
SIGNATURE=$(echo -n "$TIMESTAMP.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST $APP_URL/api/webhooks/phase-complete \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Token-Secret: $SECRET" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Signature: $SIGNATURE" \
  -H "Content-Type: application/json" \
  -d "$BODY"
```

### Headers (case-sensitive, semua wajib)

| Header | Value |
|--------|-------|
| `Authorization` | `Bearer <token>` |
| `X-Token-Secret` | `<secret>` (HMAC key) |
| `X-Timestamp` | `<unix_seconds>` (max 300s skew) |
| `X-Signature` | `hmac_sha256("<X-Timestamp>.<raw_body>", "<X-Token-Secret>")` |

### Body schema

```typescript
{
  version_id: number;          // required
  phase_key: string;           // required, MUST match fase key from phases artifact
  status: "running" | "done" | "error";
  output?: string;             // ringkasan

  // Granular (opsional, untuk per sub-item tracking)
  task_key?: string;           // sub-item key
  task_type?: "halaman" | "menu" | "fitur" | "flow" | "api";
  title?: string;
}
```

### Response

- `200` `{ ok: true, phase_key, task_key?, status }` — webhook accepted, persisted to `phase_progress` + `task_progress`.
- `401` signature/secret missing/invalid.
- `409` duplicate (replay protection, cache TTL 1 jam).
- `422` validation error (unknown phase_key atau invalid task_type).

---

## Architecture

```
Browser (Next.js + React 19)
   │  HttpOnly session cookie + CSRF (credentials: "include")
   ▼
   Direct fetch → ${NEXT_PUBLIC_API_URL}/api/* (cross-origin via Cloudflare Tunnel)
   ▼
nginx_api (docker/api-nginx) → php-fpm (Laravel, stateless)
   │  Controllers → Services → Repositories → Models
   ▼
PostgreSQL 16 (3 schemas)
   ├── aiplanstudio_master    (users, projects metadata, providers)
   ├── aiplanstudio_project   (versions, artifacts, phase_progress, task_progress)
   └── aiplanstudio_settings  (profile, preferences)
```

> **No BFF layer.** Browser fetch direct ke Laravel via `NEXT_PUBLIC_API_URL`. CORS + Sanctum stateful domain handle cross-origin. See [docs/25-bypass-bff.md](docs/25-bypass-bff.md) untuk migration rationale.

Lihat [docs/06-ai-pipeline.md](docs/06-ai-pipeline.md) dan [.graphify/GRAPH_REPORT.md](.graphify/GRAPH_REPORT.md).

---

## Local development

```bash
# Start full stack
docker compose up

# Backend (di dalam container)
docker exec aiplanstudio_apifpm php artisan test           # 258 tests
docker exec aiplanstudio_apifpm php artisan pint --test    # code style
docker exec aiplanstudio_apifpm vendor/bin/pint app/Prompts # format prompts

# Frontend
cd web
npm run lint         # ESLint
npx tsc --noEmit     # TypeScript check
npm run build        # production build
npx playwright test  # e2e tests
```

### Ports

| Service | Port | URL |
|---------|------|-----|
| Cloudflare Tunnel | external | `https://aiplanstudio.arsyiladm.my.id` (web) + `https://api-aiplanstudio.arsyiladm.my.id` (API) |
| Next.js (internal) | 3000 | (via Cloudflare Tunnel) |
| nginx_api (Laravel front) | 8000 | (via Cloudflare Tunnel) |
| php-fpm | 9000 | (internal only) |
| PostgreSQL | 5432 | (internal only) |

Dev local: `http://localhost:3000` (Next.js) + `http://localhost:8000` (nginx_api direct). Set di `web/.env.development` dan `api/.env.example`.

---

## Pipeline stage outputs (DB columns)

| Stage | Column | Type |
|-------|--------|------|
| `pertanyaan` | `pertanyaan` | text |
| `analisa` | `analysis` | text |
| `prd` | `prd` | text |
| `architecture` | `architecture` | text |
| `erd` | `erd` | jsonb |
| `api_contract` | `api_contract` | jsonb |
| `phases_web` | `phases` | jsonb |
| `standards_web` | `standards` | text |
| `master_web` | `master_prompt` | text |
| `pertanyaan_mobile` | `pertanyaan_mobile`, `mobile_answers` | text, jsonb |
| `phases_mobile` | `mobile_phases` | jsonb |
| `standards_mobile` | `mobile_standards` | text |
| `master_mobile` | `mobile_master_prompt` | text |
| `agents` | `agents` | text |

---

## Known issues

- **SocialiteControllerTest::test_first_google_login_creates_admin_and_logs_in** — pre-existing failure (sebelum CP-6). Punya dependency `Socialite::driver('google')` yang butuh mock Google OAuth config. Di luar scope master-repair CP-6..11. Track terpisah.

---

## Documentation

- [docs/05-wizard-flow.md](docs/05-wizard-flow.md) — wizard flow + Tracking Webhook spec
- [docs/06-ai-pipeline.md](docs/06-ai-pipeline.md) — pipeline architecture
- [docs/12-security-checklist.md](docs/12-security-checklist.md) — security baseline
- [docs/15-dev-log.md](docs/15-dev-log.md) — chronological dev log (CP-1..11 entries)
- [docs/16-audit-fix-plan.md](docs/16-audit-fix-plan.md) — prior audit findings
- [.graphify/GRAPH_REPORT.md](.graphify/GRAPH_REPORT.md) — codebase knowledge graph
- [docs/plan/master-repair.md](docs/plan/master-repair.md) — CP-1..11 master plan with sign-offs
