/**
 * Single source of truth for language URL routing.
 *
 * Keep SUPPORTED_LANGS in sync with i18n resources and sitemap coverage.
 * Adding a new language to URL routing is (almost) a one-line change here —
 * also remember to add the translation file and verify the API supports it.
 */

export const SUPPORTED_LANGS = ["zh", "en"] as const;
export const DEFAULT_LANG: SupportedLang = "zh";
export const LANG_COOKIE_NAME = "lang";
export const LANG_COOKIE_MAX_AGE = 60 * 60 * 24 * 365; // 1 year

export type SupportedLang = (typeof SUPPORTED_LANGS)[number];

export function normalizeLang(lang?: string | null): SupportedLang | null {
  if (!lang) return null;
  const normalized = lang.toLowerCase();
  return SUPPORTED_LANGS.includes(normalized as SupportedLang)
    ? (normalized as SupportedLang)
    : null;
}

/**
 * Extract a supported lang from the first path segment.
 * Returns null if the first segment isn't a supported language.
 */
export function getLangFromPathname(pathname: string): SupportedLang | null {
  const match = pathname.match(/^\/([^/]+)(?:\/|$)/);
  if (!match) return null;
  return normalizeLang(match[1]);
}

/**
 * Remove the lang prefix from a pathname if present.
 * "/en/actress/5" -> "/actress/5"
 * "/actress/5"    -> "/actress/5"
 * "/en"           -> "/"
 */
export function stripLangPrefix(pathname: string): string {
  const lang = getLangFromPathname(pathname);
  if (!lang) return pathname;
  const stripped = pathname.slice(`/${lang}`.length);
  return stripped === "" ? "/" : stripped;
}

/**
 * Prepend a lang prefix to a pathname.
 * buildLangPath("en", "/actress/5") -> "/en/actress/5"
 * buildLangPath("en", "/")          -> "/en/"
 */
export function buildLangPath(lang: SupportedLang, pathname: string): string {
  const path = pathname.startsWith("/") ? pathname : `/${pathname}`;
  if (path === "/") return `/${lang}/`;
  return `/${lang}${path}`;
}

/**
 * Swap the lang prefix on a pathname, or add it if missing.
 * Preserves trailing segments, search, and hash when passed via full URL parts.
 */
export function swapLangPrefix(
  pathname: string,
  nextLang: SupportedLang,
): string {
  return buildLangPath(nextLang, stripLangPrefix(pathname));
}

/**
 * Parse a cookie header string and return the lang cookie value if valid.
 * Safe to call with null/undefined.
 */
export function parseCookieLang(
  cookieHeader: string | null | undefined,
): SupportedLang | null {
  if (!cookieHeader) return null;
  // Simple parse — we only care about one cookie
  const pairs = cookieHeader.split(/;\s*/);
  for (const pair of pairs) {
    const eq = pair.indexOf("=");
    if (eq === -1) continue;
    const name = pair.slice(0, eq).trim();
    if (name !== LANG_COOKIE_NAME) continue;
    const value = decodeURIComponent(pair.slice(eq + 1).trim());
    return normalizeLang(value);
  }
  return null;
}

/**
 * Decide which lang to use for an unprefixed inbound request.
 * Priority: explicit cookie > default (zh).
 *
 * Accept-Language header is intentionally ignored for now — per product
 * decision, unprefixed visitors default to zh unless they have a saved pref.
 */
export function pickPreferredLang(args: {
  cookieLang?: SupportedLang | null;
}): SupportedLang {
  return args.cookieLang ?? DEFAULT_LANG;
}

/**
 * Build the Set-Cookie header value for the lang cookie.
 * Used by SSR loaders that want to persist the lang decision.
 */
export function buildLangCookie(lang: SupportedLang): string {
  return `${LANG_COOKIE_NAME}=${lang}; Path=/; Max-Age=${LANG_COOKIE_MAX_AGE}; SameSite=Lax`;
}

/**
 * Escape a value for safe inclusion in XML text/attributes.
 */
export function escapeXml(str: string): string {
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

/**
 * Build an array of <url> XML blocks for a single piece of content across
 * every supported language, following Google's hreflang sitemap spec.
 *
 * Each <url> block contains:
 *   - <loc> pointing at that language's URL
 *   - <xhtml:link rel="alternate" hreflang="xx"> for every supported lang
 *     (including a self-reference, which the spec requires)
 *   - <xhtml:link rel="alternate" hreflang="x-default"> pointing to DEFAULT_LANG
 *   - Optional <lastmod>
 *
 * Callers should wrap the result in a <urlset xmlns:xhtml="..."> element.
 *
 * See: https://developers.google.com/search/docs/specialized/international/localized-versions#sitemap
 */
export function buildLocalizedUrlEntries(args: {
  baseUrl: string;
  /** Path without the lang prefix, e.g. "/actress/5" or "/watch/123/slug/" */
  unprefixedPath: string;
  /** Optional ISO lastmod string */
  lastmod?: string;
  /** Override the default language list (e.g. per-lang slugs for videos) */
  langPaths?: Partial<Record<SupportedLang, string>>;
}): string[] {
  const { baseUrl, unprefixedPath, lastmod, langPaths } = args;

  // Resolve the URL for each language (allow overrides for cases where the
  // path itself differs per language, e.g. localized video slugs).
  const urlsByLang = new Map<SupportedLang, string>();
  for (const lang of SUPPORTED_LANGS) {
    const override = langPaths?.[lang];
    const path = override ?? buildLangPath(lang, unprefixedPath);
    urlsByLang.set(lang, `${baseUrl}${path}`);
  }

  const defaultUrl = urlsByLang.get(DEFAULT_LANG)!;

  const blocks: string[] = [];
  for (const lang of SUPPORTED_LANGS) {
    const loc = urlsByLang.get(lang)!;
    const alternates = SUPPORTED_LANGS.map(
      (l) =>
        `    <xhtml:link rel="alternate" hreflang="${l}" href="${escapeXml(
          urlsByLang.get(l)!,
        )}"/>`,
    ).join("\n");
    const xDefault = `    <xhtml:link rel="alternate" hreflang="x-default" href="${escapeXml(
      defaultUrl,
    )}"/>`;
    const lastmodLine = lastmod ? `\n    <lastmod>${lastmod}</lastmod>` : "";
    blocks.push(
      `  <url>
    <loc>${escapeXml(loc)}</loc>${lastmodLine}
${alternates}
${xDefault}
  </url>`,
    );
  }
  return blocks;
}

export const SITEMAP_URLSET_OPEN = `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">`;
export const SITEMAP_URLSET_CLOSE = `</urlset>`;

/**
 * A hreflang <link> tag, shaped to satisfy React Router's MetaDescriptor
 * (which requires an index signature).
 */
export type HreflangTag = {
  tagName: "link";
  rel: "alternate";
  hreflang: string;
  href: string;
  [key: string]: unknown;
};

/**
 * Build hreflang alternate <link> tags for a given unprefixed path across
 * every supported language plus an x-default pointing at DEFAULT_LANG.
 *
 * The `path` argument should be the language-independent pathname
 * (e.g. "/actress/5" or "/watch/123/my-slug/").
 */
export function buildHreflangTags(
  baseUrl: string,
  unprefixedPath: string,
): HreflangTag[] {
  const tags: HreflangTag[] = SUPPORTED_LANGS.map((lang) => ({
    tagName: "link",
    rel: "alternate",
    hreflang: lang,
    href: `${baseUrl}${buildLangPath(lang, unprefixedPath)}`,
  }));
  tags.push({
    tagName: "link",
    rel: "alternate",
    hreflang: "x-default",
    href: `${baseUrl}${buildLangPath(DEFAULT_LANG, unprefixedPath)}`,
  });
  return tags;
}
