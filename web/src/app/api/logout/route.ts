import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export function GET() {
  return Response.redirect('/login');
}

export async function POST(request: Request) {
  const res = await fetch(`${LARAVEL_URL}/api/logout`, {
    method: 'POST',
    headers: cookieHeaders(request),
  });

  const data = res.status === 204 ? null : await res.text();
  const headers: Record<string, string> = { 'Content-Type': 'application/json' };
  const setCookie = res.headers.get('set-cookie');
  if (setCookie) headers['Set-Cookie'] = setCookie;

  return new Response(data, { status: res.status, headers });
}

export const dynamic = 'force-dynamic';
