import { LARAVEL_URL, fwd } from '@/lib/bff';

export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_URL}/api/versions/${id}/regenerate-standards`, fwd('POST', request));
  const body = await laravelRes.text();
  return new Response(body, {
    status: laravelRes.status,
    headers: { 'Content-Type': 'application/json' },
  });
}

export const dynamic = 'force-dynamic';
