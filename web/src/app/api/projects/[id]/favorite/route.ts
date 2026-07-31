import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects/${id}/favorite`, fwd('PATCH', request)));
}

export const dynamic = 'force-dynamic';
