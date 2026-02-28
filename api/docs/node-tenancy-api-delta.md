# API Delta: Node Multi-Tenancy Foundation

## New resources

All endpoints are under `/api` and require authentication.

- `GET /api/nodes`
- `POST /api/nodes`
- `GET /api/nodes/{id}`
- `PUT/PATCH /api/nodes/{id}`
- `POST /api/nodes/{id}/attest`
- `DELETE /api/nodes/{id}`

- `GET /api/fleets`
- `POST /api/fleets`
- `GET /api/fleets/{id}`
- `PUT/PATCH /api/fleets/{id}`
- `DELETE /api/fleets/{id}`

- `GET /api/vehicles`
- `POST /api/vehicles`
- `GET /api/vehicles/{id}`
- `PUT/PATCH /api/vehicles/{id}`
- `DELETE /api/vehicles/{id}`

- `GET /api/drivers`
- `POST /api/drivers`
- `GET /api/drivers/{id}`
- `PUT/PATCH /api/drivers/{id}`
- `DELETE /api/drivers/{id}`

## Node model fields

- `node_id`
- `legal_entity_name`
- `jurisdiction`
- `service_radius`
- `contact` (json)
- `insurance_attestation_hash`
- `license_attestation_hash`
- `transport_law_attestation_hash`
- `platform_indemnification_attestation_hash`
- `transport_capabilities` (json)
- `governance_room_id`
- `reputation_score`
- `is_active`
- `activated_at`

## Authorization and scoping

- Fleets, vehicles, and drivers are node-scoped by global model scope (`node_tenancy`).
- Policies deny cross-node `view`, `update`, and `delete` operations.
- Creation defaults to the authenticated user's `node_id` when omitted.

## Node attestation activation workflow

`POST /api/nodes/{id}/attest` requires acceptance of all activation terms before the node is activated:

1. `transport_law_compliance`
2. `license_and_insurance_responsibility`
3. `platform_indemnification`

The endpoint stores timestamped acceptance records in `node_attestation_acceptances` and persists signed hash artifacts on the node record.
