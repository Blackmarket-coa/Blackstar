# Node Trust Scoring

Deterministic trust scoring module for vendor dashboards.

## Formula inputs

- `on_time_rate`
- `damage_rate`
- `dispute_rate`
- `governance_participation`

All inputs are clamped to `[0, 1]`.

## Scoring formula (0-100)

- `on_time_component = on_time_rate * 50`
- `damage_component = (1 - damage_rate) * 20`
- `dispute_component = (1 - dispute_rate) * 20`
- `governance_component = governance_participation * 10`
- `aggregate_score = clamp(0, 100, sum(components))`

## Vendor dashboard APIs

Authenticated endpoints:

- `GET /api/vendor-dashboard/nodes/{node}/trust-score`
- `POST /api/vendor-dashboard/nodes/{node}/trust-score/recompute`

Response includes:

- `aggregate_score`
- explainable `breakdown` with all inputs and component contributions.
