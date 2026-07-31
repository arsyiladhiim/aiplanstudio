import { LARAVEL_URL, cookieHeaders, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/user`, { headers: cookieHeaders(request) }));
}

export const dynamic = 'force-dynamic';
