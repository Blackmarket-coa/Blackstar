# FBM unified portal: Blackstar integration contract

**Decision date:** 2026-11-12  
**Status:** Approved product direction; proposed integration, not a deployment claim.  
**Canonical plan:** [FBM unified member portal and character-build plan](https://github.com/Blackmarket-coa/free-black-market/blob/47f81529f834565ad8ccf0fbca4e64d876ca3fb0/docs/FBM_UNIFIED_MEMBER_PORTAL_PLAN.md).

The member sees one FBM account/character/portal for buying, selling, trading and goods auctions. Character creation configures real tools based on goals, resources and practical participation. Blackstar supplies existing transport operations; Coalition supplies the shared map/community coordination. Do not rebuild those domains in FBM.

## Reuse verified code

| Existing Blackstar code | Integration use | Remaining limit |
| --- | --- | --- |
| ShipmentBoardListing and controller | Eligible work, voluntary claim, transport bid/award and status | Full member action UI and concurrency/recovery require validation |
| Node and ShipmentEligibilityService | Location/radius, active/attested status, jurisdiction, transport capability and Coalition membership filtering | Unset geography falls back; do not imply proven local coverage |
| ShipmentLegProgressionService | Ordered relay progression and handoff events | FBM currently records leg events without converting them into whole-shipment status |
| FreeBlackMarket inbound/outbound processors | Existing signed event/retry bridge | Configuration, membership data and end-to-end delivery must be verified |
| Console dispatch page | Existing eligible-board request/display patterns | Current page is not a complete claim/bid/award workspace |

See [controller](../app/Http/Controllers/Api/ShipmentBoardListingController.php), [eligibility](../app/Services/ShipmentEligibilityService.php), [leg progression](../app/Services/ShipmentLegProgressionService.php) and [event processor](../app/Services/FreeBlackMarket/InboundEventProcessor.php). These are reusable implementations, not new backend requirements.

## Character survey and operator setup

Reuse FBM's existing ResourceQuiz delivery opt-in and node-operator provisioner. Transportation ownership alone is not agreement to operate. Opting in may add a transport setup quest and board to the member's portal; it does not activate a node, prove an attestation or guarantee paid work.

Provisioning creates an inactive node. Existing activation/transport checks remain. Paid enhancement equipment may configure supported software but cannot buy eligibility, Coalition membership, transport attestations or authorization.

## Session and delegation gaps

Console operator authentication is distinct from Fleetbase/FBM sessions. The reviewed provisioner creates a random password; a usable member-to-operator sign-in/linking and recovery path must be designed and verified. Bridge credentials authenticate machines/events, not ordinary member browser actions. Do not expose them as portal login tokens.

Award permission is **poster-only** in the inspected controller. The old idea that a Coalition coordinator can automatically award a service-account-created listing is not implemented. Membership narrows eligibility but supplies no delegation role. Resolve authenticated grant/revoke, scope, self-award protection and audit before describing Coalition transport bidding as usable.

## Lifecycle and payment boundaries

Reuse the current flow: FBM's Blackstar fulfillment subscriber emits delivery.option.selected → Blackstar creates an open board listing → eligible operator voluntarily claims work → listing-level events update FBM delivery state. The inbound order.created event is recorded but intentionally performs no shipment creation.

Leg updates/handoff proof are different from listing-level delivered. Keep stable references and separate progress views; one completed leg must not falsely mark the entire order delivered.

GlobalDispatchService currently returns null. Preserve voluntary work acceptance; do not promise centralized automatic assignment, a route optimizer or deployed mesh/depot planning from the presence of board/relay records.

Blackstar's noncustodial payment guard rejects holding/disbursing shipment principal. Reuse existing payment references and FBM commerce boundaries; this plan adds no custody wallet or settlement rail. Transport bids are quotes for work, not FBM goods-auction mechanics.

## Shared map and record ownership

Blackout Coalition retains the maintained real-world map, place/area semantics and community visibility. Blackstar retains nodes/operators/transport state, and FBM retains commerce/inventory/orders/equipment.

Reference the proper Coalition, drive, order and shipment IDs; do not copy private addresses or live positions onto a public map. Opted-in pickup places and approximate service areas are separate projections from shipment-party details.

## Delivery packages and acceptance

1. Integrate operator setup/session/capability state into the character-builder preview without another buyer/vendor portal.
2. Expose existing eligible board and claim/status operations in the member portal, preserving authorization and access to ordinary shopping when logistics is unavailable.
3. Validate signed event delivery, configuration, replay/retry/cancel recovery and a real order-to-delivered journey.
4. Add bid/award and relay panels only after authenticated delegation and lifecycle semantics are correct; test competing claims/awards and avoid duplicate jobs.
5. Coordinate Coalition drives with known IDs and active memberships. Verify grouped-run/order-cycle/parcel mapping before claiming pooling or optimization.
6. Preserve inactive/attestation/transport-class rules and payment boundaries; test public projection privacy and operator access recovery.

Existing vending/micro-depot expansion is a separate later track. Current transport integration is not categorically deferred until vending stage three. Historical deployment/CI observations must be revalidated before release.

This document changes no runtime API, flag, role, billing, deployment or carrier eligibility.
