# Federated Logistics Compatibility Blueprint

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
