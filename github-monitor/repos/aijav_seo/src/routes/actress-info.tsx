import type { Route } from "./+types/actress-info";
import { useLoaderData } from "react-router";
import { fetchActorInfo } from "@/services/actor.service.ts";
import { SITE_NAME, BASE_URL } from "@/hooks/useSEO";
import {
  DEFAULT_LANG,
  buildHreflangTags,
  buildLangPath,
  normalizeLang,
} from "@/lib/i18n-routing";
import type { ActorInfo } from "@/types/actor.types.ts";
import ActressInfo from "@/pages/ActressInfo.tsx";

export async function loader({ params }: Route.LoaderArgs) {
  const { id = "" } = params;
  if (!id) return null;
  const lang = normalizeLang(params.lang) ?? DEFAULT_LANG;
  try {
    // Forward the URL lang explicitly — during SSR the axios interceptor
    // reads i18n.language which is a singleton shared across requests.
    const response = await fetchActorInfo(id, undefined, lang);
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
  const description = `${data.name} - 演员主页，共 ${data.video_count} 部视频，${data.subscribe_count} 位粉丝`;

  const unprefixedPath = `/actress/${data.id}`;
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

  const personSchema: Record<string, unknown> = {
    "@context": "https://schema.org",
    "@type": "Person",
    name: data.name,
    url: canonicalUrl,
  };
  if (data.image) personSchema.image = data.image;
  const nationalityName = typeof data.nationality === "string" ? data.nationality : data.nationality?.name;
  if (nationalityName)
    personSchema.nationality = { "@type": "Country", name: nationalityName };

  tags.push({ "script:ld+json": personSchema });

  return tags;
}

function ActressInfoRoute() {
  const loaderData = useLoaderData() as ActorInfo | null;
  return <ActressInfo loaderData={loaderData} />;
}

export default ActressInfoRoute;
