import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json().catch(() => ({}));
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects/${id}/versions`, fwd('POST', request, body)));
}

export const dynamic = 'force-dynamic';
