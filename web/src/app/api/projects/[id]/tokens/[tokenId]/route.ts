import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string; tokenId: string }> }) {
  const { id, tokenId } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects/${id}/tokens/${tokenId}`, fwd('DELETE', request)));
}

export const dynamic = 'force-dynamic';
