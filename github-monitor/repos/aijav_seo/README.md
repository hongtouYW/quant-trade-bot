# AI JAV — SSR Version

Server-side rendered version of the AI JAV frontend, built for SEO. Crawlers receive fully rendered HTML with meta tags, Open Graph data, and JSON-LD structured data.

## Tech Stack

- React 19 + TypeScript
- React Router v7 (Framework Mode, SSR enabled)
- Vite + Tailwind CSS v4
- Node.js SSR server via `react-router-serve`

## SSR-Enabled Pages

| Page | URL Pattern | SEO Data |
|------|------------|----------|
| Homepage | `/` | Title, description, OG tags, JSON-LD WebSite |
| Video Player | `/watch/:id`, `/:lang/watch/:id/:slug` | Title, description, OG tags, JSON-LD VideoObject + BreadcrumbList |
| Actress | `/actress/:id` | Title, description, OG tags, JSON-LD Person |
| Publisher | `/publisher/:id` | Title, description, OG tags, JSON-LD Organization |

All other pages run as a standard SPA via the catchall route.

## Requirements

- Node.js 18+

## Development

```bash
npm ci
npm run dev
```

## Build & Deploy

```bash
# Build (generate route types → type-check → client bundle → server bundle)
npm run build

# Start the SSR server (default port: 3000)
PORT=3000 node build/server/index.js

# Or use the preview script
npm run preview
```

**Note:** The build process now generates React Router route types before TypeScript type-checking to resolve type dependencies and prevent implicit any errors in generated route types.

### With PM2

```bash
PORT=3000 pm2 start build/server/index.js --name aijav-seo
```

### Update

```bash
git pull
npm ci
npm run build
pm2 restart aijav-seo
```

## Adding a New SSR Page

1. Create a route module in `src/routes/` with `meta` export (and optional `loader` for dynamic data)
2. Register it in `src/routes.ts`
3. Rebuild and restart the server

## Project Structure (SSR-specific files)

```
react-router.config.ts    # SSR config (ssr: true)
src/
├── root.tsx              # HTML shell, providers, default meta tags
├── entry.client.tsx      # Client hydration entry
├── entry.server.tsx      # Server rendering entry (streaming)
├── routes.ts             # Route definitions
├── routes/
│   ├── home.tsx          # Homepage route (meta)
│   ├── video-player.tsx  # Video route (loader + meta)
│   ├── actress-info.tsx  # Actress route (loader + meta)
│   └── publisher-info.tsx # Publisher route (loader + meta)
├── catchall.tsx          # SPA fallback for non-SSR routes
└── ...                   # Existing app code
```
