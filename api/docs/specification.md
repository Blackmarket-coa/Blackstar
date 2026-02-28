# Federated Logistics Protocol (FLP) Formal Specification

> Canonical FLP specification for this repository. Compatibility planning details remain in `FEDERATED_LOGISTICS_COMPATIBILITY.md`.

See also: [Compatibility Blueprint](../../FEDERATED_LOGISTICS_COMPATIBILITY.md).

## 1. Purpose and Scope
Define a federated logistics protocol where independent nodes coordinate shipment execution without centralized dispatch.

## 2. Objectives
1. Transport-method agnostic capability matching.
2. Node autonomy for acceptance and planning.
3. No platform-side route or dispatch control.
4. Non-custodial payment boundary.
5. Governance references only, with deliberation externalized to Blackout.
6. Interoperability with FreeBlackMarket order lifecycle.

## 3. System Topology
FreeBlackMarket -> FLP Core -> Independent Nodes -> Blackout governance rooms.

## 4. Core Entities
`Node`, `TransportClass`, `ShipmentBoardListing`, `ShipmentLeg`, governance reference entities.

## 5. ShipmentBoardListing Normative Schema
Required protocol fields:
- `source_order_ref`
- `origin`, `destination`
- `required_category`, `required_subtype`
- `required_weight_limit`, `required_volume_limit`, `required_range_limit`
- `requires_hazard_capability`, `required_regulatory_class`, `insurance_required_flag`
- `status`, `claim_policy`, `current_node_id`

## 6. Lifecycle State Machine
`open -> claimed -> in_transit -> delivered|disputed|cancelled` with append-only event trail.

## 7. No-Central-Dispatch Invariant
The core MUST NOT auto-assign operators or compute mandatory route execution. Claim/bid actions are node-initiated only.

## 8. Eligibility and Capability Matching
Eligibility SHALL be based on node activation/attestation, jurisdiction rules, and declared transport constraints only.

## 9. Inter-Node Relay
Relay uses sequenced shipment legs with handoff proof references and auditable status transitions.

## 10. Marketplace Contract (FreeBlackMarket)
Inbound events: `order.created`, `delivery.option.selected`, `order.cancelled`.
Outbound events: `shipment.claimed`, `shipment.in_transit`, `shipment.delivered`, `shipment.disputed`, `shipment.cancelled`.

## 11. Vendor Visibility Policy
Allowed fields: node display name, transport class, ETA estimate, reputation score, listing lifecycle status.
Disallowed fields by default: route internals, private coordination artifacts, non-required telemetry.

## 12. Governance Boundary
FLP stores governance references (`governance_room_id`, council room id, decision refs) but MUST NOT run proposal/vote/execution logic in-core.

## 13. Security and Policy Constraints
Enforce authn/authz, node scoping, replay-aware webhook processing, and append-only governance outcomes.

## 14. Payment Boundary
Payments are non-custodial and reference-only in FLP (`buyer_vendor_payment_ref`, `vendor_node_settlement_ref`, `platform_fee_ref`).

## 15. Contribution Credits Module Status
**Deferred by design (current release train).**
- Non-goal for current gate: no in-core transferable/cashable credit mechanics.
- Rationale: prioritize release blockers around interoperability, visibility contracts, and staging validation.
- Target revisit: after Gate 1 and Gate 2 full staging completion.

## 16. Conformance and Test Requirements
Conformance evidence SHALL include API contracts, visibility denylist checks, governance boundary tests, and no-dispatch regression tests.

## 17. Delivery Phases and Release Gates
Authoritative execution sequencing and gate evidence tracking are in [workflows/full-functionality-workplan.md](../../workflows/full-functionality-workplan.md).

## 18. QA and Release Checklist
Release gating requires E2E staging evidence, contract conformance, incident readiness, and reproducible runtime/test bootstrap.

---

### Cross-reference index to compatibility blueprint
- Scope/objectives: `FEDERATED_LOGISTICS_COMPATIBILITY.md` sections 1-2.
- Governance boundary: section 5 and section 11 workstream G.
- Delivery phases / DoD: sections 8-9.
- QA release checklist: section 12.
