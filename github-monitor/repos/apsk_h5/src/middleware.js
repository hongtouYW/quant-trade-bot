// middleware.js
import { NextResponse } from "next/server";
import { COOKIE_NAMES } from "./constants/cookies";

const locales = ["en", "zh", "ms"];
const defaultLocale = "en";
const PUBLIC_FILE = /\.(.*)$/;
const IGNORED = ["/_next", "/api", "/favicon.ico"];
export const PUBLIC_PATHS = [
  "/login",
  "/register",
  "/otp",
  "/otp-reset/verify",
  "/otp-password",
  "/otp-password/verify",
  "/reset-password",
  "/agent-download",
  "/user-download",
  "/shop-download",
  "/game-redirect",
  "/game-detail-redirect",
  "/redirect",
  "/public",
  "/privacy",
  "/user-agreement",
]; // paths that don't need auth

function detectLocaleFromHeader(header) {
  if (!header) return defaultLocale;

  const candidates = header
    .split(",")
    .map((part) => part.split(";")[0]?.trim().toLowerCase())
    .filter(Boolean);

  for (const candidate of candidates) {
    if (locales.includes(candidate)) return candidate;
    const base = candidate.split("-")[0];
    if (locales.includes(base)) return base;
  }

  return defaultLocale;
}

export function middleware(request) {
  const { nextUrl, url, cookies } = request;
  const { pathname } = nextUrl;

  // Skip Next internals & static files
  if (
    IGNORED.some((p) => pathname.startsWith(p)) ||
    PUBLIC_FILE.test(pathname)
  ) {
    return NextResponse.next();
  }

  // Figure out locale in path and path without locale
  const segs = pathname.split("/"); // ["", "en", "foo"]
  const maybeLocale = locales.includes(segs[1]) ? segs[1] : null;
  const pathWithoutLocale = maybeLocale
    ? `/${segs.slice(2).join("/")}`
    : pathname;

  const cookieLocale = cookies.get(COOKIE_NAMES.LOCALE)?.value;
  const hasLocaleCookie = locales.includes(cookieLocale);
  const preferredLocale = hasLocaleCookie
    ? cookieLocale
    : detectLocaleFromHeader(request.headers.get("accept-language"));

  // If URL has no locale prefix, add it.
  if (!maybeLocale) {
    const redirectUrl = nextUrl.clone();
    redirectUrl.pathname =
      pathname === "/"
        ? `/${preferredLocale}`
        : `/${preferredLocale}${pathname}`;

    const res = NextResponse.redirect(redirectUrl);
    if (!hasLocaleCookie) {
      res.cookies.set(COOKIE_NAMES.LOCALE, preferredLocale, {
        path: "/",
        sameSite: "lax",
      });
    }
    return res;
  }

  // Update locale cookie when locale is present
  const resNext = NextResponse.next();
  if (cookieLocale !== maybeLocale) {
    resNext.cookies.set(COOKIE_NAMES.LOCALE, maybeLocale, {
      path: "/",
      sameSite: "lax",
    });
  }

  // 🔐 Auth gate (middleware can only read cookies, not localStorage)
  const token = cookies.get(COOKIE_NAMES.BEARER_TOKEN)?.value;
  const memberCookie = cookies.get(COOKIE_NAMES.MEMBER_INFO)?.value;

  const isPublic = PUBLIC_PATHS.some((p) => pathWithoutLocale.startsWith(p));
  if (!token && !isPublic) {
    return NextResponse.redirect(new URL(`/${maybeLocale}/login`, url));
  }

  if (!memberCookie && !isPublic) {
    return NextResponse.redirect(new URL(`/${maybeLocale}/login`, url));
  }
  return resNext;
}

// IMPORTANT: include "/" so middleware runs on home page
export const config = {
  matcher: [
    "/((?!api|_next/static|_next/image|favicon.ico).*)", // exclude system paths
  ],
};
