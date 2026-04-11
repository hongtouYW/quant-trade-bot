import { fetchVideoList } from "@/services/video.service";
import { BASE_URL } from "@/hooks/useSEO";

const VIDEOS_PER_SITEMAP = 1000;

export async function loader() {
  // Fetch page 1 with limit 1 just to get the total count
  let totalVideos = 0;
  try {
    const res = await fetchVideoList({ page: 1, limit: 1 });
    totalVideos = res.data?.total ?? 0;
  } catch {
    // If the API fails, we still return the index with static/actress/publisher sitemaps
  }

  const videoPages = Math.ceil(totalVideos / VIDEOS_PER_SITEMAP) || 1;

  const sitemaps = [
    `${BASE_URL}/sitemap-static.xml`,
    ...Array.from({ length: videoPages }, (_, i) => `${BASE_URL}/sitemap-videos/${i + 1}.xml`),
    `${BASE_URL}/sitemap-actresses.xml`,
    `${BASE_URL}/sitemap-publishers.xml`,
  ];

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${sitemaps.map((loc) => `  <sitemap><loc>${loc}</loc></sitemap>`).join("\n")}
</sitemapindex>`;

  return new Response(xml, {
    headers: {
      "Content-Type": "application/xml",
      "Cache-Control": "public, max-age=3600",
    },
  });
}

