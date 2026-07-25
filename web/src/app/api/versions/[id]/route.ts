import { LARAVEL_URL, fwd } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/versions/${id}`, fwd('GET', request));
  return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'application/json' } });
}

export const dynamic = 'force-dynamic';
