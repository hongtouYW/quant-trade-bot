import type { Route } from "./+types/publisher-info";
import { useLoaderData } from "react-router";
import { fetchPublisherInfo } from "@/services/publisher.service.ts";
import { SITE_NAME, BASE_URL } from "@/hooks/useSEO";
import {
  DEFAULT_LANG,
  buildHreflangTags,
  buildLangPath,
  normalizeLang,
} from "@/lib/i18n-routing";
import type { PublisherInfo as PublisherInfoType } from "@/types/publisher.types.ts";
import PublisherInfo from "@/pages/PublisherInfo.tsx";

export async function loader({ params }: Route.LoaderArgs) {
  const { id = "" } = params;
  if (!id) return null;
  const lang = normalizeLang(params.lang) ?? DEFAULT_LANG;
  try {
    const response = await fetchPublisherInfo(id, undefined, lang);
    if (response.code !== 1) return null;
    return response.data;
  } catch {
    return null;
  }
}

export function meta({ data, params }: Route.MetaArgs) {
  if (!data) return [{ title: SITE_NAME }];

  const lang = normalizeLang(params.lang) ?? DEFAULT_LANG;
  const title = data.name;
  const description = `${data.name} - 制片商主页，共 ${data.total_video} 部视频，${data.subscribe_count} 位订阅`;

  const unprefixedPath = `/publisher/${data.id}`;
  const canonicalUrl = `${BASE_URL}${buildLangPath(lang, unprefixedPath)}`;

  const tags: Route.MetaDescriptors = [
    { title: `${title} - ${SITE_NAME}` },
    { name: "description", content: description },
    { name: "robots", content: "index, follow" },
    { property: "og:type", content: "profile" },
    { property: "og:site_name", content: SITE_NAME },
    { property: "og:title", content: title },
    { property: "og:description", content: description },
    { name: "twitter:card", content: "summary_large_image" },
    { name: "twitter:title", content: title },
    { name: "twitter:description", content: description },
    { tagName: "link", rel: "canonical", href: canonicalUrl },
    { property: "og:url", content: canonicalUrl },
    ...buildHreflangTags(BASE_URL, unprefixedPath),
  ];

  if (data.image) {
    tags.push({ property: "og:image", content: data.image });
    tags.push({ name: "twitter:image", content: data.image });
  }

  tags.push({
    "script:ld+json": {
      "@context": "https://schema.org",
      "@type": "Organization",
      name: data.name,
      url: canonicalUrl,
      ...(data.image ? { image: data.image } : {}),
    },
  });

  return tags;
}

function PublisherInfoRoute() {
  const loaderData = useLoaderData() as PublisherInfoType | null;
  return <PublisherInfo loaderData={loaderData} />;
}

export default PublisherInfoRoute;
