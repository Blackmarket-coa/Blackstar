# Blackstar Nav (Baseline)

React Native driver app baseline adapters for Blackstar API connectivity and bid submission flow.

## What is included
- `src/config.js` for `BLACKSTAR_API_URL` and shared token key.
- `src/auth-store.js` for shared token handling contract.
- `src/api-client.js` with API connection helpers (`login`, request listing, bid submit).
- `src/bid-flow.js` orchestration for request-list + bid submission path.
- `scripts/smoke.js` dry-run smoke test for API wiring and bid flow.

## Env
- `BLACKSTAR_API_URL` (required)
- `BLACKSTAR_AUTH_TOKEN_STORAGE_KEY` (default `blackstar.auth.token`)
- `BLACKSTAR_DRY_RUN=1` (default in smoke script)

## Commands
```bash
npm run smoke
npm run bid:smoke
```

## Note
Upstream Navigator fork fetch remains blocked in this environment (`CONNECT tunnel failed, response 403`), so this baseline provides a working local implementation layer until mirror/source access is available.
