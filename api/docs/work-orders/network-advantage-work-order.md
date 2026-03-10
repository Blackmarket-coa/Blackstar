# Work Order: Network Advantage Engine Implementation

## Objective
Implement routing intelligence, cost comparison, and demand aggregation capabilities that make Blackstar's federated mesh advantage visible at shipment claim time and buyer checkout, while preserving FLP's no-central-dispatch invariant.

## Scope
This work order operationalizes the strategy in:
- `api/docs/network-advantage-engine.md`
- `api/docs/specification.md` section "Network Advantage Engine Profile"

## Constraints and Guardrails
- Advisory-only optimization: no forced assignment and no mandatory dispatch routing.
- Maintain vendor visibility policy boundaries.
- Every estimator output must be versioned and auditable.
- New features should degrade gracefully when benchmark or map providers are unavailable.

## Delivery Plan (Phased)

### Phase 1 — Routing Intelligence Core
**Goal:** Produce `local_advantage` scoring from mesh ETA vs carrier baseline ETA.

#### Tasks
1. Define route-estimation service interfaces:
   - `MeshRouteEstimator` (OSRM-backed)
   - `CarrierBaselineEstimator` (UPS/FedEx/USPS reference curves)
2. Add data model fields for advisory ranking outputs:
   - `mesh_eta_minutes`, `carrier_eta_minutes`, `local_advantage_minutes`, `local_advantage_ratio`, `local_advantage_claim_priority`
3. Implement eligibility-safe scoring in shipment listing read paths.
4. Add feature flag for progressive rollout (`network_advantage_ranking`).

#### Deliverables
- Service classes and tests.
- API response fields for eligible listing feeds.
- Formula metadata attached to outputs.

#### Acceptance Criteria
- Same-metro listings can surface `high` priority when threshold is met.
- Listings still require explicit node claims.
- Tests cover missing OSRM/provider fallback behavior.

#### Implementation Prompt
"Implement Phase 1 of `api/docs/work-orders/network-advantage-work-order.md`: add route estimator interfaces, advisory local-advantage fields, and ranking logic for eligible shipment listings. Preserve FLP no-central-dispatch behavior and include unit/feature tests for thresholds and fallback behavior."

---

### Phase 2 — Cost Comparison Engine
**Goal:** Compare multi-leg relay price/ETA against carrier references at checkout.

#### Tasks
1. Build multi-leg composition logic across transport class constraints.
2. Add chain-level pricing and ETA computation with handoff penalties.
3. Expose checkout comparison object:
   - relay path summary
   - relay price + ETA
   - carrier reference price + ETA
4. Add API contract tests for comparison payload shape.

#### Deliverables
- `RelayChainEstimator` implementation.
- Checkout API payload extension.
- Conformance tests and docs.

#### Acceptance Criteria
- At least one feasible chain is returned for eligible multi-modal corridors.
- Comparison payload omits private node coordination internals.
- Carrier comparison has documented confidence/source fields.

#### Implementation Prompt
"Implement Phase 2 of `api/docs/work-orders/network-advantage-work-order.md`: create a relay chain estimator and expose relay-vs-carrier cost/ETA comparison in checkout-facing payloads. Enforce visibility policy boundaries and add contract tests."

---

### Phase 3 — Demand Aggregation Tooling
**Goal:** Increase per-run efficiency using node windows and corridor clustering.

#### Tasks
1. Add per-node batching windows (15/30/60 min configurable).
2. Implement listing clustering by proximity + heading + SLA.
3. Produce batch advisory signals:
   - `batch_candidate_id`, `batch_stop_count`, `estimated_cost_per_shipment`, `estimated_departure_window`
4. Add node directional-intent feed ("already going that way").

#### Deliverables
- Batch recommendation service.
- Node availability intent model/API.
- Feature tests for batch candidate generation.

#### Acceptance Criteria
- Multiple compatible listings can be grouped into same advisory batch.
- Batch signals are visible without auto-claiming any listing.
- Intent overlap improves ranking for matching listings.

#### Implementation Prompt
"Implement Phase 3 of `api/docs/work-orders/network-advantage-work-order.md`: add batch-window aggregation and directional intent signals for shipment-board ranking. Ensure the changes are advisory-only and covered by feature tests."

---

### Phase 4 — Market and Quality Layer
**Goal:** Add transparent pricing competition and quality gating.

#### Tasks
1. Extend bid flow with reverse-auction constraints (`max_willingness_to_pay`, expiry).
2. Add reputation/attestation tier checks for premium lanes.
3. Add USPS-injection option flagging and blended estimate support.
4. Add emissions estimation (`estimated_co2e_kg`, `co2e_saved_vs_carrier`).

#### Deliverables
- Bid market enhancements.
- Tiered eligibility policy module.
- Sustainability estimate output fields.

#### Acceptance Criteria
- Bid listings support downward competition with deterministic tie-breakers.
- Premium listing eligibility enforces trust thresholds.
- Emissions outputs appear with source/version metadata.

#### Implementation Prompt
"Implement Phase 4 of `api/docs/work-orders/network-advantage-work-order.md`: add reverse-auction enhancements, reputation-gated premium eligibility, USPS-injection estimate support, and emissions metrics with auditable metadata and tests."

---

## Cross-Cutting Task List
- [ ] Add migration plan and backfill strategy for new advisory fields.
- [ ] Add observability: latency, estimator confidence, provider error rates.
- [ ] Add governance/audit references for formula version changes.
- [ ] Update docs and API examples for vendors and nodes.
- [ ] Add staged rollout and rollback checklist.

## QA Checklist
- [ ] Unit tests for estimator formulas and threshold classification.
- [ ] Feature tests for listing and checkout payload contracts.
- [ ] Failure-mode tests for OSRM unavailability and stale benchmark data.
- [ ] Regression tests to confirm no auto-assignment/dispatch behavior.

## Definition of Done
- All acceptance criteria across phases are met.
- API contracts are documented and tested.
- Rollout flags and fallback behaviors are verified in staging.
- Governance/audit metadata is present for estimator logic versions.
