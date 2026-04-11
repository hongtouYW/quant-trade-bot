# Global Image Usage Guide

The global image endpoint has been integrated and will be prefetched automatically when the app loads.

## Usage in Components

```tsx
import { useGlobalImageContext } from "@/contexts/GlobalImageContext";
import { useTranslation } from "react-i18next";

function MyComponent() {
  const { i18n } = useTranslation();
  const { getImageByKey, isLoading, isError } = useGlobalImageContext();

  // Get specific image by key
  const loginBg = getImageByKey("loginBg");

  // Get the image URL for current language
  const currentLanguage = i18n.language as keyof typeof loginBg.thumb;
  const imageUrl = loginBg?.thumb[currentLanguage] || loginBg?.thumb.en;

  if (isLoading) return <div>Loading...</div>;
  if (isError) return <div>Error loading images</div>;

  return (
    <div
      style={{
        backgroundImage: `url(${imageUrl})`
      }}
    >
      {/* Your content */}
    </div>
  );
}
```

## Available Image Keys

Based on the API response structure:
- `loginBg` - Login background image
- `registerBg` - Registration background image
- `forgotPasswordBg` - Forgot password background image
- `logoutBg` - Logout background image
- `vipBg` - VIP background image
- `voucherBg` - Voucher background image
- `diamondBg` - Diamond background image

## Direct Hook Usage

If you prefer to use the hook directly without the context:

```tsx
import { useGlobalImage } from "@/hooks/index/useGlobalImage";

function MyComponent() {
  const { data: globalImages, isPending, isError } = useGlobalImage();

  // Use globalImages array directly
  const loginBg = globalImages?.find(img => img.key === "loginBg");

  return (
    // Your component
  );
}
```

## Features

- ✅ Automatic prefetching on app startup
- ✅ TypeScript support with proper interfaces
- ✅ Multi-language support (en, zh, ru, ms, th, es)
- ✅ Context provider for easy access across components
- ✅ Helper function to get images by key
- ✅ **Automatic fallback to static assets** when global images are empty or unavailable
- ✅ Follows project patterns and conventions

## Fallback System

The implementation includes a robust fallback system using the `useGlobalImageWithFallback` hook:

```tsx
import { useGlobalImageWithFallback } from "@/hooks/useGlobalImageWithFallback";
import staticImage from "@/assets/my-static-image.png";

function MyComponent() {
  const dynamicImage = useGlobalImageWithFallback({
    imageKey: "loginBg",
    fallbackImage: staticImage
  });

  return <img src={dynamicImage} alt="Background" />;
}
```

**Fallback conditions:**
- Global image API is loading → uses static fallback
- Global image API returns error → uses static fallback
- Global image not found by key → uses static fallback
- Global image thumb object is empty → uses static fallback
- Global image URL is empty string → uses static fallback
- Language-specific image not available → tries 'en', then any available language, then fallback

## Implementation Status

**✅ Components Updated with Fallback:**
- `sign-in.tsx` - Uses `loginBg` with `sign-in-bg.png` fallback
- `sign-up.tsx` - Uses `registerBg` with `sign-up-bg.png` fallback
- `ForgotPasswordDialog.tsx` - Uses `forgotPasswordBg` with `forgot-password-bg.png` fallback
- `LogoutConfirmationDialog.tsx` - Uses `logoutBg` with `log-out-bg.png` fallback
- `Plans.tsx` - Uses `vipBg` and `diamondBg` with respective card fallbacks
- `VoucherDialog.tsx` - Uses `voucherBg` with `vip-card.png` fallback