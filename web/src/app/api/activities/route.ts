import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request) {
  const url = new URL(request.url);
  const qs = url.searchParams.toString();
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/activities${qs ? `?${qs}` : ''}`, fwd('GET', request)));
}
