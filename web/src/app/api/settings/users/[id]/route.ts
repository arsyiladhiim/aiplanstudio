import { LARAVEL_URL, fwd } from '@/lib/bff';

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json().catch(() => ({}));
  const res = await fetch(`${LARAVEL_URL}/api/settings/users/${id}`, fwd('PATCH', request, body));
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/settings/users/${id}`, fwd('DELETE', request));
  return new Response(null, { status: res.status });
}

export const dynamic = 'force-dynamic';
