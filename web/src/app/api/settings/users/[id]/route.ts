import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/users/${id}`, fwd('PATCH', request, body)));
}

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/users/${id}`, fwd('DELETE', request)));
}

export const dynamic = 'force-dynamic';
