import {
  type RouteConfig,
  route,
  index,
  layout,
} from "@react-router/dev/routes";

export default [
  // Sitemap routes — no layout, return raw XML responses
  route("sitemap.xml", "./routes/sitemap[.]xml.tsx"),
  route("sitemap-static.xml", "./routes/sitemap-static[.]xml.tsx"),
  route("sitemap-videos/:page.xml", "./routes/sitemap-videos-$page[.]xml.tsx"),
  route("sitemap-actresses.xml", "./routes/sitemap-actresses[.]xml.tsx"),
  route("sitemap-publishers.xml", "./routes/sitemap-publishers[.]xml.tsx"),

  // Language-prefixed SSR routes (/zh/..., /en/...)
  // lang-layout validates :lang against SUPPORTED_LANGS and redirects
  // to DEFAULT_LANG if the segment isn't a recognized language.
  route(":lang", "./routes/lang-layout.tsx", [
    layout("./layouts/MainLayout.tsx", [
      index("./routes/home.tsx"),
      route("actress/:id", "./routes/actress-info.tsx"),
      route("publisher/:id", "./routes/publisher-info.tsx"),
      route("watch/:id/:slug", "./routes/video-player.tsx", {
        id: "video-player-lang-slug",
      }),
      route("watch/:id", "./routes/video-player.tsx", {
        id: "video-player-lang",
      }),
    ]),
  ]),

  // Unprefixed SSR paths — 301 redirect to preferred lang (cookie or default).
  // Only the SSR-capable paths get explicit redirects; other unprefixed paths
  // (like /latest, /categories, /search) fall through to the SPA catchall.
  index("./routes/lang-redirect.tsx", { id: "redirect-index" }),
  route("actress/:id", "./routes/lang-redirect.tsx", {
    id: "redirect-actress",
  }),
  route("publisher/:id", "./routes/lang-redirect.tsx", {
    id: "redirect-publisher",
  }),
  route("watch/:id/:slug", "./routes/lang-redirect.tsx", {
    id: "redirect-watch-slug",
  }),
  route("watch/:id", "./routes/lang-redirect.tsx", {
    id: "redirect-watch",
  }),

  // Catchall: hands everything else to the existing BrowserRouter-based App.
  // Covers /auth_login, /latest, /search, /my-favorites, and any other SPA
  // routes. Migrate routes out of here incrementally as SSR loaders are added.
  route("*?", "./catchall.tsx"),
] satisfies RouteConfig;
