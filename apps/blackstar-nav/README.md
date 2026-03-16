# Blackstar Nav (Scaffold)

This directory is reserved for the React Native driver app baseline (`blackstar_nav` / Navigator fork).

## Current status
- A direct upstream baseline sync was attempted from `https://github.com/fleetbase/navigator-app.git` but is blocked in this environment due to outbound proxy restrictions (`CONNECT tunnel failed, response 403`).
- To keep monorepo structure unblocked, this scaffold includes shared env/auth conventions and smoke-test hooks.

## Required env contract
- `BLACKSTAR_API_URL`
- `BLACKSTAR_AUTH_TOKEN_STORAGE_KEY` (default `blackstar.auth.token`)

See `../ENV_CONVENTIONS.md`.
