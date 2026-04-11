import { fetchPublisherList } from "@/services/publisher.service";
import { BASE_URL } from "@/hooks/useSEO";
import {
  SITEMAP_URLSET_CLOSE,
  SITEMAP_URLSET_OPEN,
  buildLocalizedUrlEntries,
} from "@/lib/i18n-routing";

export async function loader() {
  try {
    const res = await fetchPublisherList({ page: 1, limit: 5000 });
    const publishers = res.data?.data ?? [];

    const urlBlocks: string[] = [];
    for (const p of publishers) {
      urlBlocks.push(
        ...buildLocalizedUrlEntries({
          baseUrl: BASE_URL,
          unprefixedPath: `/publisher/${p.id}`,
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
        "Cache-Control": "public, max-age=86400",
      },
    });
  } catch {
    return new Response("Internal Server Error", { status: 500 });
  }
}
