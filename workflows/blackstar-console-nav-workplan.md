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
| Console | Copy `console` into `apps/blackstar-console/` | Ember.js admin dashboard for node operators; update API endpoint configs | Monorepo restructure | P0 | COMPLETE | No | 1 |
| Console | Node registration UI | Register new logistics node with map polygon + vehicle + availability | Nodes API | P0 | COMPLETE (initial form + payload preview scaffold) | Yes | 4 |
| Console | Dispatch dashboard | Real-time active orders/bids/assigned drivers with map + filters | Dispatch API | P0 | COMPLETE (initial table/filter/map placeholder scaffold) | Yes | 6 |
| Console | Route visualization | Optimized batch routes, color-coded by driver, stop sequence + ETA | Route optimization API | P1 | NOT STARTED | Yes | 4 |
| Console | Driver management | Driver list/status, route assignment, performance stats | Driver API | P1 | NOT STARTED | Yes | 4 |
| Console | Settlement dashboard | Per-delivery payouts, date filters, CSV export, fee/pay/revenue split | Settlement API | P1 | NOT STARTED | Yes | 3 |
| Console | Relay point management | CRUD micro-depots with map picker, capacity/hours/status | Micro-depot API | P2 | NOT STARTED | Yes | 3 |
| Console | Federation map | Node/service-area polygons + relay points + coverage gaps | Inter-node discovery API | P2 | NOT STARTED | Yes | 4 |
| Console | Analytics dashboard | Deliveries/day, cost/delivery, delivery time, utilization, node comparison | Analytics API | P2 | NOT STARTED | Yes | 4 |
| Nav | Copy `blackstar_nav` into `apps/blackstar-nav/` | Fleetbase Navigator fork React Native app with tracking/orders/navigation | Monorepo restructure | P0 | COMPLETE (local baseline created; upstream sync still blocked by network) | No | 1 |
| Nav | Update API connections to blackstar-api | Point SDK calls to `BLACKSTAR_API_URL`, validate auth/login/order listing | blackstar-api standalone | P0 | COMPLETE (baseline API client wired to `BLACKSTAR_API_URL`) | Yes | 3 |
| Nav | Bid on delivery requests | Show nearby requests + details, submit bid (price + ETA) | Dispatch claims API | P0 | COMPLETE (dry-run bid flow scaffold implemented) | Yes | 4 |
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
