## ADDED Requirements

### Requirement: Language-scoped video URLs with keyword slugs
The system SHALL provide language-scoped watch URLs in the form `/{lang}/watch/{id}/{mash}` where `mash` is the video code slug.

#### Scenario: Language URL resolves to a video
- **WHEN** a user visits `/{lang}/watch/{id}/{mash}`
- **THEN** the VideoPlayer page renders the video identified by `{id}` and remains on the language-scoped URL

#### Scenario: Legacy route remains usable
- **WHEN** a user visits `/watch/{id}`
- **THEN** the system renders the video and provides a canonical URL pointing to `/{lang}/watch/{id}/{mash}`

### Requirement: Canonical SEO metadata uses the language URL
The system SHALL use the language-scoped URL as the canonical URL and include the `mash` code in page metadata when available.

#### Scenario: Video has a mash code
- **WHEN** the video record includes a `mash` code
- **THEN** the canonical URL uses `/{lang}/watch/{id}/{mash}` and metadata includes the code

#### Scenario: Mash code missing
- **WHEN** the video record does not include `mash`
- **THEN** the system falls back to `/{lang}/watch/{id}` for canonical URL and standard metadata

### Requirement: hreflang alternates and sitemap coverage
The system SHALL emit `hreflang` alternate links for `en` and `zh` and include both language URLs in the sitemap.

#### Scenario: Video page renders with language variants
- **WHEN** a video page is rendered for `en` or `zh`
- **THEN** the HTML includes `hreflang` alternates for both languages and the sitemap contains both URLs

### Requirement: Watch pages are server-rendered for crawlers
The system SHALL render watch pages on the server with full metadata and JSON-LD so crawlers receive indexable HTML.

#### Scenario: Crawler requests a watch page
- **WHEN** a crawler requests `/{lang}/watch/{id}/{mash}`
- **THEN** the server responds with HTML containing title, meta description, OpenGraph tags, and JSON-LD for the video

### Requirement: Internal links prefer language URLs
The system SHALL generate internal links to video pages using `/{lang}/watch/{id}/{mash}` when a mash code is available.

#### Scenario: Video list renders cards
- **WHEN** a video card/link is rendered and the video has a `mash` code
- **THEN** the link points to `/{lang}/watch/{id}/{mash}`
