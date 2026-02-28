# Governance Audit References (Blackout Integration)

This module stores governance references for auditability only.

## Stored references

- Node-level `governance_room_id` (on `nodes` table)
- Global `federation_council_room_id` (in `governance_settings`)
- Immutable governance outcome references (`governance_decision_references`)

## Explicit non-goals in logistics core

- No voting workflow logic
- No proposal state machine
- No encrypted room message handling

## Forbidden in-core behaviors checklist

- [x] No `POST /api/governance/proposals` endpoint.
- [x] No `POST /api/governance/votes` endpoint.
- [x] No governance decision execution endpoint (the core stores references only).
- [x] Governance outcomes remain append-only (`create` + `read` only).
- [x] Governance writes are node-scoped to the authenticated actor.

## APIs

Authenticated endpoints:

- `GET /api/governance/settings`
- `PATCH /api/governance/settings`
- `POST /api/governance/outcomes` (append-only)
- `GET /api/governance/outcomes`
- `GET /api/governance/outcomes/{id}`

## Auditability and immutability

- Outcomes are append-only (model rejects update/delete).
- Outcomes are scoped so users can only query node-owned or global (`node_id = null`) outcomes.
- `correlation_id` is stored for traceability across systems.
