import { LARAVEL_URL, fwd, safeFwdResponse } from '@/lib/bff';

export async function GET(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects/${id}`, fwd('GET', request)));
}

export async function PATCH(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const body = await request.json();
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects/${id}`, fwd('PATCH', request, body)));
}

export async function DELETE(request: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return await safeFwdResponse(fetch(`${LARAVEL_URL}/api/projects/${id}`, fwd('DELETE', request)));
}

export const dynamic = 'force-dynamic';
