import { LARAVEL_URL } from '@/lib/bff';

const MAX_BODY_SIZE = 1_048_576; // 1MB
const UPSTREAM_TIMEOUT_MS = 30_000;

export async function POST(request: Request) {
  const auth = request.headers.get('authorization');
  if (!auth || !auth.startsWith('Bearer ')) {
    return new Response(
      JSON.stringify({ message: 'Unauthorized: Bearer token required' }),
      { status: 401, headers: { 'Content-Type': 'application/json' } }
    );
  }

  const contentLength = request.headers.get('content-length');
  if (contentLength && parseInt(contentLength, 10) > MAX_BODY_SIZE) {
    return new Response(
      JSON.stringify({ message: 'Request body too large' }),
      { status: 413, headers: { 'Content-Type': 'application/json' } }
    );
  }

  const laravelUrl = `${LARAVEL_URL}/api/webhooks/phase-complete`;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Authorization': auth,
  };

  try {
    const body = await request.json();
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), UPSTREAM_TIMEOUT_MS);

    const laravelRes = await fetch(laravelUrl, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
      signal: controller.signal,
    });
    clearTimeout(timer);

    const responseHeaders: [string, string][] = [
      ['Content-Type', laravelRes.headers.get('content-type') ?? 'application/json'],
    ];
    for (const cookie of laravelRes.headers.getSetCookie()) {
      responseHeaders.push(['Set-Cookie', cookie]);
    }
    return new Response(await laravelRes.text(), { status: laravelRes.status, headers: responseHeaders });
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') {
      return new Response(
        JSON.stringify({ message: 'Upstream request timed out' }),
        { status: 504, headers: { 'Content-Type': 'application/json' } }
      );
    }
    return new Response(
      JSON.stringify({ message: 'Webhook error' }),
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    );
  }
}
