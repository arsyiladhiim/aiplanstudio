// lib/bff.ts — shared helpers for BFF route handlers.
// Sanctum SPA auth via cookies. BFF forwards cookies transparently.
// No Bearer tokens, no sessionStorage.

const LARAVEL_URL = process.env.LARAVEL_URL ?? 'http://api:8000';

/** Forward cookies + Origin/Referer from incoming request to upstream Laravel */
export function cookieHeaders(request: Request): Record<string, string> {
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  const cookie = request.headers.get('cookie') ?? '';
  const xsrfToken = request.headers.get('x-xsrf-token') ?? '';
  const origin = request.headers.get('origin') ?? '';
  const referer = request.headers.get('referer') ?? '';
  if (cookie) headers['Cookie'] = cookie;
  if (xsrfToken) headers['X-XSRF-TOKEN'] = xsrfToken;
  if (origin) headers['Origin'] = origin;
  if (referer) headers['Referer'] = referer;
  return headers;
}

/** Build fetch init with cookie forwarding */
export function fwd(method: string, request: Request, body?: unknown): RequestInit {
  return { method, headers: cookieHeaders(request), body: body ? JSON.stringify(body) : undefined };
}

/** Forward Set-Cookie headers from Laravel response, preserving multiple cookies */
export function setCookieHeaders(res: Response): [string, string][] {
  const h: [string, string][] = [];
  for (const cookie of res.headers.getSetCookie()) {
    h.push(['Set-Cookie', cookie]);
  }
  return h;
}

export { LARAVEL_URL };

/** Forward only Cookie header for SSE/streaming requests (no Content-Type, no XSRF) */
export function sseCookieHeaders(request: Request): Record<string, string> {
  const headers: Record<string, string> = { Accept: 'text/event-stream' };
  const cookie = request.headers.get('cookie');
  if (cookie) headers['Cookie'] = cookie;
  return headers;
}
