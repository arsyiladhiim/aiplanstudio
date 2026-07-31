import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/versions/${id}/artifacts`, fwd('PATCH', request, body)));
}

export const dynamic = 'force-dynamic';
