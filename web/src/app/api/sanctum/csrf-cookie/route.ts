import { LARAVEL_URL, cookieHeaders, setCookieHeaders } from '@/lib/bff';

export async function GET(request: Request) {
  const res = await fetch(`${LARAVEL_URL}/sanctum/csrf-cookie`, { headers: cookieHeaders(request) });
  return new Response(null, { status: 204, headers: setCookieHeaders(res) });
}

export const dynamic = 'force-dynamic';
