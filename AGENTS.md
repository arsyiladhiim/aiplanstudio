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
- AI pipeline: analisa → prd → architecture → erd → phases → master
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

This project has a graphify knowledge graph at .graphify/.

Rules:
- For codebase or architecture questions, when `.graphify/graph.json` exists, first run `graphify query "<question>"` (or `graphify path "<A>" "<B>"` / `graphify explain "<concept>"`); these return a scoped subgraph, usually much smaller than `GRAPH_REPORT.md` or raw grep output
- If .graphify/wiki/index.md exists, navigate it instead of reading raw files
- If .graphify/graph.json is missing but graphify-out/graph.json exists, run `graphify migrate-state --dry-run` first; if tracked legacy artifacts are reported, ask before using the recommended `git mv -f graphify-out .graphify` and commit message
- If .graphify/needs_update exists or .graphify/branch.json has stale=true, warn before relying on semantic results and run the graphify skill with --update when appropriate
- If the user asks to build, update, query, path, or explain the graph, use the installed `graphify` skill instead of ad-hoc file traversal
- Before proposing or committing .graphify artifacts, run `graphify portable-check .graphify`; commit-safe graph artifacts must use repo-relative paths, and never commit .graphify/branch.json, .graphify/worktree.json, .graphify/needs_update, or .graphify/cache/. If a repo already tracks any of them, first add them to .gitignore, then propose `git rm --cached .graphify/branch.json .graphify/worktree.json .graphify/needs_update` and `git rm -r --cached .graphify/cache`; never mutate git state without asking
- Before deep graph traversal, prefer `graphify summary --graph .graphify/graph.json` for compact first-hop orientation
- For review impact on changed files, use `graphify review-delta --graph .graphify/graph.json` instead of generic traversal
- Read `.graphify/GRAPH_REPORT.md` only for broad architecture review or when `query` / `path` / `explain` do not surface enough context
- After modifying code files in this session, run `npx graphify hook-rebuild` to keep the graph current
