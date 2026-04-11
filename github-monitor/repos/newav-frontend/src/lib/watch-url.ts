const SUPPORTED_LANGS = ["en", "zh"] as const;

export type SupportedLang = (typeof SUPPORTED_LANGS)[number];

export function normalizeLang(lang?: string | null): SupportedLang | null {
  if (!lang) return null;
  const normalized = lang.toLowerCase();
  return SUPPORTED_LANGS.includes(normalized as SupportedLang)
    ? (normalized as SupportedLang)
    : null;
}

export function getLangFromPathname(pathname: string): SupportedLang | null {
  const match = pathname.match(/^\/(en|zh)(\/|$)/);
  return match ? (match[1] as SupportedLang) : null;
}

export function buildVideoSlug(mash?: string, title?: string): string | null {
  const mashText = (mash || "").trim();
  let titleText = (title || "").trim();
  if (mashText) {
    const mashRegex = new RegExp(`^${mashText}\\s*`, "i");
    titleText = titleText.replace(mashRegex, "");
  }
  const base = [mashText, titleText].filter(Boolean).join(" ");
  if (!base) return null;
  return base
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

export function buildWatchPath(args: {
  lang: SupportedLang;
  id: number | string;
  slug: string;
}): string {
  const idPart = String(args.id).trim();
  return `/${args.lang}/watch/${idPart}/${args.slug}/`;
}
