# API Delta: Node Multi-Tenancy Foundation

## New resources

All endpoints are under `/api` and require authentication.

- `GET /api/nodes`
- `POST /api/nodes`
- `GET /api/nodes/{id}`
- `PUT/PATCH /api/nodes/{id}`
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
- `transport_capabilities` (json)
- `governance_room_id`
- `reputation_score`

## Authorization and scoping

- Fleets, vehicles, and drivers are node-scoped by global model scope (`node_tenancy`).
- Policies deny cross-node `view`, `update`, and `delete` operations.
- Creation defaults to the authenticated user's `node_id` when omitted.
