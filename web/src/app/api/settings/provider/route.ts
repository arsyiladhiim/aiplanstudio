import { LARAVEL_URL, cookieHeaders, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/provider`, { headers: cookieHeaders(request) }));
}

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/provider`, {
    method: 'POST', headers: cookieHeaders(request), body: JSON.stringify(body),
  }));
}

export const dynamic = 'force-dynamic';
