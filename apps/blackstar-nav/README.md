# Blackstar Nav (Baseline)

React Native driver app baseline adapters for Blackstar API connectivity and bid submission flow.

## What is included
- `src/config.js` for `BLACKSTAR_API_URL` and shared token key.
- `src/auth-store.js` for shared token handling contract.
- `src/api-client.js` with API connection helpers against the Blackstar API (`api/routes/api.php`):
  - `login(email, password, deviceName)` — `POST /api/auth/token` `{email, password, device_name}` → `{token, token_type: 'Bearer'}`; the token is kept in `auth-store` and sent as `Authorization: Bearer <token>`.
  - `listEligibleListings()` — `GET /api/shipment-board-listings/eligible` (open listings the caller's node is eligible for; `[]` when the user has no node).
  - `submitBid({ listingId, amount, currency?, note? })` — `POST /api/shipment-board-listings/{id}/bids`. Only `claim_policy: 'bid'` listings accept bids; the listing's poster awards one (`/award`), which the driver app does not do.
- `src/bid-flow.js` orchestration: list eligible listings, check the target is eligible and bid-policy, submit the bid.
- `scripts/smoke.js` dry-run smoke test for API wiring and bid flow.
- `test/api-client.test.js` unit tests (`node --test`, stubbed `fetch`) pinning the paths, headers and bodies above.

This is a Node adapter layer, not a React Native app: there is no UI, no persistent token storage (the token is in memory only), and no location broadcasting.

## Env
- `BLACKSTAR_API_URL` (required)
- `BLACKSTAR_AUTH_TOKEN_STORAGE_KEY` (default `blackstar.auth.token`)
- `BLACKSTAR_DRY_RUN=1` (default in smoke script)
- `BLACKSTAR_SAMPLE_LISTING_ID`, `BLACKSTAR_SAMPLE_BID_AMOUNT`, `BLACKSTAR_SAMPLE_BID_CURRENCY` (smoke script sample bid)

## Commands
```bash
npm test
npm run smoke
npm run bid:smoke
```

## Note
Upstream Navigator fork fetch remains blocked in this environment (`CONNECT tunnel failed, response 403`), so this baseline provides a working local implementation layer until mirror/source access is available.
