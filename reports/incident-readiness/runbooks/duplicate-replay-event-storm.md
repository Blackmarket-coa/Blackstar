# Runbook: duplicate/replay event storm

## Severity & trigger
- **Default severity**: SEV-2; SEV-1 if DB/API saturation or queue backlog breaches SLO.
- Trigger when repeated `event_id` spikes or inbound attempts rapidly increase.

## Responders
- IC
- SRE on-call
- DB owner
- API owner

## Detection signals
- Repeated inbound payloads with same `event_id`.
- Increasing `attempts` and delayed retries.
- Elevated processing latency.

## Immediate actions
1. Confirm idempotency (`event_id` uniqueness + first-or-create path).
2. Rate-limit upstream tenant or apply ingress shaping.
3. Process only ready retries; avoid uncontrolled replays.
4. Coordinate with partner to halt replay source.

## Commands
- `rg -n "firstOrCreate\(|event_id" api/app/Services/FreeBlackMarket/InboundEventProcessor.php api/database/migrations/2024_01_01_000340_create_free_black_market_events_tables.php`
- `rg -n "dead_letter|attempts|next_attempt_at" api/app/Services/FreeBlackMarket/InboundEventProcessor.php`

## Rollback / mitigation criteria
- Roll back recent receipt/idempotency logic if duplicate suppression fails in staging replay simulation.
- Apply temporary ingress controls when attempt growth >2x baseline for 15 minutes.

## Post-rollback verification
- Duplicate `event_id` leads to no duplicate side effects.
- Retry queue drains to baseline.
- No increase in dead-letter for known-good payloads.
