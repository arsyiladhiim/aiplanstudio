import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const url = new URL(request.url);
  const format = url.searchParams.get('format');
  if (format && !['md', 'zip'].includes(format)) {
    return Response.json({ message: 'Format tidak didukung.' }, { status: 422 });
  }

  const laravelRes = await fetch(`${LARAVEL_URL}/api/versions/${id}/export${url.search}`, {
    headers: cookieHeaders(request),
  });

  const blob = await laravelRes.blob();
  return new Response(blob, {
    status: laravelRes.status,
    headers: {
      'Content-Type': laravelRes.headers.get('content-type') ?? 'application/octet-stream',
      'Content-Disposition': laravelRes.headers.get('content-disposition') ?? 'attachment',
    },
  });
}

export const dynamic = 'force-dynamic';
