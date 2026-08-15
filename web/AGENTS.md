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

## API Rules (Direct Routing — no BFF)
- Browser fetch DIRECT ke Laravel via `NEXT_PUBLIC_API_URL` dengan `credentials: "include"` (no BFF layer — see docs/25-bypass-bff.md)
- Backend dev: `http://localhost:8000` (nginx_api direct). Prod: `https://api-<your-domain>`.
- Session cookie auth only (HttpOnly + `SameSite=None; Secure` cross-origin), no Bearer tokens
- Always call `fetchCsrfCookie()` before state-changing requests
- Handle 401 → redirect to /login
- CORS configured untuk cross-origin (`api/config/cors.php` allowlist + `supports_credentials: true`)

## Pipeline Stages (14 stages, target both / 10 stages, target web)
Questions (MCQ) → Analysis → PRD → Architecture → ERD → API Contract → Phases (web) → Standards (web) → Master Prompt (web) → Mobile Questions (MCQ) → Phases (mobile) → Standards (mobile) → Master Prompt (mobile) → Agents
Keys: pertanyaan, analisa, prd, architecture, erd, api_contract, phases_web, standards_web, master_web, pertanyaan_mobile, phases_mobile, standards_mobile, master_mobile, agents
Gate: mobile track (10-13) waits for master_web done. Target web = 10 stages only.
