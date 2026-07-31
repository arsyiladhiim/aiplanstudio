import { type NextRequest, NextResponse } from "next/server";

const protectedPaths = ["/dashboard", "/projects", "/new", "/templates", "/settings", "/activities", "/help"];

export function proxy(req: NextRequest) {
  const { pathname } = req.nextUrl;
  const hasSession = req.cookies.has("ai-planning-studio-session");

  // Redirect unauthenticated users to login for protected pages
  if (!hasSession && protectedPaths.some((p) => pathname.startsWith(p))) {
    const login = new URL("/login", req.url);
    login.searchParams.set("redirect", pathname);
    return NextResponse.redirect(login);
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/((?!api|_next/static|_next/image|favicon.ico).*)",
  ],
};
