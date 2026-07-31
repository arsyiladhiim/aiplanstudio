import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function POST(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/versions/${id}/regenerate-standards`, fwd('POST', request)));
}

export const dynamic = 'force-dynamic';
