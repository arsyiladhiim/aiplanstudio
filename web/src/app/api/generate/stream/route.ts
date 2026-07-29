import { LARAVEL_URL, sseCookieHeaders } from '@/lib/bff';

export async function GET(request: Request) {
  const url = new URL(request.url);
  const laravelUrl = `${LARAVEL_URL}/api/generate/stream${url.search}`;

  console.log('[BFF SSE] Request:', url.search, '→', laravelUrl);

  const headers: Record<string, string> = sseCookieHeaders(request);

  let laravelRes: Response;
  try {
    laravelRes = await fetch(laravelUrl, { headers, cache: 'no-store' });
    console.log('[BFF SSE] Laravel status:', laravelRes.status);
  } catch (err) {
    const msg = err instanceof Error ? err.message : 'Gagal terhubung ke server';
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
    const body = await laravelRes.text();
    console.error('[BFF SSE] Laravel error:', laravelRes.status, body);
    return new Response(
      `event: fail\ndata: ${JSON.stringify({ message: body || 'Stream failed', status: laravelRes.status })}\n\n`,
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
