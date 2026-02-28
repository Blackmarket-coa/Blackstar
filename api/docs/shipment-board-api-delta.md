# API Delta: Shipment Board (No Central Dispatch)

## Lifecycle

`ShipmentBoardListing` status flow:

- `open` -> `claimed` -> `in_transit` -> (`delivered` | `disputed` | `cancelled`)
- `open` can also transition to `cancelled`.

The platform does not compute mandatory route assignments.

## Endpoints

Authenticated endpoints under `/api`:

- `POST /api/shipment-board-listings` create listing
- `GET /api/shipment-board-listings/eligible` list listings eligible for caller's node
- `POST /api/shipment-board-listings/{id}/claim` claim an open eligible listing
- `POST /api/shipment-board-listings/{id}/bids` submit/update bid (only when `claim_policy=bid`)
- `POST /api/shipment-board-listings/{id}/status` transition lifecycle (`in_transit|delivered|disputed|cancelled`)

## Central dispatch disablement

- `automatic_global_assignment` configuration defaults to `false`.
- `GlobalDispatchService::autoAssign()` is a no-op and does not assign nodes.

## Eligibility rules

A node is eligible to claim/bid only if:

- listing status is `open`,
- listing jurisdiction matches node jurisdiction when listing jurisdiction is specified,
- node includes all listing required transport capabilities.
