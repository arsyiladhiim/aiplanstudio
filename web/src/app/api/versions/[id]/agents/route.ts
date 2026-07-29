import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_URL}/api/versions/${id}/agents`, {
    headers: cookieHeaders(request),
  });

  const blob = await laravelRes.blob();
  return new Response(blob, {
    status: laravelRes.status,
    headers: {
      'Content-Type': 'text/markdown; charset=utf-8',
      'Content-Disposition': laravelRes.headers.get('content-disposition') ?? 'attachment; filename="AGENTS.md"',
    },
  });
}

export const dynamic = 'force-dynamic';
