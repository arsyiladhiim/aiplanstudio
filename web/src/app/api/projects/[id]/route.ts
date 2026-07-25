import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/projects/${id}`, { headers: cookieHeaders(request) });
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/projects/${id}`, { method: 'DELETE', headers: cookieHeaders(request) });
  return new Response(null, { status: res.status });
}

export const dynamic = 'force-dynamic';
