import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/dashboard/stats`, fwd('GET', request)));
}

export const dynamic = 'force-dynamic';
