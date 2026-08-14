import { NextResponse } from "next/server";

/**
 * Middleware hanya redirect guard untuk unauth users di protected paths.
 * Cookie session ada di domain api-aiplanstudio.arsyiladm.my.id (cross-origin),
 * tidak bisa dibaca dari frontend (HttpOnly + SameSite=None scoped).
 * Auth check sebenarnya terjadi di client-side: API return 401 → redirect via UserContext.
 *
 * Middleware ini opsional; bisa dihapus bila semua client component sudah handle 401.
 */
export function middleware() {
  return NextResponse.next();
}

export const config = {
  matcher: [
    "/((?!api|_next/static|_next/image|favicon.ico).*)",
  ],
};
