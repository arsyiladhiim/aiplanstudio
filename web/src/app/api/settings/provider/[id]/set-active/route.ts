import { LARAVEL_URL, cookieHeaders, safeFwdResponse } from '@/lib/bff';

export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/provider/${id}/set-active`, { method: 'POST', headers: cookieHeaders(request) }));
}

export const dynamic = 'force-dynamic';
