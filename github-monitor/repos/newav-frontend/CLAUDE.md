<!-- OPENSPEC:START -->
# OpenSpec Instructions

These instructions are for AI assistants working in this project.

Always open `@/openspec/AGENTS.md` when the request:
- Mentions planning or proposals (words like proposal, spec, change, plan)
- Introduces new capabilities, breaking changes, architecture shifts, or big performance/security work
- Sounds ambiguous and you need the authoritative spec before coding

Use `@/openspec/AGENTS.md` to learn:
- How to create and apply change proposals
- Spec format and conventions
- Project structure and guidelines

Keep this managed block so 'openspec update' can refresh the instructions.

<!-- OPENSPEC:END -->

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Context
InsAV is a video streaming application built with React, TypeScript, Vite, and Tailwind CSS. It features a Chinese language interface for browsing videos, actors, publishers, and managing user collections.

## Development Commands

### Essential Commands
- `npm run dev` - Start development server (Vite)
- `npm run build` - Build for production (TypeScript check + Vite build)
- `npm run lint` - Run ESLint for code quality
- `npm run preview` - Preview production build

### TypeScript Checking
- `npx tsc --noEmit` - Type check without building
- `npx tsc -b` - Build TypeScript only

## Architecture Overview

### Core Tech Stack
- **Frontend**: React 19 + TypeScript + Vite
- **Styling**: Tailwind CSS v4 + Radix UI components
- **State Management**: React Query (TanStack Query) for server state
- **Routing**: React Router v7
- **Video Player**: Vidstack with HLS.js support
- **Internationalization**: i18next with browser language detection
- **UI Components**: Custom components built on Radix UI primitives

### Project Structure
```
src/
├── components/          # Reusable UI components
│   ├── ui/             # Base UI components (Radix-based)
│   ├── skeletons/      # Loading state components
│   ├── empty-states/   # Empty state components
│   └── error-states/   # Error handling components
├── pages/              # Route components
├── hooks/              # Custom hooks organized by domain
│   ├── auth/          # Authentication hooks
│   ├── video/         # Video-related hooks
│   ├── actor/         # Actor/actress hooks
│   └── publisher/     # Publisher hooks
├── services/           # API service functions
├── types/              # TypeScript type definitions
├── contexts/           # React contexts
├── layouts/            # Layout components
├── i18n/              # Internationalization files
└── lib/               # Utility libraries
```

### Key Architectural Patterns

#### Data Fetching Strategy
- React Query for all API interactions with automatic caching
- Custom hooks pattern: each domain has dedicated hooks (e.g., `useVideoInfo`, `useActorList`)
- Optimistic updates for user interactions (collect, subscribe)
- Background refetching disabled (`refetchOnWindowFocus: false`)

#### Authentication System
- Token stored in `localStorage` as "tokenNew"
- Reactive authentication with `useAuth` hook
- Custom events for cross-component auth state synchronization
- Automatic token expiration handling with logout
- API interceptors add language and auth headers

#### Error Handling Architecture
- Business logic errors (API code !== 1) render custom error pages
- Component-level error boundaries with `PageError`, `InlineError`
- Graceful degradation with safe property access and fallbacks
- Specialized error components: `VideoNotFound` for missing content

#### Component Architecture
- Modular skeleton loaders for all major components
- Separate empty state components for different contexts
- Error states with retry functionality
- Mobile-first responsive design with Tailwind breakpoints

### Navigation & State Management

#### Navigation Context System
- Video player header shows navigation breadcrumbs
- Navigation state passed through React Router `state` prop
- Back button uses browser history (`navigate(-1)`)
- Context tracking: category name, source page (latest, search, etc.)

#### Route Structure
- Layout-based routing with `MainLayout` containing sidebar + navbar
- Dynamic routes: `/watch/:id`, `/actress/:id`, `/publisher/:id`
- Search results: `/search?keyword=...`
- User-specific routes: `/my-channels`, `/my-favorites`, `/watch-history`

### Video Player System

#### Vidstack Integration
- Full-featured video player with subtitle support
- Multi-language subtitle tracks (VTT format)
- Automatic language detection and fallback system
- Custom video authentication overlays
- Mobile-responsive controls

#### Video Authentication Flow
- Video URLs require authentication
- Custom overlay system for unauthenticated users
- Error handling for different permission levels (VIP, credits, restrictions)
- Automatic URL refetching on authentication state changes

#### Video Access Control
The `private` field in video objects determines access requirements:
- `private === 0`: Free content (no authentication required)
- `private === 1`: Requires login (basic authentication)
- `private === 2`: VIP required (premium subscription)
- `private === 3`: Series content (special handling for episode collections)

## Design Guidelines

### Brand Identity
- Primary purple: `#EC67FF`
- Chinese language interface (zh, en, ru support)
- Video-focused design with preview thumbnails
- Clean, modern UI with rounded corners and subtle shadows

### Loading States
- **Primary Choice**: Skeleton loaders over spinners
- Skeleton components mirror actual content structure
- Stakeholder-friendly: easily toggleable to simple loading text
- Examples: `VideoPlayerSkeleton`, `LatestVideoListSkeleton`

### Mobile Responsiveness
- Tailwind breakpoint system: `xs`, `sm`, `md`, `lg`, `xl`
- Sticky header on video player for mobile navigation
- Hidden scrollbars with maintained scroll functionality
- Touch-optimized video controls and navigation

### Component Patterns
- Consistent error handling with retry buttons
- Empty states with contextual icons and messages
- Collapsible sections (comments, video lists)
- Pagination with "load more" patterns for infinite lists

## Key Implementation Details

### API Integration
- Base URL: `https://newavapi.9xyrp3kg4b86.com/`
- Language header automatically added to all requests
- Token-based authentication with automatic retry on failure
- Encryption utilities for sensitive data handling

### State Synchronization
- Cross-tab authentication state sync via localStorage events
- Custom events for same-tab state updates
- React Query cache invalidation on auth changes
- Optimistic UI updates with rollback on error

### Performance Optimizations
- Lazy loading for video thumbnails and avatars
- Keen Slider for smooth horizontal scrolling lists
- Image optimization with loading states
- Component-level code splitting for large pages

### Internationalization
- i18next with React integration
- Automatic browser language detection
- Fallback system for missing translations
- Dynamic subtitle language selection based on app language

## Development Guidelines

### API Integration Protocol
When integrating new endpoints, always request the following information:
1. **Request payload structure** - Expected input parameters and their types
2. **Response payload structure** - Complete response format including nested objects
3. **Error response formats** - Different error codes and their corresponding structures
4. **Authentication requirements** - Whether the endpoint requires authentication

This information is essential for:
- Creating/updating TypeScript interfaces in `src/types/`
- Building proper service functions in `src/services/`
- Developing appropriate React Query hooks in `src/hooks/`
- Implementing correct error handling patterns

Example workflow:
1. Get endpoint documentation with request/response examples
2. Create/update TypeScript interfaces
3. Implement service function with proper typing
4. Create custom hook with error handling
5. Integrate into components with loading and error states

#### Complete Implementation Example: `/video/myPlayLog`

**Step 1: API Documentation**
- Endpoint: `POST {{BASE_URL}}/video/myPlayLog`
- No payload required
- Response: Standard API format with PaginatedData<PlayLogVideo>

**Step 2: TypeScript Interfaces (`src/types/video.types.ts`)**
```typescript
export interface PlayLogVideo extends Video {
  video_point: string;
}
```

**Step 3: Service Function (`src/services/video.service.ts`)**
```typescript
import type { PlayLogVideo } from "@/types/video.types.ts";

export const fetchPlayLog = async (): Promise<ApiResponse<PaginatedData<PlayLogVideo>>> => {
  const response = await axios.post("/video/myPlayLog");
  return response.data;
};
```

**Step 4: Custom Hook (`src/hooks/video/usePlayLog.ts`)**
```typescript
import { useQuery } from "@tanstack/react-query";
import { fetchPlayLog } from "@/services/video.service";

export const usePlayLog = () => {
  return useQuery({
    queryKey: ["playLog"],
    queryFn: fetchPlayLog,
    refetchOnWindowFocus: false,
  });
};
```

**Step 5: Component Integration**
```typescript
const { data, isPending, isError } = usePlayLog();
const watchHistory = data?.data?.data || [];
```

## Version Management

### Current Version
The project follows semantic versioning (MAJOR.MINOR.PATCH) starting from version 1.0.0.

### Version Update Guidelines
**IMPORTANT**: Update the version in `package.json` before each commit according to semantic versioning:

- **PATCH** (1.0.1): Bug fixes, small UI tweaks, text changes
- **MINOR** (1.1.0): New features, new components, significant enhancements
- **MAJOR** (2.0.0): Breaking changes, major refactoring, architecture changes

### Version Display
The current version is automatically displayed in the sidebar settings area for tracking purposes.

### Claude Instructions
When making commits, always:
1. Update the version number in `package.json` according to the change type
2. The version will be automatically displayed in the UI