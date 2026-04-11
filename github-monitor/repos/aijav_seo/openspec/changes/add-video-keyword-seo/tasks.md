## 1. Implementation
- [ ] 1.1 Define language-scoped routing for `/en/watch/:id/:mash?` and `/zh/watch/:id/:mash?` while preserving `/watch/:id`.
- [ ] 1.2 Update VideoPlayer to derive `lang`, `id`, and `mash` from the URL and keep `id` as the fetch key.
- [ ] 1.3 Update SEO metadata to use the language-scoped canonical URL and include the `mash` code in title/description/keywords/JSON-LD.
- [ ] 1.4 Add `hreflang` alternate links for `en` and `zh` and include both in the sitemap.
- [ ] 1.5 Implement watch-only SSR handler that renders HTML with meta/OG/JSON-LD for `/{lang}/watch/:id/:mash?`.
- [ ] 1.6 Implement sitemap generator that paginates `/video/lists` and writes `sitemap.xml` (refresh schedule).
- [ ] 1.7 Update watch links across lists/cards to generate `/{lang}/watch/{id}/{mash}`.
- [ ] 1.8 Add fallback behavior for legacy routes (redirect or render with canonical).
- [ ] 1.9 Manual verification checklist for language URLs, SSR HTML, and sitemap output.
