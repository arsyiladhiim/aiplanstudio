@../AGENTS.md

<!-- BEGIN:nextjs-agent-rules -->
# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.
<!-- END:nextjs-agent-rules -->

## TypeScript & Lint
- Always run `npm run lint` after edits
- Always run `npx tsc --noEmit` for type checking
- Use `/lint` and `/tsc` commands as shortcuts
- Format with prettier before finalizing

## API Rules (Direct Routing)
- Browser fetch DIRECT ke Laravel via `NEXT_PUBLIC_API_URL` dengan `credentials: "include"` — lihat docs/25-bypass-bff.md
- Backend dev: `http://localhost:8000` (nginx_api direct). Prod: `https://api-<your-domain>`.
- Session cookie auth only (HttpOnly + `SameSite=None; Secure` cross-origin), no Bearer tokens
- Always call `fetchCsrfCookie()` before state-changing requests
- Handle 401 → redirect to /login
- CORS configured untuk cross-origin (`api/config/cors.php` allowlist + `supports_credentials: true`)

## Pipeline Stages (22 stages, target both / 16 stages, target web)
Source of truth: `api/app/Services/StageRegistry.php` (mirror UI: `src/lib/mock.ts`, endpoint `GET /api/stages`).
Urutan kanonik: pertanyaan, analisa, prd, architecture, erd, api_contract, design_system, phases_web, standards_web, master_web, app_spec_web, design_system_mobile, pertanyaan_mobile, standards_mobile, phases_mobile, master_mobile, app_spec_mobile, env_config, security, deployment, observability, agents.
Gate: mobile track menunggu `master_web` done. Target `web` = 16 stage tanpa key mengandung `mobile`.
