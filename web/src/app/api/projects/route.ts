import { LARAVEL_URL, fwd } from '@/lib/bff';

export async function GET(request: Request) {
  const res = await fetch(`${LARAVEL_URL}/api/projects`, fwd('GET', request));
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  const res = await fetch(`${LARAVEL_URL}/api/projects`, fwd('POST', request, body));
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export const dynamic = 'force-dynamic';
