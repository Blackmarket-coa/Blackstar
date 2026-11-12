# Blackstar roadmap

## Current integration priority — 2026-11-12

Reuse the existing Blackstar transport implementation inside FBM's character-built unified member portal. The [local integration contract](api/docs/fbm-unified-portal-integration.md) mirrors the canonical FBM plan and records source findings, gaps and acceptance.

This supersedes the October 4 assumption that all Blackstar work waits until vending stage three. Vending/micro-depot expansion remains a separate later track; existing delivery/operator integration is planned now. No deployment date or readiness is asserted by this documentation change.

| Package | Work | Release evidence |
| --- | --- | --- |
| 1. Source/runtime reconciliation | Identify deployed revisions, enabled bridge flags, event configuration and membership data | Built/deployed/enabled/blocked/unknown recorded separately |
| 2. Character-build operator path | Reuse FBM explicit opt-in/provisioning and existing inactive-node/attestation/capability rules | Survey produces honest setup state; a vehicle answer or paid equipment does not activate a carrier |
| 3. Member/operator session | Implement verified linking/sign-in and recovery; keep machine bridge keys server-side | Authenticated member actions reach only their permitted node/work |
| 4. Existing board into the portal | Reuse eligible listings, voluntary claims and status APIs, then complete action UI | Ordinary delivery works end to end through the existing FBM fulfillment bridge |
| 5. Bid/award and relay views | Reuse transport bids/awards and ordered leg progression | Resolve poster-only/service-account delegation gap; validate concurrent actions and leg-versus-shipment status |
| 6. Coalition drives and grouped runs | Reuse Coalition map/coordination, known Coalition/drive refs and existing FBM batch/order-cycle primitives | Active membership, parcel/order mapping, permissions and recovery verified before pooling claims |
| Later. Vending/depot expansion | Assess restock points and independent hardware economics | Separate scope, infrastructure and verified demand; no implied route optimizer |

GlobalDispatchService currently returns null; the roadmap does not introduce forced assignment. Blackstar payment custody remains prohibited. Goods auctions are FBM commerce work and must not be described as implemented because transport bidding exists.

## Historical October 4 observations — revalidate before release

The earlier [FBM Black Mask launch plan](https://github.com/Blackmarket-coa/free-black-market/blob/main/docs/BLACK_MASK_LAUNCH_PLAN.md) placed Blackstar after a three-stage vending rollout. That launch ordering is superseded for current portal/transport integration, while the following historical observations remain useful evidence to recheck:

- On 2026-10-04 the Actions API reportedly had zero runs; workflow files alone did not prove functioning CI.
- Blackstar was reported not deployed at that time. Current deployment state has not been audited by this doc update.
- Vendor-stocked vending units/micro-depots require their own hardware/revenue/infrastructure plan; a 3% commerce commission is not a hardware funding claim.

## Boundaries

FBM owns commerce/billing/equipment and its existing inventory/agreements. Blackout Coalition owns the shared map/community coordination. Blackstar owns operator eligibility and physical transport state. Reuse authoritative records and versioned events rather than duplicating the backends.

No runtime, flag, role, fee, dependency or deployment changes are made by this roadmap update.
