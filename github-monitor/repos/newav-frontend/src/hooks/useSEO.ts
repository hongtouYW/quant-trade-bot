import { useEffect } from "react";

interface SEOConfig {
  title?: string;
  description?: string;
  keywords?: string;
  canonicalUrl?: string;
  ogType?: "website" | "video.other" | "article";
  ogImage?: string;
  ogUrl?: string;
  twitterCard?: "summary" | "summary_large_image" | "player";
  noIndex?: boolean;
}

interface VideoSEOConfig extends SEOConfig {
  videoName?: string;
  videoDuration?: string; // ISO 8601 duration format PT1H30M
  videoThumbnail?: string;
  videoUploadDate?: string;
  videoActors?: string[];
  videoPublisher?: string;
  videoRating?: number;
  videoViewCount?: number;
}

const SITE_NAME = "AI JAV";
const DEFAULT_DESCRIPTION =
  "AI JAV 提供高清日本成人视频在线观看，海量资源每日更新，支持多语言字幕，VIP专享内容。";
const BASE_URL = "https://xtw53.top";

function setMetaTag(
  name: string,
  content: string,
  attribute: "name" | "property" = "name",
) {
  let element = document.querySelector(
    `meta[${attribute}="${name}"]`,
  ) as HTMLMetaElement | null;

  if (!element) {
    element = document.createElement("meta");
    element.setAttribute(attribute, name);
    document.head.appendChild(element);
  }

  element.content = content;
}

function removeMetaTag(name: string, attribute: "name" | "property" = "name") {
  const element = document.querySelector(`meta[${attribute}="${name}"]`);
  if (element) {
    element.remove();
  }
}

function removeMetaTags(name: string, attribute: "name" | "property" = "name") {
  document
    .querySelectorAll(`meta[${attribute}="${name}"]`)
    .forEach((element) => element.remove());
}

function addMetaTags(
  name: string,
  values: string[],
  attribute: "name" | "property" = "name",
) {
  values.forEach((value) => {
    const element = document.createElement("meta");
    element.setAttribute(attribute, name);
    element.content = value;
    document.head.appendChild(element);
  });
}

function setCanonicalUrl(url: string) {
  let link = document.querySelector(
    'link[rel="canonical"]',
  ) as HTMLLinkElement | null;

  if (!link) {
    link = document.createElement("link");
    link.rel = "canonical";
    document.head.appendChild(link);
  }

  link.href = url;
}

function setJsonLd(id: string, data: object) {
  let script = document.getElementById(id) as HTMLScriptElement | null;

  if (!script) {
    script = document.createElement("script");
    script.id = id;
    script.type = "application/ld+json";
    document.head.appendChild(script);
  }

  script.textContent = JSON.stringify(data);
}

function removeJsonLd(id: string) {
  const script = document.getElementById(id);
  if (script) {
    script.remove();
  }
}

/**
 * Convert duration string like "01:30:45" to ISO 8601 format "PT1H30M45S"
 */
function durationToISO8601(duration: string): string {
  if (!duration) return "PT0S";

  const parts = duration.split(":").map(Number);
  let hours = 0,
    minutes = 0,
    seconds = 0;

  if (parts.length === 3) {
    [hours, minutes, seconds] = parts;
  } else if (parts.length === 2) {
    [minutes, seconds] = parts;
  } else if (parts.length === 1) {
    [seconds] = parts;
  }

  let iso = "PT";
  if (hours > 0) iso += `${hours}H`;
  if (minutes > 0) iso += `${minutes}M`;
  if (seconds > 0) iso += `${seconds}S`;

  return iso === "PT" ? "PT0S" : iso;
}

/**
 * Hook for managing page SEO meta tags
 */
export function useSEO(config: SEOConfig) {
  useEffect(() => {
    const {
      title,
      description = DEFAULT_DESCRIPTION,
      keywords,
      canonicalUrl,
      ogType = "website",
      ogImage,
      ogUrl,
      twitterCard = "summary_large_image",
      noIndex = false,
    } = config;

    // Set document title
    if (title) {
      document.title = `${title} - ${SITE_NAME}`;
    }

    // Basic meta tags
    setMetaTag("description", description);
    if (keywords) {
      setMetaTag("keywords", keywords);
    }
    if (noIndex) {
      setMetaTag("robots", "noindex, nofollow");
    } else {
      setMetaTag("robots", "index, follow");
    }

    // Canonical URL
    if (canonicalUrl) {
      setCanonicalUrl(canonicalUrl);
    }

    // Open Graph tags
    setMetaTag("og:type", ogType, "property");
    setMetaTag("og:site_name", SITE_NAME, "property");
    if (title) {
      setMetaTag("og:title", title, "property");
    }
    setMetaTag("og:description", description, "property");
    if (ogImage) {
      setMetaTag("og:image", ogImage, "property");
    }
    if (ogUrl) {
      setMetaTag("og:url", ogUrl, "property");
    }

    // Twitter Card tags
    setMetaTag("twitter:card", twitterCard);
    if (title) {
      setMetaTag("twitter:title", title);
    }
    setMetaTag("twitter:description", description);
    if (ogImage) {
      setMetaTag("twitter:image", ogImage);
    }
    if (ogUrl) {
      setMetaTag("twitter:url", ogUrl);
    }

    // Cleanup function to reset to defaults
    return () => {
      document.title = `${SITE_NAME} - 高清日本成人视频在线观看`;
      setMetaTag("description", DEFAULT_DESCRIPTION);
      setMetaTag("og:type", "website", "property");
      setMetaTag("og:title", SITE_NAME, "property");
      setMetaTag("og:description", DEFAULT_DESCRIPTION, "property");
      setMetaTag("og:url", BASE_URL, "property");
      setMetaTag("twitter:title", SITE_NAME);
      setMetaTag("twitter:description", DEFAULT_DESCRIPTION);
      setMetaTag("twitter:url", BASE_URL);
      removeMetaTag("keywords");
    };
  }, [config]);
}

/**
 * Hook for video page SEO with JSON-LD structured data
 */
export function useVideoSEO(config: VideoSEOConfig) {
  useEffect(() => {
    const {
      title,
      description = DEFAULT_DESCRIPTION,
      keywords,
      canonicalUrl,
      ogImage,
      ogUrl,
      videoName,
      videoDuration,
      videoThumbnail,
      videoUploadDate,
      videoActors = [],
      videoPublisher,
      videoRating,
      videoViewCount,
    } = config;

    // Set document title
    if (title) {
      document.title = `${title} - ${SITE_NAME}`;
    }

    // Basic meta tags
    setMetaTag("description", description);
    if (keywords) {
      setMetaTag("keywords", keywords);
    }
    setMetaTag("robots", "index, follow");

    // Canonical URL
    if (canonicalUrl) {
      setCanonicalUrl(canonicalUrl);
    }

    // Open Graph tags for video
    setMetaTag("og:type", "video.other", "property");
    setMetaTag("og:site_name", SITE_NAME, "property");
    if (title) {
      setMetaTag("og:title", title, "property");
    }
    setMetaTag("og:description", description, "property");
    if (ogImage || videoThumbnail) {
      setMetaTag("og:image", ogImage || videoThumbnail || "", "property");
    }
    if (ogUrl) {
      setMetaTag("og:url", ogUrl, "property");
    }
    removeMetaTags("og:video:tag", "property");
    if (videoName) {
      setMetaTag("og:video:tag", videoName, "property");
    }
    if (keywords) {
      const keywordList = keywords
        .split(",")
        .map((value) => value.trim())
        .filter(Boolean);
      if (keywordList.length > 0) {
        addMetaTags("og:video:tag", keywordList, "property");
      }
    }
    removeMetaTags("video:actor", "property");
    if (videoActors.length > 0) {
      addMetaTags("video:actor", videoActors, "property");
    }

    // Twitter Card tags for video
    setMetaTag("twitter:card", "summary_large_image");
    if (title) {
      setMetaTag("twitter:title", title);
    }
    setMetaTag("twitter:description", description);
    if (ogImage || videoThumbnail) {
      setMetaTag("twitter:image", ogImage || videoThumbnail || "");
    }
    if (ogUrl) {
      setMetaTag("twitter:url", ogUrl);
    }

    // JSON-LD VideoObject structured data
    if (videoName) {
      const videoSchema: Record<string, unknown> = {
        "@context": "https://schema.org",
        "@type": "VideoObject",
        name: videoName,
        description: description,
        thumbnailUrl: videoThumbnail || ogImage,
        uploadDate: videoUploadDate || new Date().toISOString().split("T")[0],
      };

      if (videoDuration) {
        videoSchema.duration = durationToISO8601(videoDuration);
      }

      if (videoActors.length > 0) {
        videoSchema.actor = videoActors.map((name) => ({
          "@type": "Person",
          name: name,
        }));
      }

      if (videoPublisher) {
        videoSchema.publisher = {
          "@type": "Organization",
          name: videoPublisher,
        };
      }

      if (videoRating !== undefined && videoRating > 0) {
        videoSchema.aggregateRating = {
          "@type": "AggregateRating",
          ratingValue: videoRating,
          bestRating: 5,
          worstRating: 1,
        };
      }

      if (videoViewCount !== undefined && videoViewCount > 0) {
        videoSchema.interactionStatistic = {
          "@type": "InteractionCounter",
          interactionType: { "@type": "WatchAction" },
          userInteractionCount: videoViewCount,
        };
      }

      if (keywords) {
        videoSchema.keywords = keywords;
      }

      if (canonicalUrl) {
        videoSchema.url = canonicalUrl;
      }

      setJsonLd("video-structured-data", videoSchema);
    }

    // BreadcrumbList structured data
    const breadcrumbSchema = {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        {
          "@type": "ListItem",
          position: 1,
          name: "首页",
          item: BASE_URL,
        },
        {
          "@type": "ListItem",
          position: 2,
          name: videoName || "视频",
          item: canonicalUrl,
        },
      ],
    };

    setJsonLd("breadcrumb-structured-data", breadcrumbSchema);

    // Cleanup function
    return () => {
      document.title = `${SITE_NAME} - 高清日本成人视频在线观看`;
      setMetaTag("description", DEFAULT_DESCRIPTION);
      setMetaTag("og:type", "website", "property");
      setMetaTag("og:title", SITE_NAME, "property");
      setMetaTag("og:description", DEFAULT_DESCRIPTION, "property");
      setMetaTag("og:url", BASE_URL, "property");
      setMetaTag("twitter:card", "summary_large_image");
      setMetaTag("twitter:title", SITE_NAME);
      setMetaTag("twitter:description", DEFAULT_DESCRIPTION);
      setMetaTag("twitter:url", BASE_URL);
      removeMetaTag("keywords");
      removeMetaTag("og:video:tag", "property");
      removeMetaTags("video:actor", "property");
      removeJsonLd("video-structured-data");
      removeJsonLd("breadcrumb-structured-data");
    };
  }, [config]);
}

export { BASE_URL, SITE_NAME, DEFAULT_DESCRIPTION };
