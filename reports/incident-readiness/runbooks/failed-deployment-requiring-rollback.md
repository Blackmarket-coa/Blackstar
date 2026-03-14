# Runbook: failed deployment requiring rollback

## Severity & trigger
- **Default severity**: SEV-1 when release breaks critical path or causes sustained 5xx.

## Responders
- IC
- Release manager
- SRE on-call
- Service owner

## Rollback criteria
Rollback if any of the following persist >10 minutes after hotfix attempt:
1. Checkout-to-shipment lifecycle cannot progress (claim/status operations failing).
2. Webhook processing error rate >10%.
3. Outbound dead-letter growth >3x baseline.
4. Security regression (vendor payload includes denylisted fields).

## Rollback procedure
1. Identify last known good commit/tag.
2. Run one-command rollback automation (`scripts/rollback-release.sh`) against previous artifact tag.
3. Re-run smoke checks and integration probes (`scripts/verify-rollback-health.sh`).
4. Freeze further deploys until incident review.

## Commands
- `git rev-parse --abbrev-ref HEAD`
- `git rev-parse HEAD`
- `git log --oneline -n 5`
- `CLUSTER=<cluster> API_SERVICE=<api> SCHEDULER_SERVICE=<scheduler> EVENTS_SERVICE=<events> ROLLBACK_VERSION=<prev-tag> DRY_RUN=0 ./scripts/rollback-release.sh`

## Post-rollback verification checks
- API health and auth checks pass.
- Webhook `delivery.option.selected` returns `202` and processes.
- Claim/status APIs return expected allowlisted payloads.
- Outbound events dispatch without abnormal failure growth.
- No denylisted/internal telemetry keys visible in vendor responses/events.
