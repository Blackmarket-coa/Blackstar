# Federated Logistics Compatibility Blueprint

Canonical formal spec: [`api/docs/specification.md`](api/docs/specification.md). This blueprint remains the implementation and release-planning companion.

This document adapts the federated logistics migration plan for compatibility with:

- `free-black-market` (order creation, vendor checkout, order status UX)
- `Blackout_server` (governance rooms, encrypted inter-node coordination)

The objective is to evolve this Fleetbase-based codebase into a **multi-tenant federated logistics protocol** where independent nodes coordinate shipments without centralized dispatch.

---

## 1) Compatibility Objectives

### Protocol-level requirements

1. **All transport methods allowed** through transport classes and node-declared capabilities.
2. **Node autonomy**: each node controls acceptance, routing, and assignment.
3. **No centralized dispatch**: platform offers a shipment board, not command-and-control routing.
4. **Non-custodial payments**: buyer-vendor and vendor-node settlements happen directly.
5. **Governance via Blackout**: platform stores governance references and decisions, but does not govern operations itself.
6. **Vendor compatibility with FreeBlackMarket**: shipment lifecycle and visibility sync with marketplace orders.

### Target topology

```text
FreeBlackMarket (Demand + Orders)
      ↓
Federated Logistics Protocol (this repository)
      ↓
Independent Nodes (LLCs / co-ops / operators)
      ↓
Blackout (Governance + encrypted coordination)
```

---

## 2) Data Model Additions

Add or extend core entities so compatibility can be implemented without hidden centralization.

### `Node` (new first-class entity)

- `id` / `node_id` (UUID)
- `legal_entity_name`
- `jurisdiction`
- `service_radius_meters` (or geometry)
- `contact_email`, `contact_phone`
- `insurance_attestation_hash`
- `license_attestation_hash`
- `transport_capabilities` (array of `transport_class_id`)
- `governance_room_id` (Blackout room reference)
- `reputation_score` (derived value)
- `activation_status` (`pending_attestation`, `active`, `suspended`)

### `TransportClass` (new)

- `id`
- `category` (`land`, `air`, `water`, `autonomous`, `experimental`)
- `subtype` (e.g., `car`, `drone`, `boat`, `atv`, `rover`)
- `weight_limit_kg`
- `volume_limit_m3`
- `range_limit_km`
- `hazard_capability`
- `regulatory_class`
- `insurance_required_flag`

### `ShipmentBoardListing` (new)

- `id`
- `source_order_ref` (FreeBlackMarket order/shipment ID)
- `origin`, `destination`
- `required_transport_classes`
- `cargo_constraints` (`weight`, `volume`, `hazard`)
- `status` (`open`, `claimed`, `in_transit`, `delivered`, `disputed`, `cancelled`)
- `claim_policy` (`first_claim` / `bid`)
- `current_node_id` (nullable)

### `ShipmentLeg` (new for relay)

- `id`
- `shipment_listing_id`
- `sequence`
- `from_node_id`
- `to_node_id`
- `status`
- `proof_of_handoff_hash`
- `settlement_ref`

### `ContributionCredit` (optional incentive module)

- `id`
- `earned_by_node_id`
- `earned_from_ref`
- `amount`
- `expiration_date`
- `usable_for` (`shipping_fees`, `platform_services`)
- `state` (`available`, `consumed`, `expired`)

---

## 3) Centralization Removal Requirements

To align with legal and governance goals, disable or refactor:

- automatic global dispatch assignment
- global route optimization that decides operational routes for nodes
- global pricing engine that sets mandatory shipping rates

Replace these with neutral protocol behavior:

1. Vendor creates shipment request via FreeBlackMarket integration.
2. Listing is posted to eligible nodes by capability and service area.
3. Node claims or bids.
4. Node performs internal planning privately.
5. Platform stores status events only.

---

## 4) Integration Contract: FreeBlackMarket

Use either webhooks or an event bus. Minimal contract below.

### Inbound (from FreeBlackMarket to this protocol)

- `order.created` → may trigger shipment listing pre-validation.
- `delivery.option.selected` (`federated_delivery_network`) → create `ShipmentBoardListing`.
- `order.cancelled` → cancel open listing or request stop on in-transit shipment.

### Outbound (from this protocol to FreeBlackMarket)

- `shipment.claimed`
- `shipment.in_transit`
- `shipment.delivered`
- `shipment.disputed`
- `shipment.leg.updated` (optional for relay transparency)

### Vendor-facing visibility constraints

Allowed:

- node display name
- declared transport class
- node-generated ETA estimate
- reputation score

Not exposed by default:

- route internals
- operator private coordination
- vehicle-level telemetry not required for order status

---

## 5) Integration Contract: Blackout_server

Blackout is the governance and encrypted coordination layer.

### Stored by logistics protocol

- `governance_room_id` on `Node`
- `federation_council_room_id` (global config)
- governance decision references (decision hash / message ref)

### Owned by Blackout workflows

- node membership voting
- local rate floor policies
- cargo restrictions by node/jurisdiction
- inter-node relay standards
- dispute discussion records

### Principle

The logistics protocol **logs outcomes** and references, while Blackout handles encrypted deliberation and voting.

---

## 6) Non-Custodial Payments Compatibility

### Required settlement boundaries

1. Buyer pays vendor directly (e.g., Stripe through marketplace).
2. Vendor pays node directly (Stripe Connect transfer or off-platform method).
3. Platform receives only software/network fees.
4. No pooled custody account for shipment principal.

### Data references to store

- `buyer_vendor_payment_ref`
- `vendor_node_settlement_ref`
- `platform_fee_ref`

These should be references only, not a replacement for payment processor records.

---

## 7) Attestation and Risk Shielding

Before node activation:

- acceptance of legal compliance declaration
- acceptance of license/insurance responsibility declaration
- digital signature hash persisted (`insurance_attestation_hash`, `license_attestation_hash`)

Recommended policy language:

- platform is not a carrier
- platform does not operate vehicles
- platform does not dispatch routes
- nodes are independent service providers

---

## 8) Sequenced Delivery Plan

### Phase 0 (2–3 weeks): fork hardening and dispatch audit

- fork + branding separation + AGPL review
- map centralized logic (orders, dispatch, routing, pricing)

### Phase 1: node tenancy model

- introduce `Node`
- scope fleets, drivers, and vehicles to node
- remove cross-node forced assignment

### Phase 2: transport abstraction

- introduce `TransportClass`
- enforce capability checks without route assignment

### Phase 3: FreeBlackMarket integration

- add federated delivery checkout option
- implement shipment lifecycle sync
- implement vendor visibility constraints

### Phase 4: payments

- direct routing references
- optional contribution credits

### Phase 5: Blackout governance

- node governance room mapping
- federation council integration

### Phase 6: inter-node relay

- shipment leg handoff workflow
- trust scoring metrics (on-time, damage, dispute, participation)

### Phase 7: legal shielding

- attestation workflow
- explicit TOS language

### Phase 8: pilot

- single-city launch with limited transport classes first

---

## 9) Definition of Done for Compatibility

Compatibility with both upstream systems is achieved when:

1. FreeBlackMarket can create and track federated shipments using stable event contracts.
2. Blackout room IDs and governance outcomes are persisted and queryable.
3. Node claim flow works without centralized assignment.
4. Transport classes gate eligibility for listings.
5. Payment architecture is non-custodial by design.
6. Relay shipments support multi-node handoff with auditable status updates.


---

## 10) Detailed End-to-End Build Plan (with AI Prompts)

This section turns the architecture into an executable delivery plan. Each workstream includes:

- **Objective**
- **Implementation tasks**
- **AI prompt pack** (copy/paste prompts for coding agents)
- **Acceptance criteria**

### Workstream A — Repository Baseline and Audit

**Objective**: Freeze a stable starting point and identify centralized behaviors to remove.

**Implementation tasks**

1. Create `federation-audit` branch from pinned release.
2. Inventory modules handling order assignment, dispatch, routing, and rate computation.
3. Produce an architecture map (`docs/federation-audit.md`) with call paths and ownership boundaries.
4. Tag all components that enforce central dispatch assumptions.

**AI prompt pack**

```text
You are working in a Fleetbase fork.
Task: produce a centralized-dispatch audit.
Output:
1) list of files/classes/functions that perform auto-dispatch,
2) list of routing and pricing components that assume centralized control,
3) migration notes showing what to disable vs refactor,
4) markdown report at docs/federation-audit.md.
Constraints:
- do not implement behavior changes in this step,
- include grep/rg command evidence and code references.
```

**Acceptance criteria**

- Audit report exists and is reviewed.
- Every centralized dispatch path has an owner and planned disposition (`remove`, `replace`, `defer`).

---

### Workstream B — Node Tenancy Foundation

**Objective**: Introduce `Node` as first-class tenant and bind fleets/assets to node scope.

**Implementation tasks**

1. Add `Node` model + migration.
2. Add foreign keys from drivers/vehicles/fleets (or equivalent) to `node_id`.
3. Enforce query scoping so users only view data for permitted node(s).
4. Add seed fixtures for at least 3 test nodes.

**AI prompt pack**

```text
Implement Node multi-tenancy foundation.
Requirements:
- Add Node model with fields:
  node_id, legal_entity_name, jurisdiction, service_radius,
  contact, insurance_attestation_hash, license_attestation_hash,
  transport_capabilities, governance_room_id, reputation_score.
- Attach driver/vehicle/fleet ownership to node_id.
- Add policy/scope guards to prevent cross-node leakage.
- Add tests for CRUD + authorization scoping.
Deliverables:
- migrations, models, policies/scopes, tests, and API docs delta.
```

**Acceptance criteria**

- Cross-node data access blocked in API/integration tests.
- Existing non-federated tenants still function after migration.

---

### Workstream C — Shipment Board (No Central Dispatch)

**Objective**: Replace centralized dispatch with listing/claim semantics.

**Implementation tasks**

1. Add `ShipmentBoardListing` entity.
2. Disable auto-assignment pathways.
3. Implement listing visibility rules by service area + capabilities.
4. Implement claim and optional bid flow.

**AI prompt pack**

```text
Refactor dispatch to shipment board model.
Implement:
- ShipmentBoardListing lifecycle: open -> claimed -> in_transit -> delivered/disputed/cancelled.
- Remove/disable automatic global dispatch assignment.
- Add API endpoints: create listing, list eligible listings, claim listing, submit bid (optional).
- Ensure platform does not compute mandatory route assignment.
Include integration tests proving:
- no global dispatcher force-assigns a shipment,
- only eligible nodes can claim.
```

**Acceptance criteria**

- Automated tests confirm no forced cross-node assignment.
- Node claim flow works end-to-end via API.

---

### Workstream D — Transport-Agnostic Capability Layer

**Objective**: Enable all transport methods through class/capability matching.

**Implementation tasks**

1. Add `TransportClass` entity and node-to-transport mapping.
2. Extend listing requirements with cargo constraints (weight/volume/hazard).
3. Add eligibility matcher (capability checks only, no route optimization).

**AI prompt pack**

```text
Add transport-agnostic capability framework.
Implement TransportClass with:
category, subtype, weight_limit, range_limit, hazard_capability,
regulatory_class, insurance_required_flag.
Match listings against node capabilities by constraint checks only.
Do NOT implement route optimization or internal vehicle assignment.
Add unit and integration tests for positive/negative matching cases.
```

**Acceptance criteria**

- Listings are only visible/claimable to compatible nodes.
- Incompatible claims are rejected with explainable validation errors.

---

### Workstream E — FreeBlackMarket Integration

**Objective**: Synchronize marketplace checkout and shipment lifecycle.

**Implementation tasks**

1. Implement inbound events: `order.created`, `delivery.option.selected`, `order.cancelled`.
2. Implement outbound events: `shipment.claimed`, `shipment.in_transit`, `shipment.delivered`, `shipment.disputed`.
3. Add idempotency keys and replay-safe handlers.
4. Document event contract in OpenAPI/AsyncAPI format.

**AI prompt pack**

```text
Implement FreeBlackMarket interoperability.
Build webhook/event handlers for inbound order and delivery-option events.
Emit shipment lifecycle events back to marketplace with signed payloads.
Requirements:
- idempotent handlers,
- dead-letter/retry strategy,
- correlation IDs for tracing,
- integration tests using fixture payloads.
Also produce docs/events/freeblackmarket-contract.md.
```

**Acceptance criteria**

- Marketplace can create federated shipments from checkout.
- Status transitions appear in vendor dashboard after event propagation.

---

### Workstream F — Non-Custodial Settlement Model

**Objective**: Ensure platform never holds shipment principal funds.

**Implementation tasks**

1. Add settlement reference fields for buyer→vendor and vendor→node flows.
2. Implement platform fee capture as separate software fee reference.
3. Add audit logs for settlement state changes.

**AI prompt pack**

```text
Implement non-custodial payment references.
Do not add pooled wallet/custody logic.
Store only references:
- buyer_vendor_payment_ref
- vendor_node_settlement_ref
- platform_fee_ref
Expose read APIs for reconciliation and reporting.
Add tests verifying platform cannot hold or disburse shipment principal.
```

**Acceptance criteria**

- No code path creates pooled custody balances for shipment principal.
- Reconciliation reports can be generated from reference IDs.

---

### Workstream G — Blackout Governance Integration

**Objective**: Connect governance metadata without platform-side governance control.

**Implementation tasks**

1. Persist `governance_room_id` per node.
2. Add federation council room config.
3. Add governance decision reference log API (`decision_hash`, `message_ref`, timestamps).

**AI prompt pack**

```text
Integrate Blackout governance references.
Implement storage and retrieval of:
- node governance_room_id,
- federation_council_room_id,
- governance decision references.
Do not implement vote logic in logistics core.
Add APIs to append/query governance outcomes for auditability.
Include tests for access control and immutability of logged decisions.
```

**Acceptance criteria**

- Governance metadata is queryable and auditable.
- Core logistics engine does not execute governance policy logic directly.

---

### Workstream H — Inter-Node Relay Legs

**Objective**: Support handoff shipments across multiple nodes.

**Implementation tasks**

1. Add `ShipmentLeg` model and APIs.
2. Implement leg sequencing and handoff status updates.
3. Track payout split references per leg.

**AI prompt pack**

```text
Implement inter-node relay protocol.
Add ShipmentLeg with:
sequence, from_node_id, to_node_id, status, proof_of_handoff_hash, settlement_ref.
Support multi-leg status progression and final shipment completion rules.
Add event emission for leg updates and handoff proofs.
Test scenarios:
- 2-leg and 3-leg shipments,
- failed handoff and dispute path.
```

**Acceptance criteria**

- Multi-leg shipments complete successfully with auditable history.
- Disputes can target specific legs.

---

### Workstream I — Reputation and Trust Scoring

**Objective**: Provide transparent node reliability indicators to vendors.

**Implementation tasks**

1. Define metrics: on-time %, damage rate, dispute rate, governance participation.
2. Implement scheduled score recompute job.
3. Expose score breakdown in vendor-safe API fields.

**AI prompt pack**

```text
Build trust scoring module for nodes.
Formula inputs:
- on_time_rate,
- damage_rate,
- dispute_rate,
- governance_participation.
Implement deterministic scoring with explainable components.
Expose aggregate score + metric breakdown to vendor dashboards.
Add tests for metric edge cases and recompute determinism.
```

**Acceptance criteria**

- Scores are reproducible and traceable to metric inputs.
- Vendor visibility excludes private route/vehicle details.

---

### Workstream J — Legal Attestation and TOS Controls

**Objective**: Add activation gating and policy language for risk separation.

**Implementation tasks**

1. Add attestation acceptance workflow and signature hash persistence.
2. Block activation until required attestations are accepted.
3. Add/verify TOS clauses in legal docs and onboarding UI/API text.

**AI prompt pack**

```text
Implement node attestation workflow.
Before activation, require acceptance of:
1) compliance with applicable transport law,
2) license and insurance responsibility,
3) platform indemnification terms.
Persist signed hash artifacts and timestamped acceptance records.
Add validation tests ensuring non-attested nodes cannot claim shipments.
```

**Acceptance criteria**

- Unattested nodes cannot become active claimants.
- Attestation records are immutable/auditable.

---

## 11) Program Plan, Milestones, and Exit Gates

- **Milestone 1**: Audit + Node foundation (A-B)
- **Milestone 2**: Dispatch replacement + transport abstraction (C-D)
- **Milestone 3**: External interoperability (E-G)
- **Milestone 4**: Relay + scoring + legal hardening (H-J)

**Exit gate for pilot launch**

1. No centralized dispatch codepaths active in production config.
2. FreeBlackMarket event contract passes replay/idempotency tests.
3. Blackout governance references live and queryable.
4. Non-custodial payment boundary validated by architecture review.
5. Relay shipment scenarios pass staging soak tests.

---

## 12) QA Plan (End of Program)

QA is required at each milestone and as a final release gate.

### QA scope

1. **Functional QA**
   - Node onboarding, attestation, and activation
   - Shipment listing/claim lifecycle
   - Relay handoffs and disputes
   - Marketplace status synchronization
2. **Security QA**
   - Cross-node data isolation
   - Event signature verification and replay prevention
   - Access control over governance and payment references
3. **Reliability QA**
   - Event retries and dead-letter handling
   - Idempotent processing under duplicate delivery
   - Background job resilience and observability
4. **Compliance QA**
   - Legal attestation records present and immutable
   - TOS language visible at onboarding and API docs
   - Non-custodial constraints verifiably enforced

### Final QA checklist (release blocker)

- [ ] End-to-end order flow from FreeBlackMarket checkout to delivered status works in staging.
- [ ] No automatic dispatcher assignment occurs under any tested scenario.
- [ ] Incompatible transport nodes cannot claim restricted cargo.
- [ ] Node-to-node relay completes with auditable leg history.
- [ ] Governance room references and decision logs are persisted correctly.
- [ ] Payment references reconcile without platform custody of principal.
- [ ] Vendor UI/API exposes only approved visibility fields.
- [ ] Incident runbooks and rollback plan validated in simulation.
