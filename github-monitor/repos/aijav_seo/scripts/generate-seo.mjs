import fs from "node:fs/promises";
import path from "node:path";
import CryptoJS from "crypto-js";

const API_BASE = "https://newavapi.9xyrp3kg4b86.com/";
const BASE_URL = process.env.SEO_BASE_URL || "https://xtw53.top";
const OUT_DIR = process.env.SEO_OUT_DIR || "dist";
const LANGS = ["en", "zh"];
const LIMIT = Number(process.env.SEO_PAGE_LIMIT || 100);
const SINGLE_VIDEO_ID = process.env.VIDEO_ID
  ? String(process.env.VIDEO_ID)
  : null;

const KEY = "0XxdjmI55ZjjqQLO3nI7gGqrBP0Vz9jS";
const IV = "RWf23muavY";
const SIGN_KEY = "NRkw0g3iJLDvw5tJ5PuVt5276z0SOuyL";
const SUFFIX_HEADER = "NWSdef";

const escapeAttr = (value) =>
  String(value)
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

const slugify = (value) =>
  String(value || "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

const buildVideoSlug = (mash, title) => {
  const mashText = String(mash || "").trim();
  let titleText = String(title || "").trim();
  if (mashText) {
    const mashRegex = new RegExp(`^${mashText}\\s*`, "i");
    titleText = titleText.replace(mashRegex, "");
  }
  return slugify([mashText, titleText].filter(Boolean).join(" "));
};

function durationToISO8601(duration) {
  if (!duration) return "PT0S";
  const parts = String(duration).split(":").map(Number);
  let hours = 0;
  let minutes = 0;
  let seconds = 0;
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

const base64Sign = (data) => {
  const sortedKeys = Object.keys(data).sort();
  let preSign = "";
  for (const key of sortedKeys) {
    preSign += `${key}=${data[key]}&`;
  }
  preSign += SIGN_KEY;
  return CryptoJS.MD5(preSign).toString();
};

const encryptPayload = (payload) => {
  const payloadWithSignature = {
    ...payload,
    encode_sign: base64Sign(payload),
  };
  const newIv = CryptoJS.enc.Utf8.parse(IV + SUFFIX_HEADER);
  const newKey = CryptoJS.enc.Utf8.parse(KEY);
  const encrypted = CryptoJS.AES.encrypt(
    JSON.stringify(payloadWithSignature),
    newKey,
    {
      iv: newIv,
      mode: CryptoJS.mode.CBC,
      padding: CryptoJS.pad.Pkcs7,
      formatter: CryptoJS.format.OpenSSL,
    },
  );
  return { "post-data": encrypted.toString() };
};

const decryptPayload = (data, suffix) => {
  const newIv = CryptoJS.enc.Utf8.parse(IV + suffix);
  const newKey = CryptoJS.enc.Utf8.parse(KEY);
  const decrypted = CryptoJS.AES.decrypt(data, newKey, {
    iv: newIv,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7,
    formatter: CryptoJS.format.OpenSSL,
  });
  return decrypted.toString(CryptoJS.enc.Utf8);
};

const tryDecryptResponse = (data) => {
  if (
    data &&
    typeof data === "object" &&
    typeof data.data === "string" &&
    typeof data.suffix === "string"
  ) {
    try {
      const decrypted = decryptPayload(data.data, data.suffix);
      return JSON.parse(decrypted);
    } catch {
      return null;
    }
  }
  return null;
};

async function apiPost(pathname, body, lang) {
  const payload = {
    timestamp: Date.now(),
    ...body,
  };
  const encrypted = encryptPayload(payload);
  const response = await fetch(`${API_BASE}${pathname}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      suffix: SUFFIX_HEADER,
      lang,
    },
    body: JSON.stringify(encrypted),
  });
  const json = await response.json();
  return tryDecryptResponse(json) || json;
}

function upsertMetaTag(html, attribute, name, content) {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const regex = new RegExp(
    `<meta[^>]*${attribute}="${escapedName}"[^>]*>`,
    "i",
  );
  const tag = `<meta ${attribute}="${name}" content="${escapeAttr(
    content,
  )}" />`;
  if (regex.test(html)) {
    return html.replace(regex, tag);
  }
  return html.replace("</head>", `  ${tag}\n</head>`);
}

function upsertLinkTag(html, rel, href, extraAttrs = "") {
  const escapedRel = rel.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const regex = new RegExp(`<link[^>]*rel="${escapedRel}"[^>]*>`, "i");
  const tag = `<link rel="${rel}" href="${escapeAttr(href)}"${extraAttrs} />`;
  if (regex.test(html)) {
    return html.replace(regex, tag);
  }
  return html.replace("</head>", `  ${tag}\n</head>`);
}

function replaceHreflangLinks(html, links) {
  const cleaned = html.replace(
    /\s*<link[^>]*rel="alternate"[^>]*hreflang="[^"]+"[^>]*>\s*/gi,
    "\n",
  );
  const tags = links
    .map(
      (link) =>
        `  <link rel="alternate" hreflang="${link.lang}" href="${escapeAttr(
          link.href,
        )}" />`,
    )
    .join("\n");
  return cleaned.replace("</head>", `${tags}\n</head>`);
}

function removeMetaTags(html, attribute, name) {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  const regex = new RegExp(
    `\\s*<meta[^>]*${attribute}="${escapedName}"[^>]*>\\s*`,
    "gi",
  );
  return html.replace(regex, "\n");
}

function appendMetaTags(html, attribute, name, values) {
  if (!values || values.length === 0) return html;
  const tags = values
    .map(
      (value) =>
        `  <meta ${attribute}="${name}" content="${escapeAttr(value)}" />`,
    )
    .join("\n");
  return html.replace("</head>", `${tags}\n</head>`);
}

function upsertJsonLd(html, id, data) {
  const regex = new RegExp(`<script[^>]*id="${id}"[^>]*>.*?</script>`, "is");
  const tag = `<script id="${id}" type="application/ld+json">${JSON.stringify(
    data,
  )}</script>`;
  if (regex.test(html)) {
    return html.replace(regex, tag);
  }
  return html.replace("</head>", `  ${tag}\n</head>`);
}

function updateTitle(html, title) {
  return html.replace(
    /<title>.*?<\/title>/i,
    `<title>${escapeAttr(title)}</title>`,
  );
}

function updateHtmlLang(html, lang) {
  if (/<html[^>]*lang="/i.test(html)) {
    return html.replace(/<html[^>]*lang="[^"]*"/i, `<html lang="${lang}"`);
  }
  return html.replace(/<html/i, `<html lang="${lang}"`);
}

function renderWatchHtml(baseHtml, data) {
  let html = baseHtml;
  html = updateHtmlLang(html, data.lang);
  html = updateTitle(html, data.title);
  html = upsertMetaTag(html, "name", "description", data.description);
  html = upsertMetaTag(html, "name", "keywords", data.keywords);
  html = upsertMetaTag(html, "name", "robots", "index, follow");
  html = upsertLinkTag(html, "canonical", data.canonicalUrl);
  html = upsertMetaTag(html, "property", "og:type", "video.other");
  html = upsertMetaTag(html, "property", "og:url", data.canonicalUrl);
  html = upsertMetaTag(html, "property", "og:title", data.title);
  html = upsertMetaTag(html, "property", "og:description", data.description);
  html = upsertMetaTag(html, "property", "og:image", data.ogImage);
  html = upsertMetaTag(html, "property", "og:site_name", "AI JAV");
  html = removeMetaTags(html, "property", "og:video:tag");
  html = appendMetaTags(html, "property", "og:video:tag", data.tagList);
  html = removeMetaTags(html, "property", "video:actor");
  html = appendMetaTags(html, "property", "video:actor", data.actorList);
  html = upsertMetaTag(html, "name", "twitter:card", "summary_large_image");
  html = upsertMetaTag(html, "name", "twitter:url", data.canonicalUrl);
  html = upsertMetaTag(html, "name", "twitter:title", data.title);
  html = upsertMetaTag(html, "name", "twitter:description", data.description);
  html = upsertMetaTag(html, "name", "twitter:image", data.ogImage);
  html = replaceHreflangLinks(html, data.hreflangLinks);
  html = upsertJsonLd(html, "video-structured-data", data.videoSchema);
  html = upsertJsonLd(
    html,
    "breadcrumb-structured-data",
    data.breadcrumbSchema,
  );
  return html;
}

function buildSitemapXml(urls) {
  const now = new Date().toISOString();
  const items = urls
    .map(
      (url) =>
        `  <url>\n    <loc>${escapeAttr(
          `${BASE_URL}${url}`,
        )}</loc>\n    <lastmod>${now}</lastmod>\n  </url>`,
    )
    .join("\n");
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${items}\n</urlset>\n`;
}

async function fetchVideoList(lang) {
  let page = 1;
  let lastPage = 1;
  const items = [];

  while (page <= lastPage) {
    const response = await apiPost("video/lists", { page, limit: LIMIT }, lang);
    if (!response || response.code !== 1) {
      throw new Error(
        `Failed to fetch video list (${lang}) page ${page}: ${response?.msg || "unknown"}`,
      );
    }
    const payload = response.data;
    items.push(...(payload?.data || []));
    lastPage = payload?.last_page || page;
    page += 1;
  }

  return items;
}

async function fetchVideoInfo(id, lang) {
  const response = await apiPost("video/info", { vid: Number(id) }, lang);
  if (!response || response.code !== 1) {
    console.warn(
      `Skipping video info (${lang}) id ${id}: ${response?.msg || "unknown"}`,
    );
    return null;
  }
  return response.data;
}

async function main() {
  const baseHtmlPath = path.join(OUT_DIR, "index.html");
  const baseHtml = await fs.readFile(baseHtmlPath, "utf8");

  const sitemapUrls = [];
  const targetsByLang = new Map();
  const videoInfoById = new Map();

  for (const lang of LANGS) {
    if (SINGLE_VIDEO_ID) {
      targetsByLang.set(lang, [SINGLE_VIDEO_ID]);
      continue;
    }
    const list = await fetchVideoList(lang);
    targetsByLang.set(
      lang,
      list.map((item) => String(item.id)),
    );
  }

  for (const lang of LANGS) {
    const ids = targetsByLang.get(lang) || [];
    for (const id of ids) {
      const video = await fetchVideoInfo(id, lang);
      if (!video) continue;
      const slug = buildVideoSlug(
        video?.mash,
        video?.mash_title || video?.title,
      );
      if (!slug) continue;
      if (!videoInfoById.has(video.id)) {
        videoInfoById.set(video.id, {});
      }
      videoInfoById.get(video.id)[lang] = { video, slug };
    }
  }

  for (const [id, byLang] of videoInfoById.entries()) {
    for (const lang of LANGS) {
      const entry = byLang[lang];
      if (!entry) continue;
      const { video, slug } = entry;
      const urlPath = `/${lang}/watch/${video.id}/${slug}/`;
      const canonicalUrl = `${BASE_URL}${urlPath}`;

      const hreflangLinks = Object.entries(byLang).map(
        ([altLang, altEntry]) => ({
          lang: altLang,
          href: `${BASE_URL}/${altLang}/watch/${video.id}/${altEntry.slug}/`,
        }),
      );

      const videoSchema = {
        "@context": "https://schema.org",
        "@type": "VideoObject",
        name: video?.mash_title || video?.title || "Video",
        description: video?.description || "",
        thumbnailUrl: video?.thumb,
        uploadDate: video?.publish_date,
        url: canonicalUrl,
      };

      if (video?.duration) {
        videoSchema.duration = durationToISO8601(video.duration);
      }

      const actorNames = Array.isArray(video?.actor)
        ? video.actor.map((actor) => actor?.name).filter(Boolean)
        : [];
      if (actorNames.length > 0) {
        videoSchema.actor = actorNames.map((name) => ({
          "@type": "Person",
          name,
        }));
      }

      if (video?.publisher?.name) {
        videoSchema.publisher = {
          "@type": "Organization",
          name: video.publisher.name,
        };
      }

      if (video?.rating_count > 0 && video?.rating_avg > 0) {
        videoSchema.aggregateRating = {
          "@type": "AggregateRating",
          ratingValue: video.rating_avg,
          ratingCount: video.rating_count,
          bestRating: 5,
          worstRating: 1,
        };
      }

      if (video?.play > 0) {
        videoSchema.interactionStatistic = {
          "@type": "InteractionCounter",
          interactionType: { "@type": "WatchAction" },
          userInteractionCount: video.play,
        };
      }

      const tagList = Array.isArray(video?.tags)
        ? video.tags.map((tag) => tag?.name).filter(Boolean)
        : [];
      if (tagList.length > 0) {
        videoSchema.keywords = tagList.join(", ");
      }

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
            name: video?.mash_title || video?.title || "视频",
            item: canonicalUrl,
          },
        ],
      };

      const html = renderWatchHtml(baseHtml, {
        lang,
        title: `${video?.mash_title || video?.title || "视频播放"} - AI JAV`,
        description:
          video?.description || `${video?.mash_title || ""} - 在线观看高清视频`,
        keywords: tagList.join(", "),
        ogImage: video?.thumb || `${BASE_URL}/logo-only.svg`,
        canonicalUrl,
        hreflangLinks,
        videoSchema,
        breadcrumbSchema,
        tagList,
        actorList: actorNames,
      });

      const outPath = path.join(
        OUT_DIR,
        lang,
        "watch",
        String(video.id),
        slug,
        "index.html",
      );
      await fs.mkdir(path.dirname(outPath), { recursive: true });
      await fs.writeFile(outPath, html, "utf8");
      sitemapUrls.push(urlPath);
    }
  }

  const sitemap = buildSitemapXml(sitemapUrls);
  await fs.writeFile(path.join(OUT_DIR, "sitemap.xml"), sitemap, "utf8");
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
