# store-auth-token Specification

## Purpose
TBD - created by archiving change update-token-storage. Update Purpose after archive.
## Requirements
### Requirement: Persist auth token in localStorage
The system SHALL persist the auth token and token expiration in `localStorage` after successful login, regardless of Remember Me selection.

#### Scenario: Login success stores token in localStorage
- **WHEN** a user successfully logs in
- **THEN** `tokenNew` and `tokenExpiration` are stored in `localStorage`
- **AND** any session-scoped auth token entries are not used for subsequent reads

### Requirement: Read auth token from localStorage only
The system SHALL read the current auth token and expiration from `localStorage` only.

#### Scenario: New tab uses existing token
- **WHEN** a new tab loads and `localStorage` contains `tokenNew`
- **THEN** the app treats the user as authenticated without requiring re-login

### Requirement: Logout clears auth token data
The system SHALL remove auth-related keys from `localStorage` on logout.

#### Scenario: Logout clears persisted auth
- **WHEN** the user logs out
- **THEN** `tokenNew`, `tokenExpiration`, and `userInfoCache` are removed from `localStorage`

