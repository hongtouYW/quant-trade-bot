import { Outlet, redirect } from "react-router";
import type { Route } from "./+types/lang-layout";
import {
  DEFAULT_LANG,
  buildLangPath,
  normalizeLang,
  stripLangPrefix,
} from "@/lib/i18n-routing";

/**
 * Parent route for all language-prefixed SSR routes.
 *
 * Validates that :lang is a supported language. If not, 301-redirects to
 * the default-language version of the same path so we never render content
 * under an unknown lang segment.
 */
export async function loader({ params, request }: Route.LoaderArgs) {
  const lang = normalizeLang(params.lang);
  if (!lang) {
    // params.lang was something like "foo" that happened to match the :lang
    // segment. Treat the whole path as unprefixed and redirect to default lang.
    const url = new URL(request.url);
    const stripped = stripLangPrefix(url.pathname);
    const target = `${buildLangPath(DEFAULT_LANG, stripped)}${url.search}`;
    throw redirect(target, 301);
  }
  return { lang };
}

export default function LangLayout() {
  return <Outlet />;
}
