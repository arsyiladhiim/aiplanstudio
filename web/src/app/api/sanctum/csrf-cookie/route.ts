import { LARAVEL_URL, cookieHeaders, safeFwd } from '@/lib/bff';

export async function GET(request: Request) {
  return await safeFwd(request, `${LARAVEL_URL}/sanctum/csrf-cookie`, {
    headers: cookieHeaders(request),
  });
}

export const dynamic = 'force-dynamic';
