import { LARAVEL_URL } from '@/lib/bff';

export async function GET() {
  const res = await fetch(`${LARAVEL_URL}/sanctum/csrf-cookie`);
  const setCookie = res.headers.get('set-cookie');
  const headers: Record<string, string> = {};
  if (setCookie) headers['Set-Cookie'] = setCookie;
  return new Response(null, { status: 204, headers });
}

export const dynamic = 'force-dynamic';
