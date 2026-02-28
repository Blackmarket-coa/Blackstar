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
2. Re-deploy previous artifact/config set.
3. Re-run smoke checks and integration probes.
4. Freeze further deploys until incident review.

## Commands
- `git rev-parse --abbrev-ref HEAD`
- `git rev-parse HEAD`
- `git log --oneline -n 5`
- (deployment system specific) rollback command for prior artifact

## Post-rollback verification checks
- API health and auth checks pass.
- Webhook `delivery.option.selected` returns `202` and processes.
- Claim/status APIs return expected allowlisted payloads.
- Outbound events dispatch without abnormal failure growth.
- No denylisted/internal telemetry keys visible in vendor responses/events.
