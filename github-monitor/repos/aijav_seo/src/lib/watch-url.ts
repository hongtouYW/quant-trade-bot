import type { SupportedLang } from "./i18n-routing";
export {
  SUPPORTED_LANGS,
  normalizeLang,
  getLangFromPathname,
} from "./i18n-routing";
export type { SupportedLang } from "./i18n-routing";

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

/**
 * Build the unprefixed watch path (no lang segment).
 * Used for hreflang helpers that prepend the lang themselves.
 */
export function buildUnprefixedWatchPath(args: {
  id: number | string;
  slug: string;
}): string {
  const idPart = String(args.id).trim();
  return `/watch/${idPart}/${args.slug}/`;
}
