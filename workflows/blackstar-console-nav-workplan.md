# Blackstar Console + Nav Implementation Workplan

This plan operationalizes the requested backlog for:
- **BLACKSTAR CONSOLE** (`apps/blackstar-console`) — Ember.js admin
- **BLACKSTAR NAV** (`apps/blackstar-nav`) — React Native driver app

## 1) Prioritized execution sequence

### Phase A — Monorepo foundation (P0 blockers)
1. Create `apps/blackstar-console` and copy `console/` baseline.
2. Create `apps/blackstar-nav` and copy `blackstar_nav` baseline (or current Navigator fork source).
3. Add workspace-level env conventions:
   - `BLACKSTAR_API_URL` for both apps
   - shared auth/token handling contract
4. Smoke-test both apps boot against staging API.

### Phase B — P0 product-critical flows
1. Console: Node registration UI
2. Console: Dispatch dashboard
3. Nav: Update API connections to `BLACKSTAR_API_URL`
4. Nav: Bid on delivery requests
5. Nav: Real-time location broadcasting

### Phase C — P1 operational maturity
1. Console: Route visualization
2. Console: Driver management
3. Console: Settlement dashboard
4. Nav: Batch route view
5. Nav: Delivery confirmation flow
6. Nav: Earnings dashboard

### Phase D — P2 expansion
1. Console: Relay point management
2. Console: Federation map
3. Console: Analytics dashboard
4. Nav: Relay handoff UI
5. Nav: Blackout chat integration (optional)
6. Nav: Offline mode

## 2) Backlog tracker

| Track | Task | Description | Dependencies | Priority | Status | AI Prompt | Est. Hours |
|---|---|---|---|---|---|---|---:|
| Console | Copy `console` into `apps/blackstar-console/` | Ember.js admin dashboard for node operators; update API endpoint configs | Monorepo restructure | P0 | SUPERSEDED (the copy was folded back into `console/`; `apps/blackstar-console` no longer exists) | No | 1 |
| Console | Node registration UI | Register new logistics node with map polygon + vehicle + availability | Nodes API | P0 | PARTIAL (2026-09-25: form POSTs `/api/nodes` with the API's fields after a Blackstar operator sign-in and shows validation errors; no map capture — the API stores a point + radius, not a polygon; no vehicle/availability fields exist in the API; attestation step not in the UI; never executed) | Yes | 4 |
| Console | Dispatch dashboard | Real-time active orders/bids/assigned drivers with map + filters | Dispatch API | P0 | PARTIAL (2026-09-25: read-only table of eligible open listings from `GET /api/shipment-board-listings/eligible`, filter by claim policy; no bids, drivers, real-time updates or map; never executed) | Yes | 6 |
| Console | Route visualization | Optimized batch routes, color-coded by driver, stop sequence + ETA | Route optimization API | P1 | NOT STARTED | Yes | 4 |
| Console | Driver management | Driver list/status, route assignment, performance stats | Driver API | P1 | NOT STARTED | Yes | 4 |
| Console | Settlement dashboard | Per-delivery payouts, date filters, CSV export, fee/pay/revenue split | Settlement API | P1 | NOT STARTED | Yes | 3 |
| Console | Relay point management | CRUD micro-depots with map picker, capacity/hours/status | Micro-depot API | P2 | NOT STARTED | Yes | 3 |
| Console | Federation map | Node/service-area polygons + relay points + coverage gaps | Inter-node discovery API | P2 | NOT STARTED | Yes | 4 |
| Console | Analytics dashboard | Deliveries/day, cost/delivery, delivery time, utilization, node comparison | Analytics API | P2 | NOT STARTED | Yes | 4 |
| Nav | Copy `blackstar_nav` into `apps/blackstar-nav/` | Fleetbase Navigator fork React Native app with tracking/orders/navigation | Monorepo restructure | P0 | PARTIAL (Node.js adapter modules only — no React Native app; the Navigator fork was never imported) | No | 1 |
| Nav | Update API connections to blackstar-api | Point SDK calls to `BLACKSTAR_API_URL`, validate auth/login/order listing | blackstar-api standalone | P0 | PARTIAL (2026-09-25: client calls `POST /api/auth/token` and `GET /api/shipment-board-listings/eligible`; unit-tested with stubbed `fetch`, never run against a live API) | Yes | 3 |
| Nav | Bid on delivery requests | Show nearby requests + details, submit bid (price + ETA) | Dispatch claims API | P0 | PARTIAL (2026-09-25: bid submission to `POST /api/shipment-board-listings/{id}/bids` with `amount`/`currency`/`note` — the API has no ETA field; no UI) | Yes | 4 |
| Nav | Batch route view | Multi-stop assignment map, stop-by-stop navigation, photo proof | Route optimization | P1 | NOT STARTED | Yes | 6 |
| Nav | Real-time location broadcasting | SocketCluster location every 5s with battery-aware background tracking | Driver assignment | P0 | NOT STARTED | No | 3 |
| Nav | Delivery confirmation flow | QR/code verify, photo POD, mark delivered, auto-advance | Delivery confirmation API | P1 | NOT STARTED | Yes | 3 |
| Nav | Relay handoff UI | Driver-to-driver handoff confirmations + chain-of-custody | Relay handoff protocol | P2 | NOT STARTED | Yes | 4 |
| Nav | Earnings dashboard | Per-delivery earnings + day/week/month totals + settlement history | Settlement API | P1 | NOT STARTED | Yes | 3 |
| Nav | Blackout chat integration (optional) | Use `BLACKOUT_URL` if available, else in-app fallback messaging | Blackout bridge | P2 | NOT STARTED | Yes | 4 |
| Nav | Offline mode | Cache route/stops, offline confirmation, sync on reconnect | Batch route view | P2 | NOT STARTED | Yes | 4 |

## 3) Immediate next sprint (recommended)

### Sprint 1 objective (2 weeks)
Deliver end-to-end skeleton across both apps with one complete dispatch loop.

#### Commit targets
1. `apps/blackstar-console` scaffold + env wiring.
2. `apps/blackstar-nav` scaffold + env wiring.
3. Console Node registration UI (form + map polygon capture + API integration).
4. Nav API connection update + login/order listing sanity pass.
5. Nav bid submission flow.
6. Console dispatch dashboard initial list/map view.

#### Exit criteria
- Both apps boot in CI and local dev with documented setup.
- One dispatch request can be created/seen/bid/assigned across Console + Nav test path.
- Basic telemetry/logging for the new flows is present.

## 4) Risk notes
- Mapping stack consistency (Ember map libs vs RN map libs) should be decided before polygon + route features.
- Real-time contracts (SocketCluster events) should be versioned to avoid breaking current API consumers.
- Offline mode should reuse a single queue/retry policy shared with delivery confirmation to avoid duplicate logic.


## 5) Phase A progress update

- ✅ `apps/blackstar-console` created by copying `console/` baseline.
- ⚠️ Upstream `blackstar_nav` sync remains blocked in this environment (`CONNECT tunnel failed, response 403`); local baseline implementation is now in place under `apps/blackstar-nav/src` and `apps/blackstar-nav/scripts`.
- ✅ Workspace env/auth conventions added at `apps/ENV_CONVENTIONS.md` with `BLACKSTAR_API_URL` and shared token storage key contract.
- ✅ Smoke test script added: `scripts/smoke-test-blackstar-apps.sh`.

## 6) Status correction (2026-09-25)

The earlier COMPLETE marks described scaffolds: the console pages rendered
hard-coded data or a local payload preview, and the nav client called
`/int/v1/...` dispatch routes that do not exist in `api/routes/api.php`. The
tracker above now records what exists:

- Console pages live in `console/` (`console/dispatch`, `console/node-registration`)
  and call the Blackstar API at `config.API.host` (derived from
  `BLACKSTAR_API_URL`). They need a separate sign-in via `POST /api/auth/token`,
  because the console's Fleetbase session token is not accepted by the API's
  `operator` guard. Neither page has been built or run; the console's
  dependencies are not installed in the environment where they were written.
- `apps/blackstar-nav` is a Node adapter layer with unit tests (`npm test`); its
  smoke script is a dry run and has never reached a live API.
- The Phase A progress notes in section 5 are historical: `apps/blackstar-console` was later
  folded into `console/`, and `scripts/smoke-test-blackstar-apps.sh` only checks
  config derivation plus the nav dry run.
