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

## API Rules (BFF)
- All /api/* routes proxy to Laravel — never call Laravel directly
- Session cookie auth only, no Bearer tokens
- Always call fetchCsrfCookie() before state-changing requests
- Handle 401 → redirect to /login

## Pipeline Stages (13 stages, target both / 9 stages, target web)
Questions → Analysis → PRD → Architecture → ERD → Standards (web) → Agents (web) → Phases (web) → Master Prompt (web) → Phases (mobile) → Standards (mobile) → Agents (mobile) → Master Prompt (mobile)
Keys: pertanyaan, analisa, prd, architecture, erd, standards_web, agents_web, phases_web, master_web, phases_mobile, standards_mobile, agents_mobile, master_mobile
Gate: mobile track (10-13) waits for master_web done. Target web = 9 stages only.
