# Change: Store auth token in localStorage only
Version: 1.11.2

## Why
The app currently splits auth storage between `sessionStorage` and `localStorage` based on the Remember Me flag. This causes inconsistent token availability across tabs and adds complexity when reading and clearing auth state. The desired behavior is to always persist the token in `localStorage` so new tabs can read it, regardless of Remember Me.

## What Changes
- Persist `tokenNew` and `tokenExpiration` in `localStorage` after any successful login, regardless of Remember Me selection.
- Simplify auth storage helpers to use `localStorage` only (no token reads from `sessionStorage`).
- Align user cache storage with the new single-store model to avoid split logic.
- Ensure logout removes auth-related keys from `localStorage`.
- Keep the Remember Me checkbox as a UI preference only (no storage routing).

## Impact
- Affected specs: store-auth-token
- Affected code:
  - src/utils/auth.ts
  - src/hooks/auth/useAuth.ts
  - src/components/sign-in.tsx
  - src/pages/AuthLogin.tsx
  - src/components/popup-banner.tsx (token change events)
  - src/lib/axios.ts (token access via helper)
