import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const url = new URL(request.url);
  const compare = url.searchParams.get('compare');
  const qs = compare ? `?compare=${encodeURIComponent(compare)}` : '';
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/versions/${id}/diff${qs}`, fwd('GET', request)));
}

export const dynamic = 'force-dynamic';
