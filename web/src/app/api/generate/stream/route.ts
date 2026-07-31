import { LARAVEL_URL, sseCookieHeaders } from '@/lib/bff';

export async function POST(request: Request) {
  let body: { version?: string; stage?: string };
  try {
    body = await request.json();
  } catch {
    return new Response(
      `event: fail\ndata: ${JSON.stringify({ message: 'Invalid JSON' })}\n\n`,
      { status: 200, headers: { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache, no-store, must-revalidate' } }
    );
  }

  const params = new URLSearchParams();
  if (body.version) params.set('version', body.version);
  if (body.stage) params.set('stage', body.stage);
  const qs = params.toString();
  const laravelUrl = `${LARAVEL_URL}/api/generate/stream${qs ? '?' + qs : ''}`;

  console.log('[BFF SSE] POST →', laravelUrl);

  const headers: Record<string, string> = sseCookieHeaders(request);

  let laravelRes: Response;
  const upstreamController = new AbortController();
  const upstreamTimer = setTimeout(() => upstreamController.abort(), 30_000);
  try {
    laravelRes = await fetch(laravelUrl, { method: 'POST', headers, cache: 'no-store', signal: upstreamController.signal });
    clearTimeout(upstreamTimer);
    console.log('[BFF SSE] Laravel status:', laravelRes.status);
  } catch (err) {
    clearTimeout(upstreamTimer);
    const msg =
      err instanceof Error && err.name === 'AbortError'
        ? 'Backend lambat merespon.'
        : 'Gagal terhubung ke server';
    console.error('[BFF SSE] Fetch error:', msg);
    return new Response(
      `event: fail\ndata: ${JSON.stringify({ message: msg })}\n\n`,
      {
        status: 200,
        headers: {
          'Content-Type': 'text/event-stream',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          'Pragma': 'no-cache',
        },
      }
    );
  }

  if (!laravelRes.ok) {
    console.error('[BFF SSE] Laravel error:', laravelRes.status);
    return new Response(
      `event: fail\ndata: ${JSON.stringify({ message: 'Stream gagal.', status: laravelRes.status })}\n\n`,
      {
        status: 200,
        headers: {
          'Content-Type': 'text/event-stream',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          'Pragma': 'no-cache',
        },
      }
    );
  }

  if (!laravelRes.body) {
    console.error('[BFF SSE] No body from Laravel');
    return new Response(
      `event: fail\ndata: ${JSON.stringify({ message: 'Backend stream tidak merespon' })}\n\n`,
      { status: 200, headers: { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache, no-store, must-revalidate', 'Pragma': 'no-cache' } }
    );
  }

  const reader = laravelRes.body.getReader();
  const encoder = new TextEncoder();
  const closed = { value: false };
  const stream = new ReadableStream({
    start(controller) {
      controller.enqueue(encoder.encode(": start\n\n"));
      const pump = (): void => {
        reader.read().then(({ done, value }) => {
          if (closed.value) return;
          if (done) {
            closed.value = true;
            controller.close();
            reader.releaseLock();
            return;
          }
          controller.enqueue(value);
          pump();
        }).catch((err) => {
          if (closed.value) return;
          closed.value = true;
          console.error('[BFF SSE] Stream error:', err);
          controller.error(err);
          reader.releaseLock();
        });
      };
      pump();
    },
    cancel() {
      closed.value = true;
      reader.releaseLock();
    },
  });

  return new Response(stream, {
    headers: {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'X-Accel-Buffering': 'no',
    },
  });
}

/** Keep GET handler for backward compat */
export const GET = POST;
