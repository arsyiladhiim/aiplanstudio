import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/templates`, fwd('GET', request)));
}

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/templates`, fwd('POST', request, body)));
}

export const dynamic = 'force-dynamic';
