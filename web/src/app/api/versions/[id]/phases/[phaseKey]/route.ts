import { LARAVEL_URL, fwd } from '@/lib/bff';

export async function PATCH(request: Request) {
  const url = new URL(request.url);
  const parts = url.pathname.split('/');
  const id = parts[parts.indexOf('versions') + 1];
  const phaseKey = parts[parts.indexOf('phases') + 1];
  const body = await request.json().catch(() => ({}));
  const res = await fetch(`${LARAVEL_URL}/api/versions/${id}/phases/${phaseKey}`, fwd('PATCH', request, body));
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export const dynamic = 'force-dynamic';
