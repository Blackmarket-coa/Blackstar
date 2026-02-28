# Vendor Visibility Contract Coverage Report

## Enumerated vendor-facing API response surfaces

| Surface | Type | Coverage |
|---|---|---|
| `POST /api/webhooks/freeblackmarket` response envelope | API response | ✅ tested |
| `POST /api/webhooks/freeblackmarket/retry` response envelope | API response | ✅ tested |
| `POST /api/shipment-board-listings` response payload | API response | ✅ tested |
| `GET /api/shipment-board-listings/eligible` response payload | API response | ✅ tested |
| `POST /api/shipment-board-listings/{id}/claim` response payload | API response | ✅ tested |
| `POST /api/shipment-board-listings/{id}/status` response payload | API response | ✅ tested |
| `POST /api/shipment-board-listings/{id}/bids` response payload | API response | ✅ tested |

## Enumerated vendor-facing outbound events

| Event | Coverage |
|---|---|
| `shipment.claimed` | ✅ regression tested |
| `shipment.in_transit` | ✅ regression tested |
| `shipment.delivered` | ✅ regression tested |
| `shipment.disputed` | ✅ regression tested |
| `shipment.cancelled` | ✅ regression tested |

## Denylist coverage categories
- route internals (`route_plan`, `route_polyline`, `dispatch_internal_id`, `private_coordination`)
- private coordination data (`internal_notes`, `node_private_key`)
- non-required vehicle telemetry (`telemetry`, `gps_trace`, `vehicle_position`, `engine_temp`, `engine_rpm`)

## Missing surfaces / follow-up
- No uncovered vendor-facing surfaces remain in this gate report.
