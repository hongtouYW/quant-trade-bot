# Change: Add language-scoped video URLs with keyword slugs and SEO metadata

## Why
Video pages are currently keyed by numeric IDs, which makes URLs less searchable by video codes like “NSF-001”. In addition, language selection is done by headers, so search engines cannot reliably index both English and Chinese pages. Adding language-scoped URLs that include the video code enables indexing by code and by language.

## What Changes
- Introduce language-scoped watch URLs: `/{lang}/watch/{id}/{mash}` (e.g., `/en/watch/12345/IENF-018`).
- Keep numeric `id` as the fetch key for `/video/info`, and use `mash` only as the SEO-friendly slug (no resolver needed).
- Use the language-scoped URL as the canonical URL and include the `mash` code in metadata (title/description/keywords/OpenGraph/JSON-LD).
- Add `hreflang` alternate links for `en` and `zh` variants and include both in the sitemap.
- Server-side render the watch page HTML (watch-only SSR) so crawlers receive metadata and JSON-LD without executing JS.
- Generate and serve a static `sitemap.xml` that is refreshed on a schedule by paging through the video list API.
- Preserve existing routes (`/watch/:id`) via redirects or fallback behavior.

## Impact
- Affected specs: `specs/video-seo/spec.md` (new capability)
- Affected code: `src/pages/VideoPlayer.tsx`, `src/hooks/useSEO.ts`, `src/hooks/video/useVideoInfo.ts`, `src/services/video.service.ts`, `src/App.tsx`, video list/link components that build watch URLs, server SSR handler, sitemap/robots generation
