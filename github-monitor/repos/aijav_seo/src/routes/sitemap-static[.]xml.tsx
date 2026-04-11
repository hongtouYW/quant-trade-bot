import { BASE_URL } from "@/hooks/useSEO";
import {
  SITEMAP_URLSET_CLOSE,
  SITEMAP_URLSET_OPEN,
  buildLocalizedUrlEntries,
} from "@/lib/i18n-routing";

const STATIC_URLS = [
  { loc: "/", changefreq: "daily", priority: "1.0" },
];

export async function loader() {
  const urlBlocks: string[] = [];

  for (const u of STATIC_URLS) {
    const localized = buildLocalizedUrlEntries({
      baseUrl: BASE_URL,
      unprefixedPath: u.loc,
    });
    // The helper emits bare <url>...</url> blocks. Append changefreq/priority
    // by post-processing each block before </url>.
    for (const block of localized) {
      urlBlocks.push(
        block.replace(
          /<\/url>$/,
          `    <changefreq>${u.changefreq}</changefreq>\n    <priority>${u.priority}</priority>\n  </url>`,
        ),
      );
    }
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
}
