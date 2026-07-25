import { LARAVEL_URL } from '@/lib/bff';

export async function GET() {
  const laravelRes = await fetch(`${LARAVEL_URL}/api/health`);
  return new Response(await laravelRes.text(), {
    status: laravelRes.status,
    headers: { 'Content-Type': 'application/json' },
  });
}

export const dynamic = 'force-dynamic';
