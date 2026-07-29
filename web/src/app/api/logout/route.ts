import { LARAVEL_URL, cookieHeaders, setCookieHeaders } from '@/lib/bff';

export function GET() {
  return Response.redirect('/login');
}

export async function POST(request: Request) {
  const res = await fetch(`${LARAVEL_URL}/api/logout`, {
    method: 'POST',
    headers: cookieHeaders(request),
  });

  const data = res.status === 204 ? null : await res.text();
  const headers: [string, string][] = [['Content-Type', 'application/json'], ...setCookieHeaders(res)];

  return new Response(data, { status: res.status, headers });
}

export const dynamic = 'force-dynamic';
