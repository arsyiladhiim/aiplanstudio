import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  const res = await fetch(`${LARAVEL_URL}/api/register`, {
    method: 'POST',
    headers: cookieHeaders(request),
    body: JSON.stringify(body),
  });

  const data = await res.text();
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  const setCookie = res.headers.get('set-cookie');
  if (setCookie) headers['Set-Cookie'] = setCookie;

  return new Response(data, { status: res.status, headers });
}

export const dynamic = 'force-dynamic';
