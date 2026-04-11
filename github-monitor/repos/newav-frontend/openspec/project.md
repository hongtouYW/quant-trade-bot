# Project Context

## Purpose
[TODO: Describe your project's purpose and goals.]
[Current repo appears to be a frontend SPA built with React + Vite; no backend code is present here.]

## Tech Stack
- TypeScript + React (functional components, hooks)
- Vite (build/dev server)
- Tailwind CSS v4 (utility-first styling, CSS variables, dark mode via class)
- Radix UI primitives + custom components
- React Router (client-side routing)
- TanStack React Query + React Table (data fetching/cache + tables)
- i18next (localization)
- Axios (HTTP client)
- Zod (schema validation)
- ESLint + Prettier (linting/formatting)

## Project Conventions

### Code Style
- TypeScript-first; ES modules
- ESLint: `@eslint/js` recommended + `typescript-eslint` recommended, React Hooks rules, React Refresh warnings
- Prefer functional React components and hooks
- Tailwind classes for styling; design tokens via CSS variables

### Architecture Patterns
- SPA structure under `src/`
- `pages/` for route-level views, `components/` for reusable UI, `layouts/` for shared page scaffolding
- `contexts/` for app-level state, `hooks/` for shared logic
- `services/` for API/IO, `lib/` and `utils/` for shared helpers
- `i18n/` for localization setup

### Testing Strategy
- No test runner is configured in `package.json` yet
- [TODO: Specify testing tools and required coverage (e.g., Vitest + React Testing Library)]

### Git Workflow
[TODO: Describe branching strategy (e.g., trunk-based vs. GitFlow) and commit message conventions.]

## Domain Context
[TODO: Add domain-specific knowledge that AI assistants need to understand.]

## Important Constraints
- Frontend-only repository (no server code in this repo)
- Dark mode supported via class toggling (`darkMode: "class"` in Tailwind config)
- [TODO: Add business/regulatory constraints]

## External Dependencies
- Backend API: `https://newavapi.9xyrp3kg4b86.com/` via Axios instance in `src/lib/axios.ts`
  - Payloads are signed/encrypted before sending; responses may be decrypted client-side
  - Endpoints (POST), grouped by service:
    - Actor
      - /actor/lists: list actors (paged)
      - /actor/byPublisher: list actors by publisher
      - /actor/info: actor detail by `aid`
      - /actor/mySubscribe: list subscribed actors
      - /actor/subscribe: subscribe/unsubscribe actor
      - /actor/actorRanking: actor rankings (paged)
    - Banner
      - /banner/lists: banner list
    - Category
      - /category/lists: category list (paged)
    - Config
      - /config/lists: app config values
    - Group
      - /group/lists: group list (paged)
      - /group/details: group detail by `gid`
      - /group/myCollect: list collected groups
      - /group/collect: toggle group collection
      - /group/purchase: purchase group/series
      - /group/myPurchase: list purchased groups/series
    - Hotlist
      - /hotlist/lists: hotlist list (paged)
      - /hotlist/details: hotlist detail by `hid`
    - Index
      - /index/globalImage: global image payloads
      - /index/globalSearch: search across content by keyword
      - /index/globalVip: global VIP/plan metadata
    - Notice
      - /notice/lists: system notices
    - Publisher
      - /publisher/info: publisher detail by `pid`
      - /publisher/lists: publisher list (paged)
      - /publisher/mySubscribe: list subscribed publishers
      - /publisher/subscribe: subscribe/unsubscribe publisher
    - Rating (Reviews)
      - /rating/list: review list by payload
      - /rating/submit: submit review
      - /rating/like: like review
      - /rating/edit: edit review
      - /rating/delete: delete review
    - Tag
      - /tag/lists: tag list (paged)
      - /tag/homeList: categorized video list by tag
    - User
      - /user/login: login
      - /user/info: current user info
      - /user/register: register user
      - /user/quickRegister: quick registration
      - /user/redeemcode: redeem code
      - /user/redeem_record: redemption history
      - /user/feedback: submit feedback
    - Video
      - /video/indexLists: home/index video list (paged)
      - /video/lists: video list (paged)
      - /video/hotLists: hot video list (paged)
      - /video/info: video detail
      - /video/getVideoUrl: playback URL
      - /video/collect: toggle video collection
      - /video/myPlayLog: play history
      - /video/myCollect: collected videos
      - /video/purchase: purchase single video
      - /video/myPurchase: purchased videos
      - /video/userHasVideoAccess: access check for video
    - VIP/Payments
      - /vip/platforms: payment platforms
      - /vip/buy: purchase VIP package
      - /vip/myOrder: VIP order history
