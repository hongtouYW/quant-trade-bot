import type { Route } from "./+types/sitemap-videos-$page[.]xml";
import { BASE_URL } from "@/hooks/useSEO";
import { buildVideoSlug, buildWatchPath } from "@/lib/watch-url";
import {
  SITEMAP_URLSET_CLOSE,
  SITEMAP_URLSET_OPEN,
  SUPPORTED_LANGS,
  buildLocalizedUrlEntries,
} from "@/lib/i18n-routing";
import type { SupportedLang } from "@/lib/i18n-routing";
import axios from "@/lib/axios";
import type { ApiResponse, PaginatedData } from "@/types/api-response";
import type { Video } from "@/types/video.types";

const VIDEOS_PER_SITEMAP = 1000;

function toISODate(timestamp: string): string {
  if (!timestamp) return new Date().toISOString();
  const num = Number(timestamp);
  if (Number.isNaN(num)) return timestamp;
  return new Date(num * 1000).toISOString();
}

async function fetchVideoListWithLang(
  page: number,
  limit: number,
  lang: string,
): Promise<ApiResponse<PaginatedData<Video>>> {
  const res = await axios.post(
    "/video/lists",
    { page, limit },
    { headers: { lang } },
  );
  return res.data;
}

export async function loader({ params }: Route.LoaderArgs) {
  const page = Number(params.page);
  if (!page || page < 1) {
    return new Response("Not found", { status: 404 });
  }

  try {
    // Fetch all supported languages in parallel so we can build per-lang slugs
    const results = await Promise.all(
      SUPPORTED_LANGS.map((lang) =>
        fetchVideoListWithLang(page, VIDEOS_PER_SITEMAP, lang),
      ),
    );

    // Map lang -> (videoId -> Video) so we can look up translated titles
    const videosByLang = new Map<SupportedLang, Map<number, Video>>();
    SUPPORTED_LANGS.forEach((lang, i) => {
      const map = new Map<number, Video>();
      for (const v of results[i].data?.data ?? []) {
        map.set(v.id, v);
      }
      videosByLang.set(lang, map);
    });

    // Use the first language's list as the canonical set of IDs for this page
    const primaryVideos = results[0].data?.data ?? [];
    if (primaryVideos.length === 0) {
      return new Response("Not found", { status: 404 });
    }

    const urlBlocks: string[] = [];
    for (const v of primaryVideos) {
      // Build the per-language path using each language's localized slug
      const langPaths: Partial<Record<SupportedLang, string>> = {};
      let anySlugValid = false;
      for (const lang of SUPPORTED_LANGS) {
        const video = videosByLang.get(lang)?.get(v.id) ?? v;
        const slug = buildVideoSlug(video.mash, video.title);
        if (!slug) continue;
        langPaths[lang] = buildWatchPath({ lang, id: video.id, slug });
        anySlugValid = true;
      }
      if (!anySlugValid) continue;

      urlBlocks.push(
        ...buildLocalizedUrlEntries({
          baseUrl: BASE_URL,
          // unprefixedPath is unused when langPaths covers all langs, but we
          // still provide a sensible default for the shape.
          unprefixedPath: `/watch/${v.id}`,
          lastmod: toISODate(v.publish_date),
          langPaths,
        }),
      );
    }

    const xml = `<?xml version="1.0" encoding="UTF-8"?>
${SITEMAP_URLSET_OPEN}
${urlBlocks.join("\n")}
${SITEMAP_URLSET_CLOSE}`;

    return new Response(xml, {
      headers: {
        "Content-Type": "application/xml",
        "Cache-Control": "public, max-age=3600",
      },
    });
  } catch {
    return new Response("Internal Server Error", { status: 500 });
  }
}
