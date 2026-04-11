import type { Route } from "./+types/video-player";
import { useLoaderData } from "react-router";
import { fetchVideoInfo } from "@/services/video.service.ts";
import {
  SITE_NAME,
  BASE_URL,
  durationToISO8601,
} from "@/hooks/useSEO";
import { buildVideoSlug, buildWatchPath } from "@/lib/watch-url";
import {
  DEFAULT_LANG,
  SUPPORTED_LANGS,
  normalizeLang,
} from "@/lib/i18n-routing";
import type { SupportedLang } from "@/lib/i18n-routing";
import type { VideoInfo } from "@/types/video.types.ts";
import VideoPlayer from "@/pages/VideoPlayer.tsx";

const HREFLANG_LANGS: readonly SupportedLang[] = SUPPORTED_LANGS;

export async function loader({ params }: Route.LoaderArgs) {
  const vid = Number(params.id);
  if (isNaN(vid)) return null;
  const lang = normalizeLang(params.lang) ?? DEFAULT_LANG;
  try {
    const response = await fetchVideoInfo({ vid }, undefined, lang);
    // Return data even for non-1 codes (e.g. auth-required videos).
    // The API may still return video metadata (title, thumb, actors)
    // which lets us render meaningful meta tags and page body for SEO
    // instead of a soft 404.
    if (response.data) return response.data;
    return null;
  } catch {
    return null;
  }
}

/**
 * Build a rich meta description from video data.
 * Falls back to a composed description when the API description is missing
 * or is just a duplicate of the title.
 */
function buildDescription(
  data: { description?: string; mash_title?: string; title?: string },
  actorNames: string[],
  tagNames: string[],
  publisherName?: string,
  duration?: string,
): string {
  const rawDesc = (data.description || "").trim();
  const titleText = (data.mash_title || data.title || "").trim();

  // Use the API description only when it's meaningfully different from the title
  if (rawDesc && rawDesc !== titleText && rawDesc.length > titleText.length) {
    return rawDesc;
  }

  // Build a richer description from available fields
  const parts: string[] = [];
  parts.push(`观看 ${titleText}`);
  if (actorNames.length > 0) parts.push(`主演：${actorNames.join("、")}`);
  if (tagNames.length > 0) parts.push(`分类：${tagNames.join("、")}`);
  if (duration) parts.push(`时长：${duration}`);
  if (publisherName) parts.push(`${publisherName} 出品`);

  return `${parts.join(" | ")} - ${SITE_NAME}`;
}

export function meta({ data, params }: Route.MetaArgs) {
  if (!data) return [{ title: SITE_NAME }];

  const title = data.mash_title || data.title || "视频播放";
  const actorNames =
    data.actor?.map((a: { name?: string }) => a.name).filter((n: string | undefined): n is string => Boolean(n)) ?? [];
  const tagNames = data.tags?.map((t: { name?: string }) => t.name).filter((n: string | undefined): n is string => Boolean(n)) ?? [];
  const description = buildDescription(data, actorNames, tagNames, data.publisher?.name, data.duration);
  const keywords = tagNames.length > 0 ? tagNames.join(",") : undefined;

  const resolvedLang = normalizeLang(params.lang) ?? DEFAULT_LANG;
  const canonicalSlug = buildVideoSlug(data.mash, data.title);
  const canonicalPath =
    data.id && canonicalSlug
      ? buildWatchPath({ lang: resolvedLang, id: data.id, slug: canonicalSlug })
      : null;
  const canonicalUrl = canonicalPath ? `${BASE_URL}${canonicalPath}` : undefined;

  const tags: Route.MetaDescriptors = [
    { title: `${title} - ${SITE_NAME}` },
    { name: "description", content: description },
    { name: "robots", content: "index, follow" },
    { property: "og:type", content: "video.other" },
    { property: "og:site_name", content: SITE_NAME },
    { property: "og:title", content: title },
    { property: "og:description", content: description },
    { name: "twitter:card", content: "summary_large_image" },
    { name: "twitter:title", content: title },
    { name: "twitter:description", content: description },
  ];

  if (data.thumb) {
    tags.push({ property: "og:image", content: data.thumb });
    tags.push({ name: "twitter:image", content: data.thumb });
  }
  if (canonicalUrl) {
    tags.push({ tagName: "link", rel: "canonical", href: canonicalUrl });
    tags.push({ property: "og:url", content: canonicalUrl });
    tags.push({ name: "twitter:url", content: canonicalUrl });
  }
  if (keywords) {
    tags.push({ name: "keywords", content: keywords });
  }
  actorNames.forEach((name: string) =>
    tags.push({ property: "video:actor", content: name }),
  );

  // Hreflang alternate links for each supported language
  if (data.id && canonicalSlug) {
    HREFLANG_LANGS.forEach((lang) => {
      const altPath = buildWatchPath({ lang, id: data.id, slug: canonicalSlug });
      tags.push({ tagName: "link", rel: "alternate", hreflang: lang, href: `${BASE_URL}${altPath}` });
    });
    // x-default points to the default-language version
    const defaultPath = buildWatchPath({ lang: DEFAULT_LANG, id: data.id, slug: canonicalSlug });
    tags.push({ tagName: "link", rel: "alternate", hreflang: "x-default", href: `${BASE_URL}${defaultPath}` });
  }

  // JSON-LD VideoObject
  const videoSchema: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "VideoObject",
    name: title,
    description,
    thumbnailUrl: data.thumb,
    uploadDate: (() => {
      const ts = Number(data.publish_date);
      if (data.publish_date && !Number.isNaN(ts) && ts > 0) {
        return new Date(ts * 1000).toISOString();
      }
      return new Date().toISOString().split("T")[0];
    })(),
  };
  if (data.duration) videoSchema.duration = durationToISO8601(data.duration);
  if (actorNames.length > 0)
    videoSchema.actor = actorNames.map((name: string) => ({ "@type": "Person", name }));
  if (data.publisher?.name)
    videoSchema.publisher = { "@type": "Organization", name: data.publisher.name };
  if (data.rating_avg > 0 && data.rating_count > 0)
    videoSchema.aggregateRating = {
      "@type": "AggregateRating",
      ratingValue: data.rating_avg,
      ratingCount: data.rating_count,
      bestRating: 5,
      worstRating: 1,
    };
  if (data.play > 0)
    videoSchema.interactionStatistic = {
      "@type": "InteractionCounter",
      interactionType: { "@type": "WatchAction" },
      userInteractionCount: data.play,
    };
  if (keywords) videoSchema.keywords = keywords;
  if (canonicalUrl) videoSchema.url = canonicalUrl;

  tags.push({ "script:ld+json": videoSchema });
  tags.push({
    "script:ld+json": {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        { "@type": "ListItem", position: 1, name: "首页", item: BASE_URL },
        { "@type": "ListItem", position: 2, name: title, item: canonicalUrl },
      ],
    },
  });

  return tags;
}

function VideoPlayerRoute() {
  const loaderData = useLoaderData() as VideoInfo | null;
  return <VideoPlayer loaderData={loaderData} />;
}

export default VideoPlayerRoute;
