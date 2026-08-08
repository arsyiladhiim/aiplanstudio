// lib/bff.ts — shared helpers for BFF route handlers.
// Sanctum SPA auth via cookies. BFF forwards cookies transparently.
// No Bearer tokens, no sessionStorage.

const LARAVEL_URL = process.env.LARAVEL_URL ?? 'http://aiplanstudionginx_api:8000';

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

/** Build a Response from the upstream Laravel fetch, forwarding Set-Cookie headers */
export async function fwdResponse(res: Response, contentType?: string): Promise<Response> {
  const data = res.status === 204 ? null : await res.text();
  const ct = contentType ?? res.headers.get('content-type') ?? 'application/json';
  const headers: [string, string][] = [['Content-Type', ct], ...setCookieHeaders(res)];
  return new Response(data, { status: res.status, headers });
}

/** Safe variant: catches fetch/network errors and returns 500 JSON instead of crashing */
export async function safeFwdResponse(promise: Promise<Response>, contentType?: string): Promise<Response> {
  try {
    const res = await promise;
    return await fwdResponse(res, contentType);
  } catch {
    return new Response(JSON.stringify({ message: 'Internal Server Error' }), { status: 500, headers: { 'Content-Type': 'application/json' } });
  }
}

/** Forward a request with cookie + Set-Cookie propagation and sanitized errors */
export async function safeFwd(request: Request, url: string, init: RequestInit): Promise<Response> {
  let res: Response;
  try {
    res = await fetch(url, init);
  } catch {
    return new Response(
      JSON.stringify({ message: 'Layanan tidak tersedia. Silakan coba lagi.' }),
      { status: 502, headers: { 'Content-Type': 'application/json' } }
    );
  }
  const data = res.status === 204 ? null : await res.text();
  const headers: [string, string][] = [
    ['Content-Type', res.headers.get('content-type') ?? 'application/json'],
    ...setCookieHeaders(res),
  ];
  return new Response(data, { status: res.status, headers });
}

/** Build a binary Response (blob) from the upstream Laravel fetch, forwarding Set-Cookie headers */
export async function fwdBlobResponse(res: Response): Promise<Response> {
  const blob = await res.blob();
  const headers: [string, string][] = [['Content-Type', res.headers.get('content-type') ?? 'application/octet-stream'], ...setCookieHeaders(res)];
  const disposition = res.headers.get('content-disposition');
  if (disposition) headers.push(['Content-Disposition', disposition]);
  return new Response(blob, { status: res.status, headers });
}
