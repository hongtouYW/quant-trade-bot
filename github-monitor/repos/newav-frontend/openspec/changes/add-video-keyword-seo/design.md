## Context
The VideoPlayer route currently expects a numeric `vid` and calls `/video/info` with that ID. The request is to support SEO-friendly URLs that include the video code (`mash`) and to make English and Chinese indexable via distinct URLs.

## Goals / Non-Goals
- Goals:
  - Use language-scoped URLs: `/{lang}/watch/{id}/{mash}`.
  - Keep numeric `id` as the fetch key to `/video/info` (no resolver required).
  - SSR `/watch` pages so crawlers receive metadata in HTML.
  - Include JSON-LD and `hreflang` alternates.
- Non-Goals:
  - Changing backend endpoints or adding a video lookup by code.

## Decisions
- Decision: Use `mash` as the URL slug only; the backend fetch remains `vid`.
- Decision: Add a language prefix (`/en`, `/zh`) so SEO can index both language variants.
- Decision: Implement watch-only SSR for `/watch/:id/:mash?` on the Node server; serve SPA for all other routes.
- Decision: Generate a static `sitemap.xml` on a schedule by paging through `/video/lists` (15k videos fits in a single sitemap).

## Risks / Trade-offs
- Requires routing changes and consistent link generation across the app.
- SEO impact depends on SSR availability; SPA-only rendering will still limit indexing.

## Migration Plan
- Introduce language-scoped routes while keeping `/watch/:id` as a legacy path.
- Use canonical URLs pointing to `/{lang}/watch/{id}/{mash}` to consolidate SEO signals.
 - Add a server job to refresh `sitemap.xml` on a schedule.

## Open Questions
- Confirm the definitive list of supported languages (currently `en` and `zh`).
- Confirm how the server determines language for SSR (URL segment → header).
