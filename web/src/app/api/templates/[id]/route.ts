import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/templates/${id}`, fwd('DELETE', request)));
}

export const dynamic = 'force-dynamic';
