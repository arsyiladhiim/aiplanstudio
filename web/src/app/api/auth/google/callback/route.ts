import { LARAVEL_URL, cookieHeaders, setCookieHeaders } from '@/lib/bff';

export async function GET(request: Request) {
  const url = new URL(request.url);
  try {
    const res = await fetch(`${LARAVEL_URL}/api/auth/google/callback${url.search}`, {
      headers: cookieHeaders(request),
      redirect: 'manual',
    });

    const location = res.headers.get('location');
    const headers: [string, string][] = [...setCookieHeaders(res)];
    if (location) headers.push(['Location', location]);

    return new Response(null, { status: res.status, headers });
  } catch {
    return new Response(
      JSON.stringify({ message: 'Layanan tidak tersedia. Silakan coba lagi.' }),
      { status: 502, headers: { 'Content-Type': 'application/json' } }
    );
  }
}

export const dynamic = 'force-dynamic';
