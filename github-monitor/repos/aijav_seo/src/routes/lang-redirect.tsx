import { redirect } from "react-router";
import type { Route } from "./+types/lang-redirect";
import {
  buildLangPath,
  parseCookieLang,
  pickPreferredLang,
  getLangFromPathname,
} from "@/lib/i18n-routing";

/**
 * Catches unprefixed SSR routes (e.g. "/actress/5", "/", "/watch/123")
 * and 301-redirects them to the language-prefixed variant.
 *
 * Lang selection priority:
 *   1. "lang" cookie (set by language switcher / LanguageRouteSync)
 *   2. DEFAULT_LANG ("zh")
 *
 * If the path already has a valid lang prefix, this loader should not
 * normally be hit — routing layer handles that case. Defensive check anyway.
 */
export async function loader({ request }: Route.LoaderArgs) {
  const url = new URL(request.url);

  // Defensive: if somehow this fires for an already-prefixed path, don't loop
  if (getLangFromPathname(url.pathname)) {
    return new Response("Not found", { status: 404 });
  }

  const cookieLang = parseCookieLang(request.headers.get("cookie"));
  const lang = pickPreferredLang({ cookieLang });

  const target = `${buildLangPath(lang, url.pathname)}${url.search}`;
  throw redirect(target, 301);
}

// This route never renders — the loader always throws a redirect.
export default function LangRedirect() {
  return null;
}
