# ADR-001: Migrate InsAV from Client-Side SPA to Server-Side Rendering

**Status:** Proposed
**Date:** 2026-03-24
**Deciders:** Engineering Team

---

## Context

InsAV is a video streaming site with a Chinese-language interface, built as a React 19 SPA with Vite, React Router v7, and TanStack Query. The site needs search engine visibility to drive organic traffic — users search for videos by mash codes (番号), actress names, and categories. Currently, crawlers see an empty `<div id="root">` on most pages, with only ~100 video pages pre-rendered via a post-build SSG script. The existing SEO improvement plan (see `SEO-IMPROVEMENT-PLAN.md`) focuses on expanding that SSG approach, but given the scale of content (thousands of videos, hundreds of actresses and publishers), a proper SSR solution would provide more sustainable, real-time coverage without needing to rebuild the entire site for every new video.

### Current Technical Landscape

- **31 page components**, **27 routes**, **10 service files**, **24+ custom hooks**
- All API calls go through a custom Axios instance with **AES-CBC encryption/decryption** middleware
- Authentication via **localStorage token** (`tokenNew`) with interceptors
- **6 React contexts** (User, Language, Config, AuthDialog, IdentityCard, GlobalImage) that depend on browser APIs
- i18n via **i18next** with browser language detection
- Styling: **Tailwind CSS v4** with Radix UI components
- Video player: **Vidstack** with HLS.js
- Existing SSG: `generate-seo.mjs` (468 lines) produces static HTML for ~100 video watch pages post-build

### Forces at Play

1. **SEO is critical** — video sites live and die by organic search traffic. Competitors (Jable.tv, JavDB, XVideos) all serve fully-rendered HTML.
2. **Content is dynamic** — new videos are added regularly, making static regeneration a maintenance burden.
3. **Not all pages need SSR** — user-specific pages (favorites, history, purchases) are behind authentication and don't need crawlability.
4. **Team familiarity** — the team knows React, Vite, and React Router v7 well.
5. **API encryption** — the custom AES-CBC layer on all API calls adds complexity to any server-side data fetching.
6. **Time-to-market** — SEO improvements are needed soon; a 6-month rewrite is not acceptable.

---

## Decision

Migrate to a **server-side rendering framework** to provide crawlable, fully-rendered HTML for all public-facing pages, while keeping authenticated/user-specific pages as client-rendered.

---

## Options Considered

### Option A: Next.js (App Router)

| Dimension | Assessment |
|-----------|------------|
| Complexity | **High** — full rewrite of routing, data fetching, and project structure |
| Cost | 6-10 weeks for core migration |
| Scalability | Excellent — ISR, streaming SSR, edge runtime |
| Team familiarity | Low — new mental model (RSC, server/client boundary, file-based routing) |
| Ecosystem maturity | Very high — largest community, most battle-tested |
| Deployment | Optimized for Vercel, but works on Node.js, Docker, self-hosted |

**How it works:** Next.js App Router defaults to React Server Components. Every component is a server component unless marked with `"use client"`. Data fetching happens in server components or via `fetch()` with caching. File-system based routing replaces React Router entirely.

**Pros:**
- Most mature SSR framework with the largest ecosystem and community support
- Built-in ISR (Incremental Static Regeneration) is ideal for video sites — generate popular pages statically, render the rest on-demand, and revalidate on a timer
- Image optimization, middleware, API routes, and edge runtime out of the box
- React Server Components reduce client-side JavaScript bundle significantly
- Excellent documentation and migration guides
- Massive hiring pool — most React developers know Next.js

**Cons:**
- **Requires a near-complete rewrite** — React Router v7 routes must be converted to file-system routing, all 31 page components restructured
- **React Router v7 is incompatible** — cannot coexist; all navigation logic must be rewritten
- **Server/client boundary is a paradigm shift** — every hook, context, and component must be evaluated for server vs. client placement. The 6 React contexts all use browser APIs and would need refactoring
- **TanStack Query integration is awkward** — Next.js prefers its own `fetch`-based caching over React Query for server components. Either abandon React Query (huge refactor) or use it only for client components (losing SSR benefits for data-heavy pages)
- **Vendor pressure toward Vercel** — while self-hosting works, some features (edge middleware, image optimization) are optimized for Vercel's platform
- **AES-CBC encryption middleware** would need to be reimplemented in server actions/route handlers

**Migration effort estimate:** **8-12 weeks** (1 developer)
- Week 1-2: Project scaffolding, build system migration, Tailwind/Radix setup
- Week 3-4: Convert routing structure (27 routes → file-system routes), layout migration
- Week 5-6: Migrate data fetching (services + hooks → server components or route handlers), reimplement encryption layer server-side
- Week 7-8: Migrate contexts to server-compatible patterns, authentication (cookies instead of localStorage)
- Week 9-10: Video player integration, i18n with next-intl, SEO metadata via `generateMetadata()`
- Week 11-12: Testing, performance optimization, deployment setup

---

### Option B: React Router v7 Framework Mode (formerly Remix)

| Dimension | Assessment |
|-----------|------------|
| Complexity | **Medium** — incremental migration possible since you already use React Router v7 |
| Cost | 4-7 weeks for core migration |
| Scalability | Good — SSR with streaming, loader/action patterns |
| Team familiarity | **High** — already using React Router v7 in SPA mode |
| Ecosystem maturity | Good — backed by Shopify, Remix heritage, active development |
| Deployment | Flexible — any Node.js host, Cloudflare Workers, Deno |

**How it works:** React Router v7 has three modes: declarative (basic SPA), data (SPA with loaders), and framework (full SSR). Framework mode adds server-side `loader` functions to each route that run before rendering, providing data to components. You keep your existing route structure and add `loader` exports to each route file. The same React Router APIs you already use (`useLoaderData`, `useNavigate`, etc.) work in all modes.

**Pros:**
- **Lowest migration friction** — you already use React Router v7. The upgrade path from SPA mode to framework mode is the most natural progression for this codebase
- **Incremental adoption** — can migrate route-by-route. Start with high-SEO-impact pages (video watch, actress, publisher) and leave authenticated pages as client-only
- **TanStack Query works naturally** — loaders can prefetch and dehydrate query data, hydrating the React Query cache on the client. This preserves your entire hooks layer
- **Keeps existing patterns** — contexts, hooks, components all remain largely unchanged. Just add `loader` functions to route files
- **Web-standard approach** — uses Request/Response APIs, form handling via actions, progressive enhancement
- **Vite-native** — built on Vite, so your existing Vite config, plugins, and Tailwind setup carry over with minimal changes
- **Flexible deployment** — works on any Node.js server, Cloudflare Workers, or serverless platforms

**Cons:**
- **Requires a Node.js server** — no longer a static site. Need to set up and maintain a server process (Express, Hono, or the built-in server)
- **Community guide for SPA→Framework migration is sparse** — most documentation assumes starting fresh. Some trial-and-error expected
- **File-based routing is new** — while you keep the React Router API, framework mode uses a `routes/` directory with file conventions (or a `routes.ts` config file). Your `App.tsx` routing definitions need restructuring
- **No built-in ISR** — unlike Next.js, there's no automatic incremental static regeneration. You'd implement caching at the CDN/server level manually
- **Smaller ecosystem** — fewer third-party integrations, templates, and tutorials compared to Next.js
- **SSR still runs at dev time** even with `ssr: false` on specific routes, which can cause issues during migration

**Migration effort estimate:** **5-8 weeks** (1 developer)
- Week 1: Enable framework mode, set up `routes.ts` config, migrate route definitions from `App.tsx`
- Week 2-3: Add `loader` functions to SEO-critical routes (video watch, actress, publisher, category pages), integrate server-side API calls with encryption
- Week 4: Migrate authentication to cookie-based sessions, adapt contexts for SSR compatibility
- Week 5: i18n server-side setup, metadata generation in loaders, structured data (JSON-LD)
- Week 6: Video player SSR hydration, remaining route migrations
- Week 7-8: Server deployment setup, CDN caching strategy, testing, performance tuning

---

### Option C: TanStack Start

| Dimension | Assessment |
|-----------|------------|
| Complexity | **Medium-High** — new framework, but familiar ecosystem (TanStack Query already in use) |
| Cost | 5-8 weeks for core migration |
| Scalability | Good — streaming SSR, selective SSR per route |
| Team familiarity | Medium — TanStack Query is known, but Router and Start are new |
| Ecosystem maturity | **Low-Medium** — reached 1.0 RC in late 2025, still young |
| Deployment | Flexible — Vite-based, works on Node.js, Cloudflare, etc. |

**How it works:** TanStack Start is a full-stack framework built on TanStack Router and Vite. It provides SSR, streaming hydration, and server functions with end-to-end type safety. Since InsAV already uses TanStack Query, the integration is seamless — query data can be prefetched in route loaders and streamed to the client.

**Pros:**
- **Best TanStack Query integration** — since both Query and Start are from the same ecosystem, SSR dehydration/hydration of query caches is first-class. Your 24+ custom hooks would work with minimal changes
- **Selective SSR** — can choose SSR vs client-only rendering per route, which is perfect for keeping authenticated pages client-only while SSR-ing public pages
- **Vite-native** — built on Vite, so existing config carries over
- **Type-safe routing** — full TypeScript inference for route params, search params, and loader data
- **Best benchmark performance** — outperforms Next.js and React Router in SSR throughput benchmarks (25% higher throughput than React Router framework mode)
- **Streaming SSR** — sends HTML as it becomes ready, improving Time to First Byte

**Cons:**
- **Young framework** — reached 1.0 RC in late 2025. Production battle-testing is limited compared to Next.js (8+ years) or Remix/React Router (4+ years)
- **Requires migrating from React Router** — must replace React Router v7 with TanStack Router. Different API, different mental model for route definitions
- **Smaller community** — fewer Stack Overflow answers, fewer tutorials, fewer third-party plugins
- **Risk of breaking changes** — as a young framework, API stability is less guaranteed
- **Documentation gaps** — while improving, some advanced patterns and edge cases are less documented
- **Hiring difficulty** — few developers have production TanStack Start experience

**Migration effort estimate:** **6-9 weeks** (1 developer)
- Week 1-2: Replace React Router v7 with TanStack Router, redefine all 27 routes with TanStack route trees
- Week 3-4: Add route loaders for SEO-critical pages, server-side API integration with encryption
- Week 5: Authentication migration, context adaptation, i18n server integration
- Week 6-7: Video player integration, SEO metadata, structured data
- Week 8-9: Deployment, caching, testing, performance optimization

---

### Option D: Expand Existing SSG (No Framework Change)

| Dimension | Assessment |
|-----------|------------|
| Complexity | **Low** — extends current approach without architectural changes |
| Cost | 2-3 weeks |
| Scalability | **Poor** — build time grows linearly with content, no real-time rendering |
| Team familiarity | **Very High** — extends existing `generate-seo.mjs` script |
| Ecosystem maturity | N/A — custom solution |
| Deployment | Same as today — static hosting |

**How it works:** This is what the existing `SEO-IMPROVEMENT-PLAN.md` proposes. Expand the `generate-seo.mjs` script to pre-render ALL video, actress, publisher, and category pages at build time instead of just 100 videos. Add comprehensive sitemaps, structured data, and meta tags to the generated HTML.

**Pros:**
- **Fastest to implement** — 2-3 weeks, extends existing working code
- **No architectural change** — keeps the SPA, just adds more pre-rendered HTML
- **No server required** — remains a static site on CDN
- **Zero runtime cost** — all computation happens at build time
- **Already partially implemented** — 468-line SSG script exists and works

**Cons:**
- **Build times will explode** — with thousands of videos × 2-3 languages, builds could take 30-60+ minutes as every page requires API calls
- **Stale content problem** — new videos aren't crawlable until the next build+deploy. If you build daily, there's up to 24 hours of invisible content
- **No incremental updates** — must regenerate ALL pages on every build (or build complex incremental logic)
- **Doesn't solve dynamic pages** — category pages with pagination, search results, filtered views can't be pre-rendered for every possible state
- **API rate limiting risk** — generating thousands of pages means thousands of API calls at build time
- **Maintenance burden** — the SSG script will grow increasingly complex as more page types are added
- **Not a long-term solution** — as content grows, this approach hits a ceiling. Most competitors moved to SSR for this reason

**Migration effort estimate:** **2-3 weeks** (1 developer)
- Week 1: Expand SSG to all videos (paginated), add actress and publisher page generation
- Week 2: Comprehensive sitemaps, structured data for all page types, Russian language support
- Week 3: Build optimization (parallel generation, caching), deployment pipeline for scheduled rebuilds

---

## Trade-off Analysis

### The Core Trade-off: Migration Effort vs. Long-Term Sustainability

| Factor | Option A (Next.js) | Option B (RR v7 Framework) | Option C (TanStack Start) | Option D (Expand SSG) |
|--------|--------------------|-----------------------------|---------------------------|----------------------|
| **Migration effort** | 8-12 weeks | 5-8 weeks | 6-9 weeks | 2-3 weeks |
| **Code reuse** | ~30% (near-rewrite) | ~70-80% (incremental) | ~50-60% (router swap) | ~95% (extension) |
| **SEO completeness** | Excellent | Excellent | Excellent | Good (static only) |
| **New content visibility** | Instant (SSR) | Instant (SSR) | Instant (SSR) | Delayed (next build) |
| **Runtime infrastructure** | Node.js server | Node.js server | Node.js server | Static CDN |
| **TanStack Query compat** | Awkward | Good | Excellent | N/A |
| **React Router v7 compat** | Must remove | Natural upgrade | Must replace | Unchanged |
| **Production track record** | Excellent (8+ years) | Good (Remix heritage) | Limited (~1 year) | Proven (custom) |
| **Scalability ceiling** | Very high | High | High | Medium (build times) |
| **Operational complexity** | Medium (server) | Medium (server) | Medium (server) | Low (static) |

### Key Decision Factors for InsAV Specifically

1. **React Router v7 is already in use** — Option B (framework mode) is the natural evolution. You're essentially "turning on" SSR for your existing router rather than replacing it.

2. **TanStack Query is deeply integrated** — 24+ hooks depend on it. Option B preserves this layer cleanly via loader prefetching. Option A would force an awkward dual-cache strategy. Option C has the best integration but requires a full router swap.

3. **Encryption middleware is unusual** — all API calls use AES-CBC encryption. This must work server-side regardless of framework choice. Options B and C (both Vite-based) make this easier since the existing Axios setup can be reused in loaders/server functions with minimal changes.

4. **Not all pages need SSR** — 12+ routes are authenticated (favorites, history, purchases, notifications). React Router v7 framework mode's per-route SSR control makes it easy to mark these as client-only.

5. **Content freshness matters** — new videos should be crawlable immediately, not after the next build. This rules out Option D as a long-term solution, though it's a valid interim step.

---

## Recommendation

**Option B: React Router v7 Framework Mode** is the strongest fit for InsAV.

The reasoning comes down to three factors. First, it offers the lowest migration risk because you're already on React Router v7 and this is the intended upgrade path from SPA to SSR within the same ecosystem. Second, it maximizes code reuse — your services, hooks, contexts, and components remain largely intact with `loader` functions added on top. Third, it balances time-to-value with long-term sustainability: 5-8 weeks is fast enough to ship within a quarter, while providing real SSR that scales with content growth.

### Phased Approach (Recommended)

**Phase 0 — Immediate (now, 2-3 weeks):** Execute the existing SSG expansion plan from `SEO-IMPROVEMENT-PLAN.md` Phase 1-2 as a quick win. This buys SEO time while the SSR migration is being built.

**Phase 1 — Foundation (weeks 1-2):** Enable framework mode, set up `routes.ts`, migrate route definitions, establish server-side encryption layer.

**Phase 2 — SEO-Critical Routes (weeks 3-5):** Add loaders to video watch, actress, publisher, and category pages. Implement `meta` exports for SEO metadata and structured data.

**Phase 3 — Full Migration (weeks 6-8):** Migrate remaining routes, implement cookie-based auth, adapt contexts, set up CDN caching, deploy.

---

## Consequences

### What becomes easier
- Every public page is immediately crawlable with full HTML, meta tags, and structured data
- New content is visible to search engines as soon as it's published — no build-and-deploy cycle
- Social media sharing shows correct previews for all pages (not just pre-rendered ones)
- Adding new page types (e.g., tag pages, series pages) automatically gets SSR without SSG script changes
- Performance improves (faster First Contentful Paint via streamed HTML)

### What becomes harder
- Deployment now requires a **Node.js server** instead of static hosting — need to set up and maintain server infrastructure (PM2, Docker, or serverless)
- **Local development** becomes slightly more complex with server-side code running
- Every new route must consider the **server/client boundary** — what runs on the server vs. the client
- **Debugging** is harder when issues span server and client code
- **Caching strategy** must be implemented at the server/CDN level (no more "just deploy static files")

### What we'll need to revisit
- **Authentication architecture** — move from localStorage tokens to HTTP-only cookies for SSR compatibility
- **CDN/caching strategy** — implement stale-while-revalidate or edge caching for SSR responses
- **Monitoring and error tracking** — need server-side error tracking (not just client-side)
- **CI/CD pipeline** — builds now produce a server application, not static files
- **The existing SSG script** — can be retired once SSR covers all routes
- **Rate limiting** — server-side API calls need rate limiting and circuit breaker patterns
- **Cost** — server hosting costs vs. static CDN costs

---

## Action Items

1. [ ] **Validate with a proof-of-concept** — convert one route (e.g., `/watch/:id`) to React Router v7 framework mode with a loader to confirm the encryption middleware works server-side and SEO output is correct
2. [ ] **Execute SSG quick wins** (Phase 0) — expand `generate-seo.mjs` per the existing SEO plan to get immediate crawlability while SSR is built
3. [ ] **Set up server infrastructure** — decide on hosting (VPS with PM2, Docker container, or serverless) and CI/CD for server deployment
4. [ ] **Plan authentication migration** — design cookie-based session strategy to replace localStorage tokens for SSR compatibility
5. [ ] **Create migration branch** — begin Phase 1 with framework mode enablement and route restructuring
6. [ ] **Define caching strategy** — determine CDN caching rules for SSR responses (e.g., cache public pages for 5 min, no-cache for authenticated routes)
7. [ ] **Set up server-side monitoring** — add error tracking and performance monitoring for the SSR server

---

## References

- [React Router v7 Modes Documentation](https://reactrouter.com/start/modes)
- [React Router SPA to Framework Mode Discussion](https://github.com/remix-run/react-router/discussions/13811)
- [TanStack Query SSR Guide](https://tanstack.com/query/v5/docs/react/guides/ssr)
- [TanStack Start vs Next.js Comparison](https://tanstack.com/start/latest/docs/framework/react/start-vs-nextjs)
- [React SSR Framework Benchmark](https://blog.platformatic.dev/react-ssr-framework-benchmark-tanstack-start-react-router-nextjs)
- [Next.js vs Remix Comparison](https://strapi.io/blog/next-js-vs-remix-2025-developer-framework-comparison-guide)
- [Vite SSR Guide](https://vite.dev/guide/ssr)
- [TanStack Start Overview](https://tanstack.com/start/latest/docs/framework/react/overview)
