import type { Route } from "./+types/home";
import { SITE_NAME, BASE_URL } from "@/hooks/useSEO";
import {
  DEFAULT_LANG,
  buildHreflangTags,
  buildLangPath,
  normalizeLang,
} from "@/lib/i18n-routing";
import Home from "@/pages/Home.tsx";

const HOME_COPY: Record<string, { title: string; description: string }> = {
  zh: {
    title: `${SITE_NAME} - 高清日本成人视频在线观看`,
    description:
      "AI JAV 提供高清日本成人视频在线观看，海量资源每日更新，支持多语言字幕，VIP专享内容。",
  },
  en: {
    title: `${SITE_NAME} - HD JAV Streaming Online`,
    description:
      "AI JAV — HD Japanese adult videos streaming online, huge library updated daily, multi-language subtitles and VIP exclusive content.",
  },
};

export function meta({ params }: Route.MetaArgs) {
  const lang = normalizeLang(params.lang) ?? DEFAULT_LANG;
  const copy = HOME_COPY[lang] ?? HOME_COPY[DEFAULT_LANG];
  const canonicalUrl = `${BASE_URL}${buildLangPath(lang, "/")}`;

  return [
    { title: copy.title },
    { name: "description", content: copy.description },
    { name: "robots", content: "index, follow" },
    { property: "og:type", content: "website" },
    { property: "og:site_name", content: SITE_NAME },
    { property: "og:title", content: copy.title },
    { property: "og:description", content: copy.description },
    { property: "og:url", content: canonicalUrl },
    { name: "twitter:card", content: "summary_large_image" },
    { name: "twitter:title", content: copy.title },
    { name: "twitter:description", content: copy.description },
    { tagName: "link", rel: "canonical", href: canonicalUrl },
    ...buildHreflangTags(BASE_URL, "/"),
    {
      "script:ld+json": {
        "@context": "https://schema.org",
        "@type": "WebSite",
        name: SITE_NAME,
        url: canonicalUrl,
        inLanguage: lang,
      },
    },
  ];
}

export default Home;
