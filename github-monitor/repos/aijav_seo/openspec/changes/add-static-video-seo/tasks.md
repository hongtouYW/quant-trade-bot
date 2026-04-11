## 1. Implementation
- [ ] 1.1 Decide canonical watch URL format and language handling
- [ ] 1.2 Implement build-time generator that fetches video list per language and emits static watch HTML
- [ ] 1.2.1 Support optional `VIDEO_ID` env var to generate a single video for local testing (non-committed)
- [ ] 1.3 Add metadata rendering (title/description/keywords/OpenGraph/JSON-LD) in static HTML
- [ ] 1.4 Generate `sitemap.xml` (and `robots.txt` if needed)
- [ ] 1.5 Apply `lang` from path to i18n on app boot and update link builders
- [ ] 1.6 Add client-side routing fallbacks/redirects for legacy watch URLs
- [ ] 1.7 Document daily rebuild process in project docs
