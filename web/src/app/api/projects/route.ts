import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  const url = new URL(request.url);
  const qs = url.searchParams.toString();
  const target = `${LARAVEL_URL}/api/projects${qs ? `?${qs}` : ''}`;
  return await safeFwdResponse(fetch(target, fwd('GET', request)));
}

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects`, fwd('POST', request, body)));
}

export const dynamic = 'force-dynamic';
