# Network Advantage Engine (Routing, Cost, and Aggregation)

This document defines the implementation approach for making Blackstar's federated network advantage visible at claim time and checkout time.

## 1) Direct mesh routing intelligence (OSRM-backed)

### Goal
Prioritize direct, intra-cluster relay paths over hub-and-spoke defaults by quantifying a **local advantage** score.

### Inputs
- Shipment origin/destination geocoordinates (or geocoded addresses).
- Active node graph (node location, transport class, capacity, service window, reputation).
- OSRM route matrix/trip outputs for candidate legs.
- Carrier benchmark estimator (`UPS Ground`, `FedEx Ground`, optional `USPS`) based on zone, distance, and day-of-week cutoffs.

### Engine output
For each listing, produce:
- `mesh_eta_minutes`
- `carrier_eta_minutes`
- `local_advantage_minutes = carrier_eta - mesh_eta`
- `local_advantage_ratio = carrier_eta / max(mesh_eta, 1)`
- `local_advantage_claim_priority` (`high|medium|none`)

### Claim-priority policy
- `high`: same metro/cluster and `local_advantage_minutes >= 120`
- `medium`: same region and `local_advantage_minutes >= 30`
- `none`: otherwise

These values are advisory and MUST NOT violate the no-central-dispatch invariant; nodes still claim voluntarily.

## 2) Batched demand aggregation per node ("milk runs")

### Goal
Convert independent listings into route-window batches for the same node corridor.

### Implementation
- Add per-node rolling windows (e.g., 15/30/60 minutes) aligned with order-cycle semantics.
- Cluster open listings by:
  - geohash proximity,
  - destination bearing corridor,
  - compatible transport constraints,
  - promised delivery SLA.
- Compute `batch_marginal_cost` and `batch_eta_delta` before a node confirms departure.

### Output signals
- `batch_candidate_id`
- `batch_stop_count`
- `estimated_cost_per_shipment`
- `estimated_departure_window`

## 3) Multi-modal transport class stacking

### Goal
Allow multi-leg route composition with best-fit node per leg.

### Implementation
- Build a leg-composition solver constrained by transport class fields:
  - `category`, `subtype`, weight/volume/range limits, hazard flags, regulatory class.
- Generate feasible chain options (example: bike -> van -> bike).
- Price and time each chain using OSRM-derived durations + handoff penalties.

### Checkout display
- Show side-by-side comparison:
  - `relay_path` with leg summary,
  - `relay_total_price`, `relay_total_eta`,
  - `carrier_reference_price`, `carrier_reference_eta`.

## 4) Directional-utilization signaling ("already going that way")

### Goal
Expose near-zero-marginal-cost opportunities from node-declared intent.

### Implementation
- Add node availability postings with:
  - corridor polyline or origin/destination pair,
  - departure window,
  - spare capacity by transport class.
- Rank eligible listings higher when listing geometry overlaps active corridor intent.

## 5) Community micro-depot relay points

### Goal
Use trusted community locations as low-cost handoff infrastructure.

### Implementation
- Model depots as relay-capable nodes with:
  - trust attributes,
  - operating windows,
  - `catchment_radius_meters`.
- Route builder injects nearest feasible depot when direct handoff is not practical.

## 6) Predictive demand pre-positioning (marketplace webhook driven)

### Goal
Shift from reactive dispatch to proactive capacity staging.

### Implementation
- Consume `order.created` and `delivery.option.selected` streams.
- Maintain rolling demand forecasts per metro and transport class.
- Trigger node alerts when projected demand exceeds current claim capacity.

## 7) Transparent reverse-auction claim market

### Goal
Make node pricing explicit and competitive.

### Implementation
- Listings with bid policy expose `max_willingness_to_pay` and expiry.
- Nodes place decrementing bids with SLA commitments.
- Tie-breakers: reliability score, on-time rate, hazard eligibility, insurance capability.

## 8) Reputation-gated premium lanes

### Goal
Increase quality without centralized compliance overhead.

### Implementation
- Build eligibility tiers from attestation + performance metrics.
- Premium/fragile/time-sensitive listings require threshold trust bands.
- Persist auditable governance references for tier-rule changes.

## 9) USPS last-mile injection option

### Goal
Use hybrid routing where USPS beats local node coverage economics (especially rural last-mile).

### Implementation
- Add transport capability flag `usps_injectable`.
- Optimizer can terminate a Blackstar relay leg at USPS handoff-in point.
- Display blended ETA/cost at checkout.

## 10) Structural software-cost advantage

### Goal
Operationalize low overhead from open Fleetbase tooling.

### Implementation
- Treat dispatch/routing/visibility features as protocol primitives, not paid enterprise add-ons.
- Track per-node software cost avoided as part of network economics reporting.

## 11) Sustainability differential

### Goal
Turn lower-emission routing into a measurable market signal.

### Implementation
- Estimate emissions per leg by vehicle type and distance.
- Publish shipment-level `estimated_co2e_kg` and `co2e_saved_vs_carrier`.
- Allow green-preference ranking in checkout selection.

## Rollout sequence (recommended)
1. **Routing intelligence core**: local-advantage scoring + carrier baseline estimator.
2. **Cost comparison engine**: relay chain pricing and checkout comparisons.
3. **Demand aggregation tooling**: node batch windows + directional intent + predictive staging.
4. **Market quality layer**: reverse auction, reputation tiers, sustainability metrics.

## Guardrails
- Preserve no-central-dispatch invariant: engine outputs are advisory and ranking-only.
- Keep vendor visibility policy boundaries intact (no private node coordination leakage).
- Version all estimator formulas and expose audit metadata for governance review.

## Execution work order
Use [Network Advantage Engine Work Order](./work-orders/network-advantage-work-order.md) for phased implementation tasks, acceptance criteria, and copy/paste implementation prompts.
