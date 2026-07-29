import { LARAVEL_URL } from '@/lib/bff';

export async function POST(request: Request) {
  const laravelUrl = `${LARAVEL_URL}/api/webhooks/phase-complete`;

  // Forward Authorization header (Bearer token) + body
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };
  const auth = request.headers.get('authorization');
  if (auth) headers['Authorization'] = auth;

  try {
    const body = await request.json();
    const laravelRes = await fetch(laravelUrl, {
      method: 'POST',
      headers,
      body: JSON.stringify(body),
    });

    return new Response(await laravelRes.text(), {
      status: laravelRes.status,
      headers: { 'Content-Type': 'application/json' },
    });
  } catch (err) {
    return new Response(
      JSON.stringify({ message: err instanceof Error ? err.message : 'Webhook error' }),
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    );
  }
}
