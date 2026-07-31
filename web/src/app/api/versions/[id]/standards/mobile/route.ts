import { LARAVEL_URL, cookieHeaders, fwdBlobResponse } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const laravelRes = await fetch(`${LARAVEL_URL}/api/versions/${id}/standards/mobile`, {
    headers: cookieHeaders(request),
  });
  return await fwdBlobResponse(laravelRes);
}

export const dynamic = 'force-dynamic';
