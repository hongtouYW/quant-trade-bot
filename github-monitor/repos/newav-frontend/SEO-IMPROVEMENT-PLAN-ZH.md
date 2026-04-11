# InsAV SEO 优化改进方案

## 目录
1. [参考网站分析 (missav.ws)](#1-参考网站分析)
2. [当前状态审计](#2-当前状态审计)
3. [关键问题与差距](#3-关键问题与差距)
4. [实施方案](#4-实施方案)
5. [后端需要的变更](#5-后端需要的变更)
6. [逐文件变更清单](#6-逐文件变更清单)
7. [优先级与工作量矩阵](#7-优先级与工作量矩阵)

---

## 1. 参考网站分析

### 真实竞品研究：竞争对手 SEO 实现方式

我们分析了以下真实网站，了解它们的 SEO 实现方式：

| 网站 | 类型 | 可访问性 | 主要发现 |
|------|------|---------|---------|
| **Pornhub** | 最大成人管站 | ✅ 完整 HTML | 最佳 hreflang（17种语言），WebSite + Organization JSON-LD，SearchAction，规范URL |
| **XVideos** | #2 成人管站 | ✅ 完整 HTML | 每个视频都有 VideoObject JSON-LD，完整 hreflang 含 x-default，og:duration，AMP 页面 |
| **JavDB** | JAV 数据库 | ✅ 完整 HTML | SSR（Rails），CDN preconnect，OpenSearch，优秀的 meta 标签 |
| **Jable.tv** | JAV 流媒体 | ✅ 完整 HTML | 每个视频都有 OG 标签，关键词丰富的 meta，标题含番号 |
| **missav.ws** | JAV 流媒体 | ❌ Cloudflare | 被阻止（但以优秀的 SSR SEO 闻名） |
| **SpankBang** | 成人管站 | ❌ Cloudflare | 被阻止 |
| **SupJav** | JAV 聚合站 | ❌ Cloudflare | 被阻止 |

---

#### Pornhub：详细发现

**首页 JSON-LD**（实际提取数据）：
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
  "sameAs": ["https://twitter.com/pornhub", "https://www.instagram.com/pornhub", "https://www.youtube.com/channel/UCYsYJ6od6t1lWnQg-A0n6Yw"]
}
```

**hreflang 策略**（17种语言变体，使用**子域名**方式）：
```html
<link rel="alternate" hreflang="x-default" href="https://www.pornhub.com/...">
<link rel="alternate" hreflang="en" href="https://www.pornhub.com/...">
<link rel="alternate" hreflang="de" href="https://de.pornhub.com/...">
<link rel="alternate" hreflang="fr" href="https://fr.pornhub.com/...">
<link rel="alternate" hreflang="ja" href="https://jp.pornhub.com/...">
<link rel="alternate" hreflang="zh" href="https://cn.pornhub.com/...">
<link rel="alternate" hreflang="ru" href="https://rt.pornhub.com/...">
<!-- + de-DE, ru-RU, ru-BY, fil-PH 区域变体 -->
```

**关键模式**：
- `<html lang="en">` 正确设置
- 每个页面都有 Canonical URL：`<link rel="canonical" href="...">`
- 演员页面有独特的标题+描述（如 "Mia Khalifa: Sexy Lebanese Porn Star Videos | Pornhub"）
- 搜索页面保留查询参数的 canonical
- CDN 域名的 `dns-prefetch`
- RSS feed 替代链接
- `meta name="rating" content="RTA-5042-1996-1400-1577-RTA"`（年龄验证标准）

---

#### XVideos：详细发现

**视频页面 JSON-LD**（实际提取数据）：
```json
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "视频标题",
  "description": "视频标题",
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

**视频页面 OG 标签**（实际数据）：
```html
<meta property="og:title" content="视频标题">
<meta property="og:type" content="video.movie">
<meta property="og:url" content="https://www.xvideos.com/video.xxx/slug">
<meta property="og:duration" content="727">  <!-- 秒！ -->
<meta property="og:image" content="https://thumb-cdn77.xvideos-cdn.com/.../xv_30_t.jpg">
```

**关键模式**：
- `og:type` = `video.movie`（而非 `video.other`）
- `og:duration` 以秒为单位（非 ISO 8601）
- JSON-LD 中 `contentUrl` 指向实际 MP4 文件
- `thumbnailUrl` 是数组（多缩略图）
- 语言使用子域名策略（fr.xvideos.com、de.xvideos.com）
- 部分地区使用独立域名（xvideos.es、xvideos-india.com、xv-ru.com）
- AMP 页面：`<link rel="amphtml" href="https://amp.xvideos.com/">`
- PWA 支持：`manifest.json`
- 频道/演员页面有完整 hreflang 含 `x-default`

---

#### Jable.tv：详细发现（JAV 专项）

**视频页面 Meta 标签**（实际数据）：
```html
<title>IPZZ-462 [中文标题含番号] - Jable.TV | 免費高清AV在線看</title>
<meta name="description" content="此作品曾在本站上傳，現已更新至中文字幕版。">
<meta name="keywords" content="中文字幕, 角色劇情, 制服誘惑, 少女, 下雨天, 童貞, OL, 口爆, 出軌, 中出, 北岡果林">
<meta property="og:title" content="IPZZ-462 [标题] 北岡果林">
<meta property="og:image" content="https://assets-cdn.jable.tv/.../preview.jpg">
<meta property="og:description" content="此作品曾在本站上傳，現已更新至中文字幕版。">
```

**关键模式**：
- **标题中包含番号** — JAV SEO 的关键（用户按番号搜索）
- **标题中包含女优名** — "IPZZ-462 ... 北岡果林"
- **关键词丰富的 meta keywords** — 标签、分类、女优名全部包含
- 无 hreflang（单语言网站）
- 未发现 JSON-LD 结构化数据（错失机会）
- 标题末尾附加网站名和分隔符

---

#### JavDB：详细发现

```html
<title>JavDB, Online information source for adult movies</title>
<meta name="description" content="An magnet links sharing website for adult movies...">
<meta name="keywords" content="JavDB,Magnet Links Sharing,Adult Video Magnet Links,Japanese Adult Movies,JAV ID Search">
<link rel="preconnect" href="https://c0.jdbstatic.com/">
<link rel="search" type="application/opensearchdescription+xml" title="searchTitle" href="/opensearch.xml">
<link rel="manifest" href="/manifest.json">
```

**关键模式**：
- 服务端渲染（Ruby on Rails）
- CDN 域名 `preconnect`
- OpenSearch 集成（浏览器搜索栏）
- PWA manifest
- CSRF 保护（服务端渲染应用）
- body 上有 `data-lang` 和 `data-domain` 属性供 JS 语言处理

---

### 总结：顶级网站做法 vs 我们的做法

| 功能 | Pornhub | XVideos | Jable.tv | JavDB | **我们的网站** |
|------|---------|---------|----------|-------|--------------|
| SSR/完整 HTML | ✅ | ✅ | ✅ | ✅ | ⚠️ 部分（100个视频） |
| hreflang + x-default | ✅ 17种语言 | ✅ 10种语言 | ❌ | ❌ | ⚠️ 2种语言，无 x-default |
| VideoObject JSON-LD | ❌ 仅首页 | ✅ 每个视频 | ❌ | ❌ | ✅ 仅 SSG 视频 |
| WebSite JSON-LD | ✅ | ❌ | ❌ | ❌ | ❌ |
| Organization JSON-LD | ✅ | ❌ | ❌ | ❌ | ❌ |
| SearchAction | ✅ | ❌ | ❌ | ❌ | ❌ |
| 规范 URL | ✅ | ✅ | ❌ | ❌ | ✅ 仅视频 |
| OG 标签（视频页） | ✅ | ✅ + og:duration | ✅ | ❌ | ✅ |
| 标题含番号 | N/A | N/A | ✅ | ✅ | ✅ |
| 标题含女优名 | N/A | N/A | ✅ | ✅ | ❌ |
| dns-prefetch/preconnect | ✅ | ✅ | ❌ | ✅ | ❌ |
| AMP 页面 | ❌ | ✅ | ❌ | ❌ | ❌ |
| PWA manifest | ❌ | ✅ | ❌ | ✅ | ❌ |
| RTA 年龄分级 meta | ✅ | ✅ | ❌ | ❌ | ❌ |
| OpenSearch | ❌ | ❌ | ❌ | ✅ | ❌ |

---

### 优秀视频流媒体网站的 SEO 做法（综合最佳实践）

基于以上分析，这些网站排名高是因为实现了以下 SEO 模式：

#### 1.1 服务端渲染 HTML
- **每个页面**都返回完整渲染的 HTML，所有 meta 标签、结构化数据和内容在 JavaScript 执行**之前**就对爬虫可见
- 社交媒体抓取器（Facebook、Twitter、Telegram）和搜索引擎爬虫在第一次请求就能获得完整页面
- 这是**最大的区别因素** — 使用客户端 meta 标签的 CSR SPA 对爬虫几乎不可见

#### 1.2 多语言 URL 架构
```
/en/          → 英文首页
/zh/          → 中文首页
/ja/          → 日文首页
/en/dm190/th  → 英文视频页（含 SEO slug）
/zh/dm190/th  → 中文视频页（含 SEO slug）
```
- 每个语言版本都有独立的 URL 路径（子目录策略）
- 每个页面都有 `hreflang` 标签指向所有语言替代版本 + `x-default`
- `<html lang="xx">` 属性匹配页面语言

#### 1.3 每页完整的 Meta 标签
```html
<title>视频标题 | 分类 - 网站名</title>
<meta name="description" content="独特的 150-160 字符描述，含关键词...">
<meta name="keywords" content="标签1, 标签2, 演员名, 发行商名">
<link rel="canonical" href="https://domain.com/en/video-slug/">

<!-- Open Graph -->
<meta property="og:type" content="video.other">
<meta property="og:title" content="视频标题">
<meta property="og:description" content="独特描述...">
<meta property="og:image" content="https://cdn.example.com/thumb.jpg">
<meta property="og:image:width" content="1280">
<meta property="og:image:height" content="720">
<meta property="og:url" content="https://domain.com/en/video-slug/">
<meta property="og:site_name" content="网站名">
<meta property="og:locale" content="en_US">
<meta property="og:locale:alternate" content="zh_CN">
<meta property="og:video:tag" content="标签1">
<meta property="video:actor" content="演员名">
<meta property="video:director" content="导演名">
<meta property="video:duration" content="5400"> <!-- 秒 -->

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="视频标题">
<meta name="twitter:description" content="独特描述...">
<meta name="twitter:image" content="https://cdn.example.com/thumb.jpg">

<!-- hreflang -->
<link rel="alternate" hreflang="en" href="https://domain.com/en/video-slug/">
<link rel="alternate" hreflang="zh" href="https://domain.com/zh/video-slug/">
<link rel="alternate" hreflang="x-default" href="https://domain.com/en/video-slug/">
```

#### 1.4 丰富的 JSON-LD 结构化数据

**VideoObject** — 每个视频页面：
```json
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "视频标题",
  "description": "视频描述...",
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
  "actor": [{"@type": "Person", "name": "演员名", "url": "https://domain.com/actress/123"}],
  "publisher": {"@type": "Organization", "name": "发行商名"},
  "keywords": "标签1, 标签2, 标签3"
}
```

**BreadcrumbList** — 每个页面（本地化）：
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "首页", "item": "https://domain.com/zh/"},
    {"@type": "ListItem", "position": 2, "name": "分类名", "item": "https://domain.com/zh/category/5"},
    {"@type": "ListItem", "position": 3, "name": "视频标题"}
  ]
}
```

**Person** schema — 演员/女优页面：
```json
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "女优名",
  "image": "https://cdn.example.com/actress.jpg",
  "url": "https://domain.com/zh/actress/123",
  "jobTitle": "演员",
  "knowsAbout": ["标签1", "标签2"]
}
```

**Organization** schema — 发行商页面：
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "发行商名",
  "logo": "https://cdn.example.com/publisher-logo.jpg",
  "url": "https://domain.com/zh/publisher/456"
}
```

**WebSite** schema — 首页（启用站内搜索框）：
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "网站名",
  "url": "https://domain.com/",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://domain.com/search?keyword={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
```

#### 1.5 语义化 HTML 结构
```html
<header><!-- 网站导航、logo --></header>
<main>
  <article>
    <h1>视频标题 - 番号</h1>
    <section class="video-player">...</section>
    <section class="video-info">
      <h2>视频详情</h2>
      <p>描述文本...</p>
    </section>
    <section class="related-videos">
      <h2>相关视频</h2>
    </section>
  </article>
</main>
<footer><!-- 链接、版权 --></footer>
```

#### 1.6 全面的 Sitemap 策略
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
- Sitemap 索引 + 按内容类型分离的 sitemap
- 视频 sitemap 带 `<video:video>` 扩展标签
- 每个 sitemap 限制 50,000 个 URL
- 包含所有语言变体

#### 1.7 性能与技术 SEO
- 对 CDN 域名使用 `dns-prefetch` 和 `preconnect`
- 首屏以下图片使用懒加载
- 图片使用描述性 `alt` 属性
- SSR/预渲染带来快速的 TTFB（首字节时间）
- 压缩响应（gzip/brotli）
- 启用 HTTP/2 或 HTTP/3

---

## 2. 当前状态审计

### 已有功能（正常工作）
| 功能 | 状态 | 备注 |
|------|------|------|
| `index.html` 中的基础 meta 标签 | ✅ | Title、description、OG、Twitter |
| `useSEO` hook（客户端） | ✅ | JS 加载后设置 meta 标签 |
| `useVideoSEO` hook（客户端） | ✅ | VideoObject JSON-LD + 面包屑 |
| 视频页面 SSG（`generate-seo.mjs`） | ✅ | 预渲染约 100 个视频页面 |
| `robots.txt` | ✅ | 配置正确 |
| 规范 URL（视频页面） | ✅ | `/{lang}/watch/{id}/{slug}/` |
| SEO 友好的 URL slug | ✅ | 番号 + 标题 slug 化 |
| hreflang（仅 SSG 视频页面） | ✅ | en、zh 替代版本 |
| Sitemap 生成 | ⚠️ | 仅视频页面，非常有限 |

### 缺失功能（关键差距）
| 功能 | 状态 | 影响 |
|------|------|------|
| 所有页面的服务端渲染 | ❌ | **严重** — 爬虫看到的是空的 `<div id="root">` |
| 女优页面 SEO | ❌ | 无 meta 标签，无结构化数据 |
| 发行商页面 SEO | ❌ | 无 meta 标签，无结构化数据 |
| 分类页面 SEO | ❌ | 无 meta 标签，无结构化数据 |
| 首页 SEO | ❌ | 仅通用的静态标签 |
| 非视频页面的 hreflang | ❌ | 无语言替代版本 |
| SEO 中的俄语支持 | ❌ | i18n 支持 `ru`，但 SEO 不支持 |
| `x-default` hreflang | ❌ | 缺少默认语言回退 |
| 非视频内容的 Sitemap | ❌ | 女优、发行商、分类未包含在 sitemap 中 |
| Sitemap 索引策略 | ❌ | 仅单一扁平 sitemap |
| 带 `<video:video>` 的视频 Sitemap | ❌ | 仅标准 sitemap |
| `WebSite` schema（首页） | ❌ | 无站内链接搜索框 |
| `Person` schema（女优页面） | ❌ | 缺少结构化数据 |
| `Organization` schema（发行商页面） | ❌ | 缺少结构化数据 |
| `ItemList` schema（列表页面） | ❌ | 缺少结构化数据 |
| 语义化 HTML（`<main>`、`<article>`） | ❌ | 到处使用通用 `<div>` |
| `og:locale` 按语言动态设置 | ❌ | 硬编码为 `zh_CN` |
| `og:image:width/height` | ❌ | 缺少图片尺寸 |
| 面包屑本地化 | ❌ | 英文页面也硬编码为"首页" |
| API/CDN 的 `dns-prefetch` | ❌ | 仅对 Google Fonts 做了 preconnect |
| 图片 `alt` 描述标签 | ⚠️ | 部分实现 |
| 内部链接优化 | ⚠️ | 可以改进 |
| `<link rel="preload">` 关键资源 | ❌ | 未实现 |

---

## 3. 关键问题与差距

### 问题 #1：客户端渲染 = 对大多数爬虫不可见（严重）

**问题**：该应用是 React SPA。当爬虫（Google、Bing、社交媒体）抓取除预生成的约 100 个视频页面之外的任何页面时，看到的是：
```html
<title>AI JAV - 高清日本成人视频在线观看</title>
<meta name="description" content="AI JAV 提供高清日本成人视频在线观看...">
<div id="root"></div>
```
每个页面都有**相同的通用标题和描述**。实际内容只有在 JavaScript 执行后才会出现，而大多数爬虫要么不执行 JS，要么执行得很差。

**影响**：
- Google 可能索引页面但标题/描述错误
- 社交媒体分享始终显示通用首页信息
- Bing、Yandex 和其他爬虫看到零内容
- Telegram/Discord/WhatsApp 链接预览在非 SSG 页面上无法正常工作

**解决方案**：扩展 SSG（静态站点生成）脚本，预渲染**所有**可索引页面，而不仅仅是视频。

### 问题 #2：仅预渲染了约 100 个视频页面

**问题**：`generate-seo.mjs` 仅获取视频列表的第 1 页（100 条记录）并为其生成 HTML。任何不在最新 100 个视频中的内容都只能依赖通用 SPA。

**影响**：绝大多数内容对搜索引擎不可见。

**解决方案**：为所有视频、女优、发行商和分类生成页面。

### 问题 #3：女优和发行商页面完全没有 SEO

**问题**：`ActressInfo.tsx` 和 `PublisherInfo.tsx` 完全没有调用 `useSEO()`。连客户端的 meta 标签更新都缺失。

**影响**：这些页面在搜索结果和社交分享中始终显示默认的首页标题/描述。

**解决方案**：
1. 在这些组件中添加 `useSEO()` 调用（立即修复）
2. 为这些页面添加 SSG 生成（正式修复）

### 问题 #4：缺少 `x-default` hreflang

**问题**：SSG 视频页面上存在 `en` 和 `zh` 的 hreflang 链接，但没有 `x-default` 用于语言不匹配的用户。

**影响**：Google 不知道对其他语言的用户应该显示哪个版本。

**解决方案**：添加 `<link rel="alternate" hreflang="x-default" href="...">` 指向英文版本。

### 问题 #5：Sitemap 不够完善

**问题**：Sitemap 仅包含 2 个 URL（1 个视频 x 2 种语言，或最多 200 个对应 100 个视频）。缺少：首页、所有分类页面、所有女优页面、所有发行商页面。

**影响**：Google 仅通过爬取链接来发现内容，速度慢且不完整。

**解决方案**：生成包含按内容类型分离的 sitemap 的完整 sitemap 索引。

---

## 4. 实施方案

### 第一阶段：快速见效（客户端修复） — 1-2 天

这些变更即使没有 SSR/SSG 也能改善 SEO，因为 Googlebot 会执行 JavaScript。

#### 1.1 为所有页面组件添加 `useSEO`

**需要修改的文件**：
- `src/pages/Home.tsx` — 添加 WebSite schema + 首页 SEO
- `src/pages/ActressInfo.tsx` — 添加 Person schema + 女优 SEO
- `src/pages/PublisherInfo.tsx` — 添加 Organization schema + 发行商 SEO
- `src/pages/CategoryVideos.tsx` — 添加分类 SEO
- `src/pages/LatestVideos.tsx` — 添加列表页 SEO
- `src/pages/FreeVideos.tsx` — 添加列表页 SEO
- `src/pages/SearchResults.tsx` — 添加 `noIndex`（搜索结果不应被索引）
- `src/pages/AllActresses.tsx` — 添加列表 SEO
- `src/pages/AllPublishers.tsx` — 添加列表 SEO

**ActressInfo.tsx 示例**：
```typescript
useSEO({
  title: actress?.name,
  description: `在线观看 ${actress?.name} 的视频。共 ${actress?.video_count} 部视频。`,
  keywords: actress?.name,
  canonicalUrl: `${BASE_URL}/actress/${actress?.id}`,
  ogImage: actress?.avatar,
  ogUrl: `${BASE_URL}/actress/${actress?.id}`,
});
```

#### 1.2 修复 `og:locale` 为动态值

**文件**：`src/hooks/useSEO.ts`

根据当前语言添加 `og:locale`：
```typescript
const localeMap = { zh: "zh_CN", en: "en_US", ru: "ru_RU" };
setMetaTag("og:locale", localeMap[currentLang] || "zh_CN", "property");
```

#### 1.3 客户端添加 hreflang 链接

**文件**：`src/hooks/useSEO.ts` 或新建 `src/hooks/useHreflang.ts`

根据当前路由动态注入 hreflang `<link>` 标签：
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

#### 1.4 面包屑 Schema 本地化

**文件**：`src/hooks/useSEO.ts`

将硬编码的"首页"替换为 i18n 感知的翻译：
```typescript
const breadcrumbHomeNames = { en: "Home", zh: "首页", ru: "Главная" };
```

#### 1.5 添加语义化 HTML

**文件**：布局和页面组件

将通用 `<div>` 包装器替换为语义化元素：
- `<main>` 用于主要内容区域
- `<article>` 用于视频详情、女优详情、发行商详情
- `<section>` 用于不同的内容区块（相关视频、评论）
- `<nav>` 用于导航元素（侧边栏中可能已有）
- `<header>` 和 `<footer>` 用于页面头部/底部

#### 1.6 添加 `dns-prefetch` 和 `preconnect`

**文件**：`index.html`

```html
<link rel="dns-prefetch" href="https://newavapi.9xyrp3kg4b86.com">
<link rel="preconnect" href="https://newavapi.9xyrp3kg4b86.com">
<!-- 如果适用，添加视频缩略图/内容的 CDN 域名 -->
```

---

### 第二阶段：扩展 SSG 脚本 — 3-5 天

这是**影响最大**的变更。扩展 `generate-seo.mjs` 以预渲染所有可索引页面。

#### 2.1 生成所有视频页面（不仅仅是最新的 100 个）

**文件**：`scripts/generate-seo.mjs`

变更：
- 分页遍历完整的视频列表 API（所有页面，不仅仅是第 1 页）
- 移除 `LIMIT` 上限或大幅提高
- 添加速率限制以避免 API 过载
- 按批次处理以提高内存效率

```javascript
// 原来：{ page: 1, limit: 100 }
// 改为：{ page: 1..N, limit: 100 } 直到 last_page
```

#### 2.2 生成女优页面

**在 `generate-seo.mjs` 中的新函数**：

```javascript
async function generateActressPages(baseHtml) {
  const actresses = await apiPost("actor/lists", { page: 1, limit: 1000 }, "en");

  for (const actress of actresses.data.data) {
    for (const lang of LANGS) {
      const actressInfo = await apiPost("actor/info", { id: actress.id }, lang);
      // 生成 HTML，包含：
      // - 标题："女优名 - 视频 | AI JAV"
      // - 描述："在线观看女优名的视频。共 X 部视频。"
      // - JSON-LD Person schema
      // - hreflang 链接
      // - BreadcrumbList：首页 > 女优 > 女优名
    }
  }
}
```

所需 API：`actor/lists`（分页）、`actor/info`（单个女优详情）

#### 2.3 生成发行商页面

与女优页面类似，但使用 Organization schema。

所需 API：`publisher/lists`（分页）

#### 2.4 生成分类页面

所需 API：`category/lists` 或等效接口

#### 2.5 生成首页变体

```javascript
// 生成 /en/index.html 和 /zh/index.html
// 包含 WebSite schema、SearchAction、本地化 meta 标签
```

#### 2.6 生成完整的 Sitemap 索引

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

生成：
- `sitemap-index.xml`（主索引）
- `sitemap-videos-1.xml`、`sitemap-videos-2.xml`、...（每个最多 50k URL）
- `sitemap-actresses.xml`
- `sitemap-publishers.xml`
- `sitemap-categories.xml`
- `sitemap-pages.xml`（首页、静态页面）

#### 2.7 添加视频 Sitemap 扩展

```xml
<url>
  <loc>https://xtw53.top/en/watch/123/video-slug/</loc>
  <video:video>
    <video:thumbnail_loc>https://cdn.example.com/thumb.jpg</video:thumbnail_loc>
    <video:title>视频标题</video:title>
    <video:description>视频描述...</video:description>
    <video:duration>5400</video:duration>
    <video:publication_date>2025-01-15T00:00:00+08:00</video:publication_date>
    <video:tag>标签1</video:tag>
    <video:tag>标签2</video:tag>
  </video:video>
</url>
```

---

### 第三阶段：添加俄语 SEO 支持 — 1-2 天

#### 3.1 扩展 LANGS 数组
**文件**：`scripts/generate-seo.mjs`
```javascript
const LANGS = ["en", "zh", "ru"];
```

#### 3.2 将俄语添加到 URL 路由
**文件**：`src/lib/watch-url.ts`
```typescript
const SUPPORTED_LANGS = ["en", "zh", "ru"];
```

#### 3.3 添加俄语 hreflang 链接
所有生成的页面应包含：
```html
<link rel="alternate" hreflang="en" href="...">
<link rel="alternate" hreflang="zh" href="...">
<link rel="alternate" hreflang="ru" href="...">
<link rel="alternate" hreflang="x-default" href="..."> <!-- 英语 -->
```

#### 3.4 按语言本地化 Meta 标签
翻译默认描述和面包屑标签：
```javascript
const DEFAULTS = {
  en: { description: "Watch HD Japanese videos online...", home: "Home" },
  zh: { description: "AI JAV 提供高清日本成人视频在线观看...", home: "首页" },
  ru: { description: "Смотрите японские видео в HD онлайн...", home: "Главная" },
};
```

---

### 第四阶段：高级优化 — 持续进行

#### 4.1 考虑预渲染服务/动态渲染
如果全面 SSG 太复杂，可以考虑：
- **Prerender.io** 或类似服务，向爬虫提供预渲染的 HTML
- **动态渲染**：在 CDN/服务器层检测爬虫 User Agent 并提供预渲染页面
- 这是完全 SSR 的更简单替代方案

#### 4.2 为列表页添加 `ItemList` Schema
在分类、女优列表和发行商列表页面：
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

#### 4.3 添加 `SearchAction` Schema
在首页添加以启用 Google 站内链接搜索框：
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

#### 4.4 内部链接策略
- 每个视频页面应链接到其演员、发行商和标签
- 女优页面应链接到其所有视频
- 分类页面应链接到子分类和视频
- 页脚应包含主要分类、热门女优的链接
- 这样可以创建强大的内部链接图，帮助爬虫发现所有内容

#### 4.5 图片 SEO
- 为所有图片添加描述性 `alt` 属性
- 内容图片使用 `<img>` 而非 CSS 背景图
- 考虑添加 `srcset` 用于响应式图片
- 确保缩略图尺寸合适（不要太大）

---

## 5. 后端需要的变更

### 5.1 SSG 所需的 API 接口

SSG 脚本需要获取所有内容来预渲染页面。确保以下接口存在并返回分页数据：

| 接口 | 用途 | 所需响应格式 |
|------|------|-------------|
| `video/lists` | 所有视频（分页） | `{ data: { data: [...], last_page: N } }` |
| `video/info` | 单个视频详情 | 包含演员、发行商、标签的完整视频对象 |
| `actor/lists` | 所有女优（分页） | `{ data: { data: [...], last_page: N } }` |
| `actor/info` | 单个女优详情 | 姓名、头像、视频数量、简介 |
| `publisher/lists` | 所有发行商 | `{ data: { data: [...] } }` |
| `category/lists` | 所有分类 | `{ data: { data: [...] } }` |

**重要**：这些接口应该**无需认证**即可工作（或使用特殊的构建时令牌），因为 SSG 脚本在构建时运行。

### 5.2 新增 API 字段（锦上添花）

考虑添加以下字段以改善 SEO：

| 字段 | 接口 | 用途 |
|------|------|------|
| `seo_description` | `video/info` | 每个视频的人工编写 SEO 描述 |
| `actress.bio` | `actor/info` | 女优简介，用于 meta description |
| `publisher.description` | `publisher/info` | 发行商描述 |
| `video.content_url` | `video/info` | 视频直链，用于 VideoObject schema 的 `contentUrl` |
| `video.embed_url` | `video/info` | 嵌入 URL，用于 VideoObject schema 的 `embedUrl` |

### 5.3 API 性能考虑

SSG 脚本在构建时会发起大量 API 调用。考虑：
- 速率限制：请求之间添加延迟（如 100ms）
- 批量接口：创建支持多个 ID 的批量视频信息接口
- 缓存：构建期间缓存 API 响应以避免重复调用
- 增量构建：仅重新生成自上次构建以来有变更的内容页面

### 5.4 服务器/CDN 配置

为使预渲染页面正常工作，服务器/CDN 必须：

1. **请求 `/{lang}/watch/{id}/{slug}/` 时提供 `/{lang}/watch/{id}/{slug}/index.html`**
2. **为女优页面提供 `/{lang}/actress/{id}/index.html`**（新增）
3. **为发行商页面提供 `/{lang}/publisher/{id}/index.html`**（新增）
4. **对非预渲染路由回退到 `index.html`**（SPA 回退）
5. **提供正确的 `Content-Type`** 头
6. **启用 gzip/brotli** 压缩
7. **为预渲染页面设置适当的缓存头**

---

## 6. 逐文件变更清单

### 需要修改的文件

| 文件 | 变更内容 |
|------|---------|
| `index.html` | 添加 API 域名的 `dns-prefetch`/`preconnect`，添加 `og:image:width/height` |
| `src/hooks/useSEO.ts` | 添加动态 `og:locale`、hreflang 支持、面包屑本地化，添加 `useActressSEO`、`usePublisherSEO`、`useHomeSEO` hooks |
| `src/pages/Home.tsx` | 调用 `useSEO` 设置首页配置，添加 WebSite + SearchAction JSON-LD |
| `src/pages/ActressInfo.tsx` | 调用 `useSEO` 设置女优信息，添加 Person JSON-LD |
| `src/pages/PublisherInfo.tsx` | 调用 `useSEO` 设置发行商信息，添加 Organization JSON-LD |
| `src/pages/CategoryVideos.tsx` | 调用 `useSEO` 设置分类信息 |
| `src/pages/LatestVideos.tsx` | 调用 `useSEO` 设置页面标题 |
| `src/pages/FreeVideos.tsx` | 调用 `useSEO` 设置页面标题 |
| `src/pages/SearchResults.tsx` | 调用 `useSEO` 设置 `noIndex: true` |
| `src/pages/AllActresses.tsx` | 调用 `useSEO` 设置列表标题 |
| `src/pages/AllPublishers.tsx` | 调用 `useSEO` 设置列表标题 |
| `src/pages/VideoPlayer.tsx` | 添加 `og:image:width/height`，添加 `x-default` hreflang |
| `src/lib/watch-url.ts` | 将 `ru` 添加到 `SUPPORTED_LANGS` |
| `scripts/generate-seo.mjs` | 重大扩展（见第二阶段） |
| `public/robots.txt` | 更新 sitemap URL 为 sitemap-index.xml，添加语言前缀的 Allow 规则 |

### 新增文件

| 文件 | 用途 |
|------|------|
| `src/hooks/useHreflang.ts` | 可复用的 hreflang 链接注入 hook |
| `scripts/generate-actress-pages.mjs` | 女优页面 SSG（或合并到 generate-seo.mjs） |
| `scripts/generate-publisher-pages.mjs` | 发行商页面 SSG（或合并到 generate-seo.mjs） |
| `dist/sitemap-index.xml` | 主 sitemap 索引（生成） |
| `dist/sitemap-videos-*.xml` | 视频 sitemap（生成） |
| `dist/sitemap-actresses.xml` | 女优 sitemap（生成） |
| `dist/sitemap-publishers.xml` | 发行商 sitemap（生成） |

---

## 7. 优先级与工作量矩阵

| 优先级 | 任务 | 工作量 | 影响 | 依赖项 |
|--------|------|--------|------|--------|
| **P0** | 为所有页面组件添加 `useSEO` | 1 天 | 高 | 无 |
| **P0** | 扩展 SSG 覆盖所有视频（移除 100 上限） | 1 天 | 非常高 | API 分页正常工作 |
| **P0** | 修复 `og:locale` 为动态值 | 0.5 天 | 中 | 无 |
| **P0** | 添加 `x-default` hreflang | 0.5 天 | 中 | 无 |
| **P1** | 生成女优 SSG 页面 + Person schema | 2 天 | 高 | `actor/lists` API |
| **P1** | 生成发行商 SSG 页面 + Organization schema | 1 天 | 高 | `publisher/lists` API |
| **P1** | 完整的 sitemap + sitemap 索引 | 1 天 | 高 | SSG 扩展完成后 |
| **P1** | 添加视频 sitemap 扩展标签 | 0.5 天 | 中 | sitemap 工作完成后 |
| **P1** | 面包屑本地化 | 0.5 天 | 中 | 无 |
| **P2** | 添加俄语 SEO | 1 天 | 中 | `ru` 翻译已存在 |
| **P2** | 语义化 HTML（`<main>`、`<article>` 等） | 1 天 | 低-中 | 无 |
| **P2** | 添加 `dns-prefetch`/`preconnect` | 0.5 天 | 低 | 无 |
| **P2** | 首页 WebSite + SearchAction schema | 0.5 天 | 中 | 无 |
| **P2** | 图片 `alt` 标签审计和修复 | 1 天 | 低-中 | 无 |
| **P3** | 列表页 `ItemList` schema | 1 天 | 低 | 无 |
| **P3** | 内部链接优化 | 2 天 | 中 | 无 |
| **P3** | 考虑预渲染服务作为 SSR 替代方案 | 调研 | 非常高 | 预算/基础设施 |
| **P3** | 分类页面 SSG | 1 天 | 中 | `category/lists` API |
| **P3** | 首页 SSG 含本地化变体 | 1 天 | 中 | 无 |

---

## 8. 竞品研究后的新增建议

基于对真实网站的分析，以下是原方案中未涵盖的额外事项：

### 8.1 添加 RTA 年龄分级 Meta 标签（来自 Pornhub/XVideos）
```html
<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">
<meta name="rating" content="adult">
```
这是负责任的年龄门控行业标准。搜索引擎使用此标签来过滤安全搜索结果。

### 8.2 为视频页面添加 `og:duration`（来自 XVideos）
XVideos 同时使用秒为单位的 `og:duration` 和 ISO 8601 格式的 JSON-LD duration：
```html
<meta property="og:duration" content="5400">
```
这改善了社交媒体预览卡片，显示视频时长。

### 8.3 在 VideoObject JSON-LD 中包含 `contentUrl`（来自 XVideos）
XVideos 在结构化数据中包含实际视频文件 URL。如可行：
```json
"contentUrl": "https://cdn.example.com/video.m3u8"
```
Google 使用此信息进行视频索引和富摘要显示。

### 8.4 视频标题中添加女优名（来自 Jable.tv）
Jable.tv 在 `<title>` 中包含女优名：
```
IPZZ-462 [视频标题] 北岡果林 - Jable.TV
```
用户经常按女优名 + 番号搜索。我们当前格式：
```
MMND-214 [标题] - AI JAV
```
**推荐格式**：`MMND-214 [标题] [女优名] - AI JAV`

### 8.5 语言子域名 vs 子目录策略对比
- **Pornhub**：子域名（`de.pornhub.com`、`jp.pornhub.com`）
- **XVideos**：子域名 + 独立域名（`fr.xvideos.com`、`xvideos.es`）
- **我们的网站**：子目录（`/en/watch/...`、`/zh/watch/...`）

子目录方式没问题且更易管理。此处无需更改。

### 8.6 添加 PWA Manifest（来自 XVideos/JavDB）
```html
<link rel="manifest" href="/manifest.json">
```
PWA 支持有助于提升移动端用户参与度，并可改善 Core Web Vitals。

### 8.7 添加 OpenSearch 支持（来自 JavDB）
```html
<link rel="search" type="application/opensearchdescription+xml" title="AI JAV 搜索" href="/opensearch.xml">
```
允许用户在浏览器中将网站添加为搜索引擎。

### 8.8 使用 `og:type` = `video.movie`（来自 XVideos）
XVideos 使用 `video.movie` 而非 `video.other`。这可能提供更好的社交卡片渲染效果。建议考虑切换。

---

## 总结

最大的 SEO 提升在于**将预渲染（SSG）页面扩展到覆盖所有内容** — 而不仅仅是最新的 100 个视频。仅此一项变更就能使数千个页面可被爬取和索引。结合为女优/发行商页面添加结构化数据和修复 hreflang 实现，网站将拥有与排名靠前的视频网站相似的 SEO 竞争力基础。

推荐方案：
1. **第一阶段**（立即执行）：为所有页面添加客户端 SEO hooks 作为快速修复
2. **第二阶段**（下个迭代）：扩展 SSG 脚本以生成所有可索引页面
3. **第三阶段**（再下个迭代）：添加俄语支持和高级优化
4. **第四阶段**（持续进行）：使用 Google Search Console 监控，迭代优化结构化数据和内部链接
