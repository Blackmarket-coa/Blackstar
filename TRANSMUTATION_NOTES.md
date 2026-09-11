# Transmutation review — what it found in this repo

Part of the 2026-09-09 transmutation-strategy reconciliation. The canonical
document is `docs/TRANSMUTATION_STRATEGY.md` in
`Blackmarket-coa/free-black-market`; this file records only the findings that
land in Blackstar. No code rides with this file.

## 1. `CONSOLIDATION.md` was right, and the strategy brief was wrong

The brief's infrastructure gap table asserted that Blackstar's "mesh routing /
reverse-auction bidding / micro-depot relays" already exist and are an "ideal
fit" for salvage freight. This repo's own `CONSOLIDATION.md` says they "exist
as design docs only", and the code agrees. Re-verified feature by feature:

| Feature | State |
| --- | --- |
| Shipment board | Shipped. `ShipmentBoardListing.php` with a guarded `transitionTo`, migrations, routes, tests. API-only — no console screen |
| Claim | Shipped. `ShipmentBoardListingController::claim()` |
| Bid | **Write-only.** `ShipmentBid` appears in the model, factory, migration and one `updateOrCreate`, plus an uncalled `bids()` relation. Nothing awards |
| Shipment leg | Shipped and good. Guarded two-node handoff with proof and settlement reference, `ShipmentLegProgressionService`, tests |
| Node attestation | Shipped |
| Trust score | Shipped but inert and self-reported — the node's own user supplies the rates, and `ShipmentEligibilityService` never reads them |
| Mesh routing | Prose only (`api/docs/network-advantage-engine.md`) |
| Batch aggregation | Prose only |
| Micro-depot | Prose only. `workflows/blackstar-console-nav-workplan.md:50` lists relay-point management as **NOT STARTED** |
| Reverse auction | Prose only |

## 2. Three specific things worth fixing or recording

- **`claim()` ignores `claim_policy`.** On a listing configured for bidding,
  the first eligible node to POST `/claim` takes it and every bid row is
  ignored. Either honour the policy or stop offering it as a setting.
- **`nodes` cannot describe a place.** Fourteen columns, none of them latitude,
  longitude, geometry, kind, hours or capacity. It carries a `service_radius`
  with no centre, and that column is never read outside its own validator. A
  `ShipmentLeg`'s `to_node_id` therefore points at a table that cannot say
  where anything is. This is the blocker for any depot work, not the depot
  model itself.
- ~~**FBM cannot see the relay.**~~ **Half fixed here 2026-09-10; the other
  half is an FBM change.** Blackstar emitted seven event types and FBM's
  `verify-blackstar-signature.ts` mapped five, so the two leg events — the
  ones a depot handoff would ride — were signed, delivered and answered with
  202 `{"status":"ignored"}`.

  Tracing it showed FBM was not at fault: `api/docs/events/freeblackmarket-contract.md`
  documented only the five, and FBM's map carries the comment "the five
  outbound events Blackstar's contract documents, nothing else". It
  implemented the contract faithfully. **The contract was incomplete**, and had
  been since the leg relay shipped.

  Worse, the two leg payloads omitted `source_order_ref` — the field FBM's
  receiver keys every inbound event on — while the three listing-level events
  emitted from the *same service* all included it. So even a receiver that
  wanted to act on a leg event could not attribute it to an order. That was an
  omission rather than a decision: `$listing->source_order_ref` was in scope on
  both lines.

  Fixed on this side: both leg events now carry `source_order_ref`, both are
  documented in the FBM contract with their payload shapes and an explicit
  warning that they report **leg** progress and no receiver may derive a
  listing status from them, and `ShipmentLegRelayTest` now asserts the payload
  contents rather than only that an event of each type exists — the assertion
  shape that let this pass unnoticed.

  Still open on the FBM side: recording these events instead of discarding
  them. That is tracked there, not here.

Note that mesh routing is not merely unbuilt but excluded by the current
architecture: `GlobalDispatchService::autoAssign()` returns null
unconditionally, and `api/docs/shipment-board-api-delta.md:10` states "The
platform does not compute mandatory route assignments." Building mesh routing
means reversing that decision deliberately, not filling a gap.

## 3. Salvage freight does not unfreeze this repo

The canonical document's §4.3 concludes that staging for a
deconstruction/salvage business line is an **FBM listing**, not a Blackstar
extension. The sequence `CONSOLIDATION.md` already records — an FBM depot
listing first, then a depot node kind a `ShipmentLeg` can hand off to, then
pooling — is unchanged by the new business line. FBM's `aid-network`
`network_node` already carries coordinates, node kinds including cold storage,
routes and a live vendor screen; its limitation is that it is single-seller,
which is a smaller problem than adding geography to a legal-entity table.

## 4. One liability item the brief did not raise

A member-hosted micro-depot holding other people's goods is a **bailment**, not
carriage. The node attestations here cover insurance, licensing, transport law
and platform indemnification — all framed around carriage — and there is no
depot-specific attestation term. Shipping a staging registry without one puts
uninsured custody of third-party property onto volunteers with a spare barn.

Relatedly: `Services/Payments/NonCustodialPaymentGuard.php` throws
unconditionally on custody, and `ShipmentPaymentReference` is reference-only.
That is correct today, and it is the pattern the canonical document cites as
the strongest kind of boundary in the ecosystem. A reverse auction that
automatically selects a winning price, combined with a listing `bounty_amount`
and a per-leg `settlement_ref`, moves toward clearing prices between parties —
validate that against this guard and FBM's `posture-a-guard.ts` **before** any
award endpoint is built, not after.
