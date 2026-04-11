# SEO Improvement Plan for InsAV

## Table of Contents
1. [Reference Site Analysis (missav.ws)](#1-reference-site-analysis)
2. [Current State Audit](#2-current-state-audit)
3. [Critical Issues & Gaps](#3-critical-issues--gaps)
4. [Implementation Plan](#4-implementation-plan)
5. [Backend Changes Required](#5-backend-changes-required)
6. [File-by-File Change List](#6-file-by-file-change-list)
7. [Priority & Effort Matrix](#7-priority--effort-matrix)

---

## 1. Reference Site Analysis

### Real-World Research: Competitor SEO Implementations

We analyzed the following live sites to understand their SEO implementations:

| Site | Type | Accessible | Key Findings |
|------|------|-----------|--------------|
| **Pornhub** | Largest adult tube | ✅ Full HTML | Best-in-class hreflang (17 languages), WebSite + Organization JSON-LD, SearchAction, canonical URLs |
| **XVideos** | #2 adult tube | ✅ Full HTML | VideoObject JSON-LD on every video, full hreflang with x-default, og:duration, AMP pages |
| **JavDB** | JAV database | ✅ Full HTML | SSR (Rails), preconnect to CDN, OpenSearch, good meta tags |
| **Jable.tv** | JAV streaming | ✅ Full HTML | OG tags per video, keyword-rich meta, mash code in title |
| **missav.ws** | JAV streaming | ❌ Cloudflare | Blocked (but known for excellent SSR SEO) |
| **SpankBang** | Adult tube | ❌ Cloudflare | Blocked |
| **SupJav** | JAV aggregator | ❌ Cloudflare | Blocked |

---

#### Pornhub: Detailed Findings

**Homepage JSON-LD** (actual data extracted):
```json
{
  "@context": "http://schema.org",
  "@type": "WebSite",
  "url": "https://www.pornhub.org/",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://www.pornhub.org/video/search?search={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
```
```json
{
  "@context": "http://schema.org",
  "@type": "Organization",
  "name": "Pornhub",
  "url": "https://www.pornhub.org/",
  "logo": "https://ki.phncdn.com/www-static/images/pornhub_logo_straight.png",
  "sameAs": [
    "https://twitter.com/pornhub",
    "https://www.instagram.com/pornhub",
    "https://www.youtube.com/channel/UCYsYJ6od6t1lWnQg-A0n6Yw"
  ]
}
```

**hreflang Strategy** (17 language variants using **subdomain** approach):
```html
<link rel="alternate" hreflang="x-default" href="https://www.pornhub.com/...">
<link rel="alternate" hreflang="en" href="https://www.pornhub.com/...">
<link rel="alternate" hreflang="de" href="https://de.pornhub.com/...">
<link rel="alternate" hreflang="fr" href="https://fr.pornhub.com/...">
<link rel="alternate" hreflang="ja" href="https://jp.pornhub.com/...">
<link rel="alternate" hreflang="zh" href="https://cn.pornhub.com/...">
<link rel="alternate" hreflang="ru" href="https://rt.pornhub.com/...">
<!-- + de-DE, ru-RU, ru-BY, fil-PH regional variants -->
```

**Key Patterns**:
- `<html lang="en">` properly set
- Canonical URL on every page: `<link rel="canonical" href="...">`
- Pornstar pages have unique title + description (e.g., "Mia Khalifa: Sexy Lebanese Porn Star Videos | Pornhub")
- Search pages have canonical with query params preserved
- `dns-prefetch` for CDN domains
- RSS feed alternate link
- `meta name="rating" content="RTA-5042-1996-1400-1577-RTA"` (age verification standard)

---

#### XVideos: Detailed Findings

**Video Page JSON-LD** (actual data extracted):
```json
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "Video Title Here",
  "description": "Video Title Here",
  "thumbnailUrl": ["https://thumb-cdn77.xvideos-cdn.com/.../xv_30_t.jpg"],
  "uploadDate": "2026-03-11T03:59:06+00:00",
  "duration": "PT00H12M07S",
  "contentUrl": "https://mp4-gcore.xvideos-cdn.com/.../mp4_sd.mp4?secure=...",
  "interactionStatistic": {
    "@type": "InteractionCounter",
    "interactionType": { "@type": "WatchAction" },
    "userInteractionCount": 11
  }
}
```

**Video Page OG Tags** (actual data):
```html
<meta property="og:title" content="Video Title">
<meta property="og:type" content="video.movie">
<meta property="og:url" content="https://www.xvideos.com/video.xxx/slug">
<meta property="og:duration" content="727">  <!-- seconds! -->
<meta property="og:image" content="https://thumb-cdn77.xvideos-cdn.com/.../xv_30_t.jpg">
```

**Key Patterns**:
- `og:type` = `video.movie` (not `video.other`)
- `og:duration` in seconds (not ISO 8601)
- `contentUrl` in JSON-LD points to actual MP4 file
- `thumbnailUrl` is an array (multiple thumbnails)
- Subdomain strategy for languages (fr.xvideos.com, de.xvideos.com)
- Dedicated domains for some regions (xvideos.es, xvideos-india.com, xv-ru.com)
- AMP page: `<link rel="amphtml" href="https://amp.xvideos.com/">`
- `manifest.json` for PWA support
- Channel/pornstar pages have full hreflang with `x-default`

---

#### Jable.tv: Detailed Findings (JAV-Specific)

**Video Page Meta Tags** (actual data):
```html
<title>IPZZ-462 [Chinese title with mash code] - Jable.TV | 免費高清AV在線看</title>
<meta name="description" content="此作品曾在本站上傳，現已更新至中文字幕版。">
<meta name="keywords" content="中文字幕, 角色劇情, 制服誘惑, 少女, 下雨天, 童貞, OL, 口爆, 出軌, 中出, 北岡果林">
<meta property="og:title" content="IPZZ-462 [title] 北岡果林">
<meta property="og:image" content="https://assets-cdn.jable.tv/.../preview.jpg">
<meta property="og:description" content="此作品曾在本站上傳，現已更新至中文字幕版。">
```

**Key Patterns**:
- **Mash code (番号) in title** — Critical for JAV SEO (users search by code)
- **Actress name in title** — "IPZZ-462 ... 北岡果林"
- **Keyword-rich meta keywords** — tags, categories, actress names all included
- No hreflang (single language site)
- No JSON-LD structured data found (missed opportunity)
- Site name appended to title with separator

---

#### JavDB: Detailed Findings

```html
<title>JavDB, Online information source for adult movies</title>
<meta name="description" content="An magnet links sharing website for adult movies...">
<meta name="keywords" content="JavDB,Magnet Links Sharing,Adult Video Magnet Links,Japanese Adult Movies,JAV ID Search,Censored,Uncensored">
<link rel="preconnect" href="https://c0.jdbstatic.com/">
<link rel="search" type="application/opensearchdescription+xml" title="searchTitle" href="/opensearch.xml">
<link rel="manifest" href="/manifest.json">
```

**Key Patterns**:
- Server-side rendered (Ruby on Rails)
- `preconnect` to CDN domain
- OpenSearch integration (browser search bar)
- PWA manifest
- CSRF protection (server-rendered app)
- `data-lang` and `data-domain` attributes on body for JS language handling

---

### Summary: What the Top Sites Do vs What We Do

| Feature | Pornhub | XVideos | Jable.tv | JavDB | **Our Site** |
|---------|---------|---------|----------|-------|-------------|
| SSR/Full HTML | ✅ | ✅ | ✅ | ✅ | ⚠️ Partial (100 videos) |
| hreflang + x-default | ✅ 17 langs | ✅ 10 langs | ❌ | ❌ | ⚠️ 2 langs, no x-default |
| VideoObject JSON-LD | ❌ Homepage only | ✅ Every video | ❌ | ❌ | ✅ SSG videos only |
| WebSite JSON-LD | ✅ | ❌ | ❌ | ❌ | ❌ |
| Organization JSON-LD | ✅ | ❌ | ❌ | ❌ | ❌ |
| SearchAction | ✅ | ❌ | ❌ | ❌ | ❌ |
| Canonical URLs | ✅ | ✅ | ❌ | ❌ | ✅ Videos only |
| OG tags (video pages) | ✅ | ✅ + og:duration | ✅ | ❌ | ✅ |
| Mash code in title | N/A | N/A | ✅ | ✅ | ✅ |
| Actress in title | N/A | N/A | ✅ | ✅ | ❌ |
| dns-prefetch/preconnect | ✅ | ✅ | ❌ | ✅ | ❌ |
| AMP pages | ❌ | ✅ | ❌ | ❌ | ❌ |
| PWA manifest | ❌ | ✅ | ❌ | ✅ | ❌ |
| RTA rating meta | ✅ | ✅ | ❌ | ❌ | ❌ |
| OpenSearch | ❌ | ❌ | ❌ | ✅ | ❌ |

---

### What Well-Optimized Video Streaming Sites Do (Synthesized Best Practices)

Based on our analysis, sites like these rank well because they implement the following SEO patterns:

#### 1.1 Server-Side Rendered HTML
- **Every page** returns fully-rendered HTML with all meta tags, structured data, and content visible to crawlers **before** JavaScript executes
- Social media scrapers (Facebook, Twitter, Telegram) and search engine bots get a complete page on the first request
- This is the **single biggest differentiator** — a CSR SPA with client-side meta tags is nearly invisible to crawlers

#### 1.2 Multi-Language URL Architecture
```
/en/          → English homepage
/zh/          → Chinese homepage
/ja/          → Japanese homepage
/en/dm190/th  → English video page with SEO slug
/zh/dm190/th  → Chinese video page with SEO slug
```
- Every language version has its own URL path (subdirectory strategy)
- `hreflang` tags on every page pointing to all language alternates + `x-default`
- `<html lang="xx">` attribute matches the page language

#### 1.3 Comprehensive Meta Tags per Page
```html
<title>Video Title | Category - Site Name</title>
<meta name="description" content="Unique 150-160 char description with keywords...">
<meta name="keywords" content="tag1, tag2, actor-name, publisher-name">
<link rel="canonical" href="https://domain.com/en/video-slug/">

<!-- Open Graph -->
<meta property="og:type" content="video.other">
<meta property="og:title" content="Video Title">
<meta property="og:description" content="Unique description...">
<meta property="og:image" content="https://cdn.example.com/thumb.jpg">
<meta property="og:image:width" content="1280">
<meta property="og:image:height" content="720">
<meta property="og:url" content="https://domain.com/en/video-slug/">
<meta property="og:site_name" content="Site Name">
<meta property="og:locale" content="en_US">
<meta property="og:locale:alternate" content="zh_CN">
<meta property="og:video:tag" content="tag1">
<meta property="video:actor" content="Actor Name">
<meta property="video:director" content="Director Name">
<meta property="video:duration" content="5400"> <!-- seconds -->

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Video Title">
<meta name="twitter:description" content="Unique description...">
<meta name="twitter:image" content="https://cdn.example.com/thumb.jpg">

<!-- hreflang -->
<link rel="alternate" hreflang="en" href="https://domain.com/en/video-slug/">
<link rel="alternate" hreflang="zh" href="https://domain.com/zh/video-slug/">
<link rel="alternate" hreflang="x-default" href="https://domain.com/en/video-slug/">
```

#### 1.4 Rich JSON-LD Structured Data

**VideoObject** on every video page:
```json
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "Video Title",
  "description": "Video description...",
  "thumbnailUrl": "https://cdn.example.com/thumb.jpg",
  "uploadDate": "2025-01-15T00:00:00+08:00",
  "duration": "PT1H30M",
  "contentUrl": "https://cdn.example.com/video.m3u8",
  "embedUrl": "https://domain.com/embed/12345",
  "interactionStatistic": {
    "@type": "InteractionCounter",
    "interactionType": {"@type": "WatchAction"},
    "userInteractionCount": 150000
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": 4.5,
    "ratingCount": 1200,
    "bestRating": 5,
    "worstRating": 1
  },
  "actor": [{"@type": "Person", "name": "Actor Name", "url": "https://domain.com/actress/123"}],
  "publisher": {"@type": "Organization", "name": "Publisher Name"},
  "keywords": "tag1, tag2, tag3"
}
```

**BreadcrumbList** on every page (localized):
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://domain.com/en/"},
    {"@type": "ListItem", "position": 2, "name": "Category Name", "item": "https://domain.com/en/category/5"},
    {"@type": "ListItem", "position": 3, "name": "Video Title"}
  ]
}
```

**Person** schema on actor/actress pages:
```json
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Actress Name",
  "image": "https://cdn.example.com/actress.jpg",
  "url": "https://domain.com/en/actress/123",
  "jobTitle": "Actress",
  "knowsAbout": ["tag1", "tag2"]
}
```

**Organization** schema on publisher pages:
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Publisher Name",
  "logo": "https://cdn.example.com/publisher-logo.jpg",
  "url": "https://domain.com/en/publisher/456"
}
```

**WebSite** schema on homepage (enables sitelinks search box):
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "Site Name",
  "url": "https://domain.com/",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://domain.com/search?keyword={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
```

#### 1.5 Semantic HTML Structure
```html
<header><!-- site nav, logo --></header>
<main>
  <article>
    <h1>Video Title - Code</h1>
    <section class="video-player">...</section>
    <section class="video-info">
      <h2>Video Details</h2>
      <p>Description text...</p>
    </section>
    <section class="related-videos">
      <h2>Related Videos</h2>
    </section>
  </article>
</main>
<footer><!-- links, copyright --></footer>
```

#### 1.6 Comprehensive Sitemap Strategy
```xml
<!-- sitemap-index.xml -->
<sitemapindex>
  <sitemap><loc>https://domain.com/sitemap-videos-1.xml</loc></sitemap>
  <sitemap><loc>https://domain.com/sitemap-videos-2.xml</loc></sitemap>
  <sitemap><loc>https://domain.com/sitemap-actresses.xml</loc></sitemap>
  <sitemap><loc>https://domain.com/sitemap-publishers.xml</loc></sitemap>
  <sitemap><loc>https://domain.com/sitemap-categories.xml</loc></sitemap>
  <sitemap><loc>https://domain.com/sitemap-pages.xml</loc></sitemap>
</sitemapindex>
```
- Sitemap index with separate sitemaps per content type
- Video sitemap with `<video:video>` extension tags
- Each sitemap limited to 50,000 URLs
- All language variants included

#### 1.7 Performance & Technical SEO
- `dns-prefetch` and `preconnect` to CDN domains
- Lazy loading for images below the fold
- Proper image `alt` attributes describing content
- Fast TTFB (Time to First Byte) from SSR/pre-rendering
- Compressed responses (gzip/brotli)
- HTTP/2 or HTTP/3 enabled

---

## 2. Current State Audit

### What We Have (Working)
| Feature | Status | Notes |
|---------|--------|-------|
| Basic meta tags in `index.html` | ✅ | Title, description, OG, Twitter |
| `useSEO` hook (client-side) | ✅ | Sets meta tags after JS loads |
| `useVideoSEO` hook (client-side) | ✅ | VideoObject JSON-LD + breadcrumbs |
| SSG for video pages (`generate-seo.mjs`) | ✅ | Pre-renders ~100 video pages |
| `robots.txt` | ✅ | Properly configured |
| Canonical URLs (video pages) | ✅ | `/{lang}/watch/{id}/{slug}/` |
| SEO-friendly URL slugs | ✅ | Mash code + title slugified |
| hreflang (SSG video pages only) | ✅ | en, zh alternates |
| Sitemap generation | ⚠️ | Only video pages, very limited |

### What's Missing (Critical Gaps)
| Feature | Status | Impact |
|---------|--------|--------|
| Server-side rendering for ALL pages | ❌ | **Critical** — crawlers see empty `<div id="root">` |
| SEO on actress pages | ❌ | No meta tags, no structured data |
| SEO on publisher pages | ❌ | No meta tags, no structured data |
| SEO on category pages | ❌ | No meta tags, no structured data |
| SEO on homepage | ❌ | Generic static tags only |
| hreflang on non-video pages | ❌ | No language alternates |
| Russian language in SEO | ❌ | i18n supports `ru`, SEO doesn't |
| `x-default` hreflang | ❌ | Missing fallback language |
| Sitemap for non-video content | ❌ | Actresses, publishers, categories not in sitemap |
| Sitemap index strategy | ❌ | Single flat sitemap |
| Video sitemap with `<video:video>` | ❌ | Standard sitemap only |
| `WebSite` schema (homepage) | ❌ | No sitelinks search box |
| `Person` schema (actress pages) | ❌ | Missing structured data |
| `Organization` schema (publisher pages) | ❌ | Missing structured data |
| `ItemList` schema (list pages) | ❌ | Missing structured data |
| Semantic HTML (`<main>`, `<article>`) | ❌ | Using generic `<div>` everywhere |
| `og:locale` dynamic per language | ❌ | Hardcoded `zh_CN` |
| `og:image:width/height` | ❌ | Missing image dimensions |
| Breadcrumb localization | ❌ | Hardcoded "首页" even on English pages |
| `dns-prefetch` to API/CDN | ❌ | Only preconnect to Google Fonts |
| Image `alt` tags with descriptions | ⚠️ | Partial |
| Internal linking optimization | ⚠️ | Could be improved |
| `<link rel="preload">` for critical resources | ❌ | Not implemented |

---

## 3. Critical Issues & Gaps

### Issue #1: Client-Side Rendering = Invisible to Most Crawlers (CRITICAL)

**Problem**: The app is a React SPA. When a crawler (Google, Bing, social media) fetches any page other than the ~100 pre-generated video pages, it sees:
```html
<title>AI JAV - 高清日本成人视频在线观看</title>
<meta name="description" content="AI JAV 提供高清日本成人视频在线观看...">
<div id="root"></div>
```
Every page has the **same generic title and description**. The actual content only appears after JavaScript executes, which most crawlers either don't do or do poorly.

**Impact**:
- Google may index pages but with wrong titles/descriptions
- Social media shares always show the generic homepage info
- Bing, Yandex, and other crawlers see zero content
- Telegram/Discord/WhatsApp link previews are broken for non-SSG pages

**Solution**: Expand the SSG (Static Site Generation) script to pre-render ALL indexable pages, not just videos.

### Issue #2: Only ~100 Video Pages Pre-Rendered

**Problem**: `generate-seo.mjs` only fetches 1 page of the video list (100 items) and generates HTML for those. Any video not in the latest 100 gets the generic SPA treatment.

**Impact**: The vast majority of content is invisible to search engines.

**Solution**: Generate pages for ALL videos, actresses, publishers, and categories.

### Issue #3: Actress & Publisher Pages Have Zero SEO

**Problem**: `ActressInfo.tsx` and `PublisherInfo.tsx` don't call `useSEO()` at all. Even the client-side meta tag updates are missing.

**Impact**: These pages always show the default homepage title/description in search results and social shares.

**Solution**:
1. Add `useSEO()` calls to these components (immediate fix)
2. Add SSG generation for these pages (proper fix)

### Issue #4: Missing `x-default` hreflang

**Problem**: hreflang links exist for `en` and `zh` on SSG video pages, but there's no `x-default` for users whose language doesn't match either.

**Impact**: Google doesn't know which version to show users in other languages.

**Solution**: Add `<link rel="alternate" hreflang="x-default" href="...">` pointing to the English version.

### Issue #5: Sitemap is Inadequate

**Problem**: The sitemap contains only 2 URLs (1 video x 2 languages, or up to 200 for 100 videos). Missing: homepage, all category pages, all actress pages, all publisher pages.

**Impact**: Google discovers content only through crawling links, which is slow and incomplete.

**Solution**: Generate a comprehensive sitemap index with separate sitemaps per content type.

---

## 4. Implementation Plan

### Phase 1: Quick Wins (Client-Side Fixes) — 1-2 days

These are changes that improve SEO even without SSR/SSG, since Googlebot does execute JavaScript.

#### 1.1 Add `useSEO` to ALL Page Components

**Files to modify**:
- `src/pages/Home.tsx` — Add WebSite schema + homepage SEO
- `src/pages/ActressInfo.tsx` — Add Person schema + actress SEO
- `src/pages/PublisherInfo.tsx` — Add Organization schema + publisher SEO
- `src/pages/CategoryVideos.tsx` — Add category SEO
- `src/pages/LatestVideos.tsx` — Add list page SEO
- `src/pages/FreeVideos.tsx` — Add list page SEO
- `src/pages/SearchResults.tsx` — Add `noIndex` (search results shouldn't be indexed)
- `src/pages/AllActresses.tsx` — Add listing SEO
- `src/pages/AllPublishers.tsx` — Add listing SEO

**Example for ActressInfo.tsx**:
```typescript
useSEO({
  title: actress?.name,
  description: `Watch ${actress?.name} videos online. ${actress?.video_count} videos available.`,
  keywords: actress?.name,
  canonicalUrl: `${BASE_URL}/actress/${actress?.id}`,
  ogImage: actress?.avatar,
  ogUrl: `${BASE_URL}/actress/${actress?.id}`,
});
```

#### 1.2 Fix `og:locale` to Be Dynamic

**File**: `src/hooks/useSEO.ts`

Add `og:locale` based on current language:
```typescript
const localeMap = { zh: "zh_CN", en: "en_US", ru: "ru_RU" };
setMetaTag("og:locale", localeMap[currentLang] || "zh_CN", "property");
```

#### 1.3 Add hreflang Links Client-Side

**File**: `src/hooks/useSEO.ts` or new `src/hooks/useHreflang.ts`

Dynamically inject hreflang `<link>` tags based on the current route:
```typescript
function useHreflang(path: string) {
  useEffect(() => {
    const langs = ["en", "zh"];
    const links: HTMLLinkElement[] = [];

    langs.forEach(lang => {
      const link = document.createElement("link");
      link.rel = "alternate";
      link.hreflang = lang;
      link.href = `${BASE_URL}/${lang}${path}`;
      document.head.appendChild(link);
      links.push(link);
    });

    // x-default
    const xDefault = document.createElement("link");
    xDefault.rel = "alternate";
    xDefault.hreflang = "x-default";
    xDefault.href = `${BASE_URL}/en${path}`;
    document.head.appendChild(xDefault);
    links.push(xDefault);

    return () => links.forEach(l => l.remove());
  }, [path]);
}
```

#### 1.4 Localize Breadcrumb Schema

**File**: `src/hooks/useSEO.ts`

Replace hardcoded "首页" with i18n-aware translation:
```typescript
const breadcrumbHomeNames = { en: "Home", zh: "首页", ru: "Главная" };
```

#### 1.5 Add Semantic HTML

**Files**: Layout and page components

Replace generic `<div>` wrappers with semantic elements:
- `<main>` for primary content area
- `<article>` for video detail, actress detail, publisher detail
- `<section>` for distinct content sections (related videos, comments)
- `<nav>` for navigation elements (already likely in sidebar)
- `<header>` and `<footer>` for page header/footer

#### 1.6 Add `dns-prefetch` and `preconnect`

**File**: `index.html`

```html
<link rel="dns-prefetch" href="https://newavapi.9xyrp3kg4b86.com">
<link rel="preconnect" href="https://newavapi.9xyrp3kg4b86.com">
<!-- Add CDN domains for video thumbnails/content if applicable -->
```

---

### Phase 2: Expand SSG Script — 3-5 days

This is the **highest impact** change. Expand `generate-seo.mjs` to pre-render all indexable pages.

#### 2.1 Generate ALL Video Pages (Not Just Latest 100)

**File**: `scripts/generate-seo.mjs`

Changes:
- Paginate through the full video list API (all pages, not just page 1)
- Remove the `LIMIT` cap or make it much higher
- Add rate limiting to avoid overwhelming the API
- Process in batches for memory efficiency

```javascript
// Instead of: { page: 1, limit: 100 }
// Paginate: { page: 1..N, limit: 100 } until last_page
```

#### 2.2 Generate Actress Pages

**New function in `generate-seo.mjs`**:

```javascript
async function generateActressPages(baseHtml) {
  const actresses = await apiPost("actor/lists", { page: 1, limit: 1000 }, "en");

  for (const actress of actresses.data.data) {
    for (const lang of LANGS) {
      const actressInfo = await apiPost("actor/info", { id: actress.id }, lang);
      // Generate HTML with:
      // - Title: "Actress Name - Videos | AI JAV"
      // - Description: "Watch Actress Name videos. X videos available."
      // - JSON-LD Person schema
      // - hreflang links
      // - BreadcrumbList: Home > Actresses > Actress Name
    }
  }
}
```

Required API: `actor/lists` (paginated), `actor/info` (single actress details)

#### 2.3 Generate Publisher Pages

Similar to actress pages but with Organization schema.

Required API: `publisher/lists` (paginated)

#### 2.4 Generate Category Pages

Required API: `category/lists` or equivalent

#### 2.5 Generate Homepage Variants

```javascript
// Generate /en/index.html and /zh/index.html
// With WebSite schema, SearchAction, localized meta tags
```

#### 2.6 Generate Comprehensive Sitemap Index

```javascript
function buildSitemapIndex(sitemaps) {
  return `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${sitemaps.map(s => `  <sitemap>
    <loc>${BASE_URL}/${s.file}</loc>
    <lastmod>${s.lastmod}</lastmod>
  </sitemap>`).join('\n')}
</sitemapindex>`;
}
```

Generate:
- `sitemap-index.xml` (master index)
- `sitemap-videos-1.xml`, `sitemap-videos-2.xml`, ... (up to 50k URLs each)
- `sitemap-actresses.xml`
- `sitemap-publishers.xml`
- `sitemap-categories.xml`
- `sitemap-pages.xml` (homepage, static pages)

#### 2.7 Add Video Sitemap Extension

```xml
<url>
  <loc>https://xtw53.top/en/watch/123/video-slug/</loc>
  <video:video>
    <video:thumbnail_loc>https://cdn.example.com/thumb.jpg</video:thumbnail_loc>
    <video:title>Video Title</video:title>
    <video:description>Video description...</video:description>
    <video:duration>5400</video:duration>
    <video:publication_date>2025-01-15T00:00:00+08:00</video:publication_date>
    <video:tag>tag1</video:tag>
    <video:tag>tag2</video:tag>
  </video:video>
</url>
```

---

### Phase 3: Add Russian Language SEO Support — 1-2 days

#### 3.1 Extend LANGS Array
**File**: `scripts/generate-seo.mjs`
```javascript
const LANGS = ["en", "zh", "ru"];
```

#### 3.2 Add Russian to URL Routing
**File**: `src/lib/watch-url.ts`
```typescript
const SUPPORTED_LANGS = ["en", "zh", "ru"];
```

#### 3.3 Add Russian hreflang Links
All generated pages should include:
```html
<link rel="alternate" hreflang="en" href="...">
<link rel="alternate" hreflang="zh" href="...">
<link rel="alternate" hreflang="ru" href="...">
<link rel="alternate" hreflang="x-default" href="..."> <!-- English -->
```

#### 3.4 Localize Meta Tags per Language
Translate default descriptions and breadcrumb labels:
```javascript
const DEFAULTS = {
  en: { description: "Watch HD Japanese videos online...", home: "Home" },
  zh: { description: "AI JAV 提供高清日本成人视频在线观看...", home: "首页" },
  ru: { description: "Смотрите японские видео в HD онлайн...", home: "Главная" },
};
```

---

### Phase 4: Advanced Optimizations — Ongoing

#### 4.1 Consider Prerender Service / Dynamic Rendering
If full SSG for all pages is too complex, consider:
- **Prerender.io** or similar service that serves pre-rendered HTML to bots
- **Dynamic rendering**: detect crawler user agents at the CDN/server level and serve pre-rendered pages
- This is a simpler alternative to full SSR

#### 4.2 Add `ItemList` Schema for List Pages
On category, actress list, and publisher list pages:
```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "url": "https://domain.com/en/watch/123/video-slug/"},
    {"@type": "ListItem", "position": 2, "url": "https://domain.com/en/watch/456/video-slug/"}
  ]
}
```

#### 4.3 Add `SearchAction` Schema
On homepage for Google sitelinks search box:
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "url": "https://xtw53.top/",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://xtw53.top/search?keyword={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
```

#### 4.4 Internal Linking Strategy
- Each video page should link to its actors, publisher, and tags
- Actor pages should link to all their videos
- Category pages should link to sub-categories and videos
- Footer should contain links to main categories, popular actresses
- This creates a strong internal link graph that helps crawlers discover all content

#### 4.5 Image SEO
- Add descriptive `alt` attributes to all images
- Use `<img>` instead of CSS background images for content images
- Consider adding `srcset` for responsive images
- Ensure thumbnail images are properly sized (not too large)

---

## 5. Backend Changes Required

### 5.1 API Endpoints Needed for SSG

The SSG script needs to fetch all content to pre-render pages. Ensure these endpoints exist and return paginated data:

| Endpoint | Purpose | Required Response |
|----------|---------|-------------------|
| `video/lists` | All videos (paginated) | `{ data: { data: [...], last_page: N } }` |
| `video/info` | Single video detail | Full video object with actors, publisher, tags |
| `actor/lists` | All actresses (paginated) | `{ data: { data: [...], last_page: N } }` |
| `actor/info` | Single actress detail | Name, avatar, video count, bio |
| `publisher/lists` | All publishers | `{ data: { data: [...] } }` |
| `category/lists` | All categories | `{ data: { data: [...] } }` |

**Important**: These endpoints should work **without authentication** (or with a special build-time token) since the SSG script runs at build time.

### 5.2 New API Fields (Nice-to-Have)

Consider adding these fields to improve SEO:

| Field | Endpoint | Purpose |
|-------|----------|---------|
| `seo_description` | `video/info` | Human-written SEO description per video |
| `actress.bio` | `actor/info` | Actress biography for meta description |
| `publisher.description` | `publisher/info` | Publisher description |
| `video.content_url` | `video/info` | Direct video URL for VideoObject schema `contentUrl` |
| `video.embed_url` | `video/info` | Embed URL for VideoObject schema `embedUrl` |

### 5.3 API Performance Considerations

The SSG script will make many API calls at build time. Consider:
- Rate limiting: Add delays between requests (e.g., 100ms)
- Batch endpoints: Create a bulk video info endpoint that accepts multiple IDs
- Caching: Cache API responses during build to avoid redundant calls
- Incremental builds: Only regenerate pages for content that changed since last build

### 5.4 Server/CDN Configuration

For the pre-rendered pages to work, the server/CDN must:

1. **Serve `/{lang}/watch/{id}/{slug}/index.html`** when the URL `/{lang}/watch/{id}/{slug}/` is requested
2. **Serve `/{lang}/actress/{id}/index.html`** for actress pages (new)
3. **Serve `/{lang}/publisher/{id}/index.html`** for publisher pages (new)
4. **Fall back to `index.html`** for non-pre-rendered routes (SPA fallback)
5. **Serve correct `Content-Type`** headers
6. **Enable gzip/brotli** compression
7. **Set appropriate cache headers** for pre-rendered pages

---

## 6. File-by-File Change List

### Modified Files

| File | Changes |
|------|---------|
| `index.html` | Add `dns-prefetch`/`preconnect` for API domain, add `og:image:width/height` |
| `src/hooks/useSEO.ts` | Add dynamic `og:locale`, add hreflang support, localize breadcrumbs, add `useActressSEO`, `usePublisherSEO`, `useHomeSEO` hooks |
| `src/pages/Home.tsx` | Call `useSEO` with homepage config, add WebSite + SearchAction JSON-LD |
| `src/pages/ActressInfo.tsx` | Call `useSEO` with actress info, add Person JSON-LD |
| `src/pages/PublisherInfo.tsx` | Call `useSEO` with publisher info, add Organization JSON-LD |
| `src/pages/CategoryVideos.tsx` | Call `useSEO` with category info |
| `src/pages/LatestVideos.tsx` | Call `useSEO` with page title |
| `src/pages/FreeVideos.tsx` | Call `useSEO` with page title |
| `src/pages/SearchResults.tsx` | Call `useSEO` with `noIndex: true` |
| `src/pages/AllActresses.tsx` | Call `useSEO` with listing title |
| `src/pages/AllPublishers.tsx` | Call `useSEO` with listing title |
| `src/pages/VideoPlayer.tsx` | Add `og:image:width/height`, add `x-default` hreflang |
| `src/lib/watch-url.ts` | Add `ru` to `SUPPORTED_LANGS` |
| `scripts/generate-seo.mjs` | Major expansion (see Phase 2) |
| `public/robots.txt` | Update sitemap URL to sitemap-index.xml, add language-prefixed Allow rules |

### New Files

| File | Purpose |
|------|---------|
| `src/hooks/useHreflang.ts` | Reusable hook for injecting hreflang links |
| `scripts/generate-actress-pages.mjs` | SSG for actress pages (or merged into generate-seo.mjs) |
| `scripts/generate-publisher-pages.mjs` | SSG for publisher pages (or merged into generate-seo.mjs) |
| `dist/sitemap-index.xml` | Master sitemap index (generated) |
| `dist/sitemap-videos-*.xml` | Video sitemaps (generated) |
| `dist/sitemap-actresses.xml` | Actress sitemap (generated) |
| `dist/sitemap-publishers.xml` | Publisher sitemap (generated) |

---

## 7. Priority & Effort Matrix

| Priority | Task | Effort | Impact | Dependencies |
|----------|------|--------|--------|-------------|
| **P0** | Add `useSEO` to all page components | 1 day | High | None |
| **P0** | Expand SSG to cover all videos (remove 100 limit) | 1 day | Very High | API pagination working |
| **P0** | Fix `og:locale` to be dynamic | 0.5 day | Medium | None |
| **P0** | Add `x-default` hreflang | 0.5 day | Medium | None |
| **P1** | Generate actress SSG pages + Person schema | 2 days | High | `actor/lists` API |
| **P1** | Generate publisher SSG pages + Organization schema | 1 day | High | `publisher/lists` API |
| **P1** | Comprehensive sitemap with sitemap index | 1 day | High | After SSG expansion |
| **P1** | Add video sitemap extension tags | 0.5 day | Medium | After sitemap work |
| **P1** | Localize breadcrumbs | 0.5 day | Medium | None |
| **P2** | Add Russian language SEO | 1 day | Medium | `ru` translations exist |
| **P2** | Semantic HTML (`<main>`, `<article>`, etc.) | 1 day | Low-Medium | None |
| **P2** | Add `dns-prefetch`/`preconnect` | 0.5 day | Low | None |
| **P2** | WebSite + SearchAction schema on homepage | 0.5 day | Medium | None |
| **P2** | Image `alt` tag audit and fix | 1 day | Low-Medium | None |
| **P3** | `ItemList` schema on list pages | 1 day | Low | None |
| **P3** | Internal linking optimization | 2 days | Medium | None |
| **P3** | Consider prerender service as SSR alternative | Research | Very High | Budget/infrastructure |
| **P3** | Category page SSG | 1 day | Medium | `category/lists` API |
| **P3** | Homepage SSG with localized variants | 1 day | Medium | None |

---

## 8. New Recommendations from Competitor Research

Based on the live site analysis, here are additional items not in the original plan:

### 8.1 Add RTA Age Rating Meta Tag (from Pornhub/XVideos)
```html
<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">
<meta name="rating" content="adult">
```
This is an industry standard for responsible age-gating. Search engines use this to filter results for SafeSearch.

### 8.2 Add `og:duration` to Video Pages (from XVideos)
XVideos uses `og:duration` in seconds alongside JSON-LD duration in ISO 8601:
```html
<meta property="og:duration" content="5400">
```
This improves social media preview cards by showing video length.

### 8.3 Include `contentUrl` in VideoObject JSON-LD (from XVideos)
XVideos includes the actual video file URL in their structured data. If feasible:
```json
"contentUrl": "https://cdn.example.com/video.m3u8"
```
Google uses this for video indexing and rich snippets.

### 8.4 Add Actress Name to Video Title (from Jable.tv)
Jable.tv includes actress names in their `<title>`:
```
IPZZ-462 [Video Title] 北岡果林 - Jable.TV
```
Users frequently search by actress name + mash code. Our current format is:
```
MMND-214 [title] - AI JAV
```
**Recommended format**: `MMND-214 [title] [actress names] - AI JAV`

### 8.5 Consider Subdomain vs Subdirectory for Languages
- **Pornhub**: Subdomain (`de.pornhub.com`, `jp.pornhub.com`)
- **XVideos**: Subdomain + dedicated domains (`fr.xvideos.com`, `xvideos.es`)
- **Our site**: Subdirectory (`/en/watch/...`, `/zh/watch/...`)

Subdirectory approach is fine and simpler to manage. No change needed here.

### 8.6 Add PWA Manifest (from XVideos/JavDB)
```html
<link rel="manifest" href="/manifest.json">
```
PWA support helps with mobile engagement and can improve Core Web Vitals.

### 8.7 Add OpenSearch Support (from JavDB)
```html
<link rel="search" type="application/opensearchdescription+xml" title="AI JAV Search" href="/opensearch.xml">
```
Allows users to add the site as a search engine in their browser.

### 8.8 Use `og:type` = `video.movie` (from XVideos)
XVideos uses `video.movie` instead of `video.other`. This may provide better social card rendering. Consider switching.

---

## Summary

The biggest SEO win is **expanding pre-rendered (SSG) pages to cover all content** — not just the latest 100 videos. This single change would make thousands of pages crawlable and indexable. Combined with adding structured data to actress/publisher pages and fixing hreflang implementation, the site would have a competitive SEO foundation similar to well-ranking video sites.

The recommended approach is:
1. **Phase 1** (immediate): Add client-side SEO hooks to all pages as a quick fix
2. **Phase 2** (next sprint): Expand the SSG script to generate all indexable pages
3. **Phase 3** (following sprint): Add Russian language support and advanced optimizations
4. **Phase 4** (ongoing): Monitor with Google Search Console, iterate on structured data, internal linking
