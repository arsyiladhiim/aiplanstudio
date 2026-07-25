import { LARAVEL_URL, fwd } from '@/lib/bff';

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const res = await fetch(`${LARAVEL_URL}/api/templates/${id}`, fwd('DELETE', request));
  return new Response(null, { status: res.status });
}

export const dynamic = 'force-dynamic';
