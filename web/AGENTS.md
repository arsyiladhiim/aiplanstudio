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
