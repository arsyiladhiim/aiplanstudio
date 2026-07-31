import { LARAVEL_URL, fwdResponse } from '@/lib/bff';

export async function GET() {
  const laravelRes = await fetch(`${LARAVEL_URL}/api/health`);
  return await fwdResponse(laravelRes);
}

export const dynamic = 'force-dynamic';
