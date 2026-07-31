import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/profile`, fwd('GET', request)));
}

export async function PATCH(request: Request) {
  const body = await request.json();
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/profile`, fwd('PATCH', request, body)));
}

export const dynamic = 'force-dynamic';
