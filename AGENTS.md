# AI Plan Studio — OpenCode Rules

## Stack
- Backend: Laravel 11 (PHP 8.4)
- Frontend: Next.js (Node 24) + React 19 + Tailwind CSS v4
- Database: PostgreSQL 16
- Infrastructure: Docker Compose

## Workflow
- When searching for docs or package references, use `context7` tools.
- When you encounter errors, use Sentry MCP to check error traces.
- Always run `npm run lint` and `npx tsc --noEmit` after TypeScript edits.
- Always run `php artisan test` after PHP edits.
- Format code with pint (PHP) and prettier (TS/JS) before finalizing changes.
- Use `/test` to run Laravel tests, `/lint` for frontend lint, `/tsc` for TypeScript check, `/build` for frontend build.

## Architecture
- BFF (Backend-for-Frontend): nginx → Next.js (BFF) → Laravel API
- Auth: Sanctum SPA Session (HttpOnly cookie + CSRF), NOT Bearer token
- All API calls go through Next.js route handlers, never direct to Laravel
- AI pipeline: pertanyaan (klarifikasi) → analisa → prd → architecture → erd → phased_master → phased_master_mobile (hanya untuk target 'both')
- Default stage status: pertanyaan, analisa, prd, architecture, erd, phased_master, phased_master_mobile
- [PATCH] /api/projects/{id} — update project title/idea/target
- [DELETE] /api/versions/{id} — hapus versi (tidak bisa hapus versi terakhir)
- [GET/PATCH] /api/settings/profile — lihat/edit profil user
- Database: 3 PostgreSQL schemas (aiplanstudio_master, aiplanstudio_project, aiplanstudio_settings)

## Security
- Never read .env files or expose API keys
- AI provider keys are stored encrypted in DB
- All queries must filter by user_id ownership
- SSRF protection: validateBaseUrl() blocks internal IPs

## Context7
When you need to search docs, use `context7` tools.

## Graph Memory
Use `graph-memory` tools to persist information across sessions.

## PostgreSQL
For DB inspection or schema verification, use `postgres` tools.

## Sequential Thinking
For architecture reasoning and structured problem-solving, use `sequential-thinking` tools.

## Skills
Available skills (use `skill` tool to load):
- `git-release` — Create consistent releases and changelogs
- `database` — DB migrations, seeding, management
- `docker` — Docker Compose commands
- `testing` — Run backend/frontend tests

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- **Graphify first, explore only when needed.** Do NOT launch explore agents for every analysis/audit. Graphify (AST-only, no API cost) is enough for navigation, locating files, and relationship questions. Use explore/read agents only when behavioral reasoning is required: cross-file logic bugs, data-flow, security review, or when the scoped subgraph does not surface enough context.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
