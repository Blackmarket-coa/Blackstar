# Shipment Leg Relay Protocol

Adds inter-node relay support with appendable shipment legs.

## ShipmentLeg fields

- `sequence`
- `from_node_id`
- `to_node_id`
- `status`
- `proof_of_handoff_hash`
- `settlement_ref`

## Status progression

Leg status flow:

- `pending -> in_transit -> handed_off -> completed`
- terminal failure paths: `failed` or `disputed`

## Multi-leg completion rules

- A leg with `sequence > 1` cannot enter `in_transit` until previous sequence leg is `completed`.
- If all legs are `completed`, listing transitions to `delivered`.
- If any leg enters `failed` or `disputed`, listing transitions to `disputed`.

## Event emission

On leg updates and handoff proof logging, outbound events are emitted:

- `shipment.leg.updated`
- `shipment.leg.handoff_proof`
- final listing updates where applicable (`shipment.in_transit`, `shipment.delivered`, `shipment.disputed`).

## APIs

Authenticated endpoints:

- `GET /api/shipment-board-listings/{shipmentBoardListing}/legs`
- `POST /api/shipment-board-listings/{shipmentBoardListing}/legs`
- `PATCH /api/shipment-board-listings/{shipmentBoardListing}/legs/{shipmentLeg}`
