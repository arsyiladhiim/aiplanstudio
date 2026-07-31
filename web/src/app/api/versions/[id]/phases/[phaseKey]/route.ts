import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function PATCH(request: Request) {
  const url = new URL(request.url);
  const parts = url.pathname.split('/');
  const id = parts[parts.indexOf('versions') + 1];
  const phaseKey = parts[parts.indexOf('phases') + 1];
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/versions/${id}/phases/${phaseKey}`, fwd('PATCH', request, body)));
}

export const dynamic = 'force-dynamic';
