import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json().catch(() => ({}));
  const res = await fetch(`${LARAVEL_URL}/api/settings/provider/${id}`, {
    method: 'PATCH', headers: cookieHeaders(request), body: JSON.stringify(body),
  });
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/settings/provider/${id}`, { method: 'DELETE', headers: cookieHeaders(request) });
  return new Response(null, { status: res.status });
}

export const dynamic = 'force-dynamic';
