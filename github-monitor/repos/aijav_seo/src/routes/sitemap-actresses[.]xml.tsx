import { fetchActorList } from "@/services/actor.service";
import { BASE_URL } from "@/hooks/useSEO";
import {
  SITEMAP_URLSET_CLOSE,
  SITEMAP_URLSET_OPEN,
  buildLocalizedUrlEntries,
} from "@/lib/i18n-routing";

const ACTORS_PER_PAGE = 5000;
const MAX_PAGES = 20;

export async function loader() {
  try {
    const urlBlocks: string[] = [];
    let page = 1;
    let lastPage = 1;

    do {
      const res = await fetchActorList({ page, limit: ACTORS_PER_PAGE });
      const actors = res.data?.data ?? [];
      lastPage = Math.min(res.data?.last_page ?? 1, MAX_PAGES);

      for (const actor of actors) {
        urlBlocks.push(
          ...buildLocalizedUrlEntries({
            baseUrl: BASE_URL,
            unprefixedPath: `/actress/${actor.id}`,
          }),
        );
      }
      page++;
    } while (page <= lastPage);

    const xml = `<?xml version="1.0" encoding="UTF-8"?>
${SITEMAP_URLSET_OPEN}
${urlBlocks.join("\n")}
${SITEMAP_URLSET_CLOSE}`;

    return new Response(xml, {
      headers: {
        "Content-Type": "application/xml",
        "Cache-Control": "public, max-age=86400",
      },
    });
  } catch {
    return new Response("Internal Server Error", { status: 500 });
  }
}
