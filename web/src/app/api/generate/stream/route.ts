import { LARAVEL_URL, cookieHeaders } from '@/lib/bff';

export async function GET(request: Request) {
  const url = new URL(request.url);
  const laravelUrl = `${LARAVEL_URL}/api/generate/stream${url.search}`;

  const headers: Record<string, string> = { Accept: 'text/event-stream', ...cookieHeaders(request) };

  const laravelRes = await fetch(laravelUrl, { headers });

  if (!laravelRes.ok) {
    return Response.json({ message: 'Stream failed' }, { status: laravelRes.status });
  }

  return new Response(laravelRes.body, {
    headers: {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
    },
  });
}

export const dynamic = 'force-dynamic';
