# Runbook: outbound event dispatch dead-letter growth

## Severity & trigger
- **Default severity**: SEV-2.
- Trigger when `fbm_outbound_events` in `failed/dead_letter` exceed threshold (e.g., >100 or >5% of outbound volume).

## Responders
- IC
- SRE on-call
- Integration owner

## Detection signals
- Rising `failed` or `dead_letter` statuses.
- Repeated outbound HTTP 5xx/timeout errors.
- Partner endpoint degradation.

## Immediate actions
1. Validate outbound URL, DNS, TLS, and auth signature behavior.
2. Force targeted retry after partner confirms recovery.
3. If persistent failures, switch to controlled retry windows to prevent storm amplification.

## Commands
- `rg -n "retryPending|markFailed|dead_letter|attempts|outbound_url" api/app/Services/FreeBlackMarket/OutboundEventPublisher.php`
- `rg -n "fbm_outbound_events|status|attempts|next_attempt_at" api/database/migrations/2024_01_01_000340_create_free_black_market_events_tables.php`

## Rollback / mitigation criteria
- Roll back recent outbound publisher changes if dead-letter slope does not reduce within 2 retry cycles.
- Trigger feature-flagged suppression of non-critical outbound events during incident.

## Post-rollback verification
- Retries transition `failed` -> `dispatched` for reachable partner.
- Dead-letter growth trend flattens.
- `shipment.claimed|in_transit|delivered|disputed` continue dispatching with valid correlation IDs.
