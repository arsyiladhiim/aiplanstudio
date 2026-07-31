import { LARAVEL_URL, cookieHeaders, safeFwd } from '@/lib/bff';

export async function POST(request: Request) {
  const body = await request.json().catch(() => ({}));
  return await safeFwd(request, `${LARAVEL_URL}/api/reset-password`, {
    method: 'POST',
    headers: cookieHeaders(request),
    body: JSON.stringify(body),
  });
}

export const dynamic = 'force-dynamic';
