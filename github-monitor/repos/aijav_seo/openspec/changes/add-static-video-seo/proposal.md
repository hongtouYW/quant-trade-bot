# Change: Add static SEO pages for video watch routes

## Version
- Target app version: `1.11.0`

## Why
Search engines need static HTML with indexable metadata, but this app is a client-only SPA and the server can only serve static files. We need a static generation approach that preserves the SPA experience while enabling SEO for video keywords and language variants.

## What Changes
- Add build-time generation of static HTML for video watch pages with SEO metadata and JSON-LD.
- Use language-scoped, keyword-friendly watch URLs (e.g., `/en/watch/:id/:slug` and `/zh/watch/:id/:slug`) and expose canonical + hreflang links.
- Use a slug format of `<video-code>-<title>` for SEO (slug is non-authoritative; `id` remains the fetch key).
- Generate and serve `sitemap.xml` (and optionally `robots.txt`) containing all watch URLs, refreshed daily via a build/deploy job.
- Ensure client-side routing hydrates normally after load so the SPA + video player work as usual, and apply `lang` from the URL path to i18n.
- Preserve legacy watch routes via client-side fallback to the new canonical URLs.
- Sync URL language segment when users switch languages in the UI to avoid URL/locale drift.
- Include tags and actors in video meta tags (e.g., `og:video:tag`, `video:actor`) and JSON-LD where available.

## User Flow
- User lands on `/en/watch/:id/:slug` from search.
- Static host serves prebuilt HTML with SEO metadata and JSON-LD.
- Browser loads SPA bundle, reads language from path, and hydrates.
- React Router renders the watch page and the player works as usual.

## Impact
- Affected specs: `specs/video-seo/spec.md` (new capability)
- Affected code: build-time generator scripts, `public/` static outputs, routing/link helpers, SEO hook(s), language switcher
