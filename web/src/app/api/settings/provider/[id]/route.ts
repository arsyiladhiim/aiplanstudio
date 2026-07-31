import { LARAVEL_URL, cookieHeaders, safeFwdResponse } from '@/lib/bff';

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/provider/${id}`, {
    method: 'PATCH', headers: cookieHeaders(request), body: JSON.stringify(body),
  }));
}

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/settings/provider/${id}`, { method: 'DELETE', headers: cookieHeaders(request) }));
}

export const dynamic = 'force-dynamic';
