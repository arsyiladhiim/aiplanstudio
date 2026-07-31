import { LARAVEL_URL, cookieHeaders, safeFwd } from '@/lib/bff';

export function GET() {
  return Response.redirect('/login');
}

export async function POST(request: Request) {
  return await safeFwd(request, `${LARAVEL_URL}/api/logout`, {
    method: 'POST',
    headers: cookieHeaders(request),
  });
}

export const dynamic = 'force-dynamic';
