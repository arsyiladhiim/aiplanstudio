import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/settings/provider/${id}/test`, { method: 'POST', headers: cookieHeaders(request) });
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export const dynamic = 'force-dynamic';
