## 1. Implementation
- [x] 1.1 Update `src/utils/auth.ts` to persist auth tokens and user cache in `localStorage` only.
- [x] 1.2 Remove any `sessionStorage` reads for auth tokens and expiration; ensure logout clears `localStorage` keys.
- [x] 1.3 Update auth listeners in `src/hooks/auth/useAuth.ts` to rely on `localStorage`-backed helpers only.
- [x] 1.4 Update login flows (`src/components/sign-in.tsx`, `src/pages/AuthLogin.tsx`) to remove storage-branching assumptions.
- [x] 1.5 Validate cross-tab behavior and logout cleanup.
