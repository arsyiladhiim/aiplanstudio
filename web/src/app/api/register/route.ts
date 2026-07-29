import { LARAVEL_URL, cookieHeaders, setCookieHeaders } from '@/lib/bff';

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  const res = await fetch(`${LARAVEL_URL}/api/register`, {
    method: 'POST',
    headers: cookieHeaders(request),
    body: JSON.stringify(body),
  });

  const data = await res.text();
  const headers: [string, string][] = [['Content-Type', 'application/json'], ...setCookieHeaders(res)];

  return new Response(data, { status: res.status, headers });
}

export const dynamic = 'force-dynamic';
