# VideoCard Component

A reusable video card component for displaying video content in the InsAV application, maintaining the original design styling.

## Features

- ✅ **Reusable Component**: Extracted from inline code for reusability
- ✅ **Responsive**: Three size variants (small, medium, large)
- ✅ **Badge System**: Support for "New" and "VIP" badges based on data
- ✅ **Original Styling**: Preserves the existing design and layout
- ✅ **Hover Effects**: Maintains original hover animations
- ✅ **Accessibility**: Proper alt texts and semantic structure
- ✅ **TypeScript**: Full type safety with interfaces

## Usage

```tsx
import { VideoCard } from '@/components/ui/video-card';

// Basic usage
<VideoCard
  video={{
    id: 1,
    title: "SONE-719 正經的學生直接拉開水手服露...",
    thumb: "/path/to/thumbnail.jpg",
    actor: { name: "糸井瑠花" },
    play: 7.5,
    private: 0  // 0 for free/new, non-0 for VIP
  }}
/>

// Different sizes
<VideoCard video={videoData} size="small" />   // 150px width
<VideoCard video={videoData} size="medium" />  // 180px width
<VideoCard video={videoData} size="large" />   // 220px width

// Custom link prefix
<VideoCard 
  video={videoData} 
  linkPrefix="/videos"  // Links to /videos/{id}
/>

// Disable badges
<VideoCard 
  video={videoData} 
  showBadges={false} 
/>
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `video` | `VideoCardData` | required | Video data object |
| `size` | `'small' \| 'medium' \| 'large'` | `'small'` | Card size variant |
| `showBadges` | `boolean` | `true` | Show/hide badge system |
| `className` | `string` | - | Additional CSS classes |
| `linkPrefix` | `string` | `'/watch'` | URL prefix for video links |

## VideoCardData Interface

```tsx
interface VideoCardData {
  id: number | string;           // Unique video identifier
  title: string;                // Video title
  thumb: string;                // Thumbnail image URL
  actor?: {                     // Actor information
    name?: string;
  };
  play?: number | string;       // Rating/play count
  private?: number;             // 0 = new/free, non-0 = VIP
}
```

## Design Specifications

- **Image**: 150×221px (small), maintains aspect ratio
- **Border Radius**: 8px for rounded corners
- **Layout**: Content positioned below image (original design)
- **Typography**: Standard font weights and sizes
- **Colors**: 
  - Title: text-gray-800 (dark text)
  - Actor: text-muted-foreground
  - Rating: text-yellow-400
  - New Badge: bg-primary (purple)
  - VIP Badge: bg-secondary

## Examples in Project

Currently used in:
- `LatestVideoList` component for homepage latest videos
- Can be extended to other video listing components

## Responsive Behavior

- Hover effects with smooth transitions
- Scale animation on image hover
- Ring highlight on card hover
- Optimized for touch devices
