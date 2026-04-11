## ADDED Requirements
### Requirement: Static watch-page SEO HTML
The system SHALL generate static HTML for each video watch page at build time so crawlers can index metadata without executing JavaScript.

#### Scenario: Crawlable watch page
- **WHEN** a crawler requests a watch URL
- **THEN** the response includes static HTML with SEO metadata and a mount point for SPA hydration

### Requirement: Canonical, language-scoped watch URLs
The system SHALL expose canonical watch URLs that include a language path segment and publish canonical + hreflang links for supported languages.

#### Scenario: Canonical URL and alternates
- **WHEN** a watch page is generated
- **THEN** it includes a canonical URL with the language path segment for its language
- **AND** includes hreflang alternates for all supported languages

### Requirement: Language selection from URL
The system SHALL set the app language from the language path segment when present.

#### Scenario: Apply language from path
- **WHEN** a user loads a watch URL under `/zh/` or `/en/`
- **THEN** the UI language is set to the requested language before rendering content

### Requirement: Structured metadata
The system SHALL include title, description, keywords, OpenGraph tags, and JSON-LD on static watch pages.

#### Scenario: Metadata presence
- **WHEN** a watch page is generated
- **THEN** the HTML contains the required meta tags and JSON-LD block

### Requirement: Sitemap generation
The system SHALL generate a sitemap that lists all watch URLs and is refreshed at least daily.

#### Scenario: Daily sitemap refresh
- **WHEN** the sitemap is generated
- **THEN** it includes all current watch URLs for supported languages

### Requirement: SPA hydration and playback
The system SHALL hydrate the SPA on load and preserve normal video playback behavior after the static HTML is served.

#### Scenario: Normal SPA behavior
- **WHEN** a user loads a static watch page
- **THEN** the SPA hydrates and the video player functions as usual

### Requirement: Legacy watch URL compatibility
The system SHALL handle legacy watch URLs by client-side navigation to the canonical language-scoped URL.

#### Scenario: Legacy URL resolution
- **WHEN** a user requests a legacy watch URL
- **THEN** the client navigates to the canonical watch URL
