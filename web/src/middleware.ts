import { type NextRequest, NextResponse } from "next/server";

const protectedPaths = ["/dashboard", "/projects", "/new", "/templates", "/settings"];

export function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl;
  const hasSession = req.cookies.has("ai-planning-studio-session");
  const hasXsrf = req.cookies.has("XSRF-TOKEN");

  // Redirect unauthenticated users to login for protected pages
  if (!hasSession && !hasXsrf && protectedPaths.some((p) => pathname.startsWith(p))) {
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
