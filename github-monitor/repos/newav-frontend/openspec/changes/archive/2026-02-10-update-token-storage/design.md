## Context
The current auth utilities choose between `sessionStorage` and `localStorage` based on a Remember Me flag. This results in token availability differing across tabs and adds branches across helpers (token, expiration, and user cache). The requested behavior is to always persist the auth token in `localStorage`.

## Goals / Non-Goals
- Goals:
  - Always store and read auth tokens from `localStorage`.
  - Preserve cross-tab authentication without user action.
  - Simplify token and user cache storage logic to one storage backend.
- Non-Goals:
  - Changing backend auth behavior or token format.
  - Implementing additional encryption or storage hardening.

## Decisions
- Decision: Use `localStorage` exclusively for `tokenNew`, `tokenExpiration`, and `userInfoCache`.
- Decision: Keep Remember Me as a UI preference only; it no longer determines storage backend.
- Decision: Ensure logout removes all auth-related `localStorage` keys.

## Risks / Trade-offs
- Risk: Persisting tokens in `localStorage` increases exposure if a shared device is used. Mitigation: token expiration remains enforced and logout clears persisted keys.

## Migration Plan
- Accept a one-time re-login for users who were authenticated only via `sessionStorage`. No migration from `sessionStorage` to `localStorage` will be performed.
