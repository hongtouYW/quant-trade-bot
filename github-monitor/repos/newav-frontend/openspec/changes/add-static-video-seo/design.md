## Context
- React SPA (Vite) with static hosting only (no SSR).
- Need SEO for video keywords and language variants.
- Catalog updates daily at unknown times.

## Goals / Non-Goals
- Goals:
  - Provide static, crawlable HTML for video watch routes.
  - Preserve SPA hydration and normal playback after page load.
  - Support language-scoped URLs with canonical + hreflang links.
  - Generate sitemap content that stays fresh with daily updates.
- Non-Goals:
  - Server-side rendering at request time.
  - Real-time SEO updates without a rebuild/deploy.

## Decisions
- Decision: Use a build-time generator to create static watch HTML and sitemap files.
- Decision: Embed SEO metadata (title, description, keywords, OpenGraph, JSON-LD) in the static HTML.
- Decision: Keep `id` as the fetch key and treat the slug as a non-authoritative SEO string.
- Decision: Use language-scoped path segments for canonical URLs and set i18n from the path on app boot.
- Decision: Slug format is `<video-code>-<title>` (code first to prioritize keyword search).
- Alternatives considered:
  - Dynamic SSR on the server (rejected: server is static-only).
  - Client-only metadata (rejected: search engines would miss data).

## Risks / Trade-offs
- Stale SEO if rebuilds lag behind new uploads → Mitigation: schedule daily rebuilds and document the workflow.
- Large sitemap size for big catalogs → Mitigation: consider sitemap index + paging if needed.

## Migration Plan
- Add static watch outputs + sitemap generation.
- Update routing and link helpers to point to canonical URLs.
- Preserve legacy watch URLs via redirect/fallback behavior.

## Open Questions
- How to handle missing translations for `en`/`zh` metadata.
- Maximum video count and any sitemap paging requirement.
