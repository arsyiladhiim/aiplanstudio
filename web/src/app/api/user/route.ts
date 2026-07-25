import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function GET(request: Request) {
  const res = await fetch(`${LARAVEL_URL}/api/user`, {
    headers: cookieHeaders(request),
  });

  const data = await res.text();
  return new Response(data, { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export const dynamic = 'force-dynamic';
