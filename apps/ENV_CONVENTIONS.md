# Blackstar Workspace Environment + Auth Contract

This workspace-level contract applies to both `apps/blackstar-console` and `apps/blackstar-nav`.

## Required environment variables

- `BLACKSTAR_API_URL` — absolute API base URL (example: `https://staging-api.blackstar.example`).
- `BLACKSTAR_AUTH_TOKEN_STORAGE_KEY` — shared client-side token storage key.
  - default: `blackstar.auth.token`

## Derived compatibility variables

For legacy Fleetbase clients (console baseline), derive from `BLACKSTAR_API_URL`:

- `API_HOST=<BLACKSTAR_API_URL>`
- `API_SECURE=true|false` (based on URL scheme)

## Shared token handling contract

- Access tokens are bearer JWT/opaque strings returned by API auth flow.
- Client storage key: `BLACKSTAR_AUTH_TOKEN_STORAGE_KEY`.
- Token MUST be attached as `Authorization: Bearer <token>` for authenticated requests.
- Logout MUST clear local token value and in-memory session state.
