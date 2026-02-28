# Runbook: FreeBlackMarket webhook signature mismatch / outage

## Severity & trigger
- **Default severity**: SEV-2 (degrade), SEV-1 if >30% webhook failures for 10 min.
- Trigger when webhook endpoint returns elevated `401 Invalid signature` or upstream outage prevents delivery.

## Responders
- Incident Commander (IC)
- SRE on-call
- API owner (Blackstar integration)
- Partner operations liaison (FreeBlackMarket)

## Detection signals
- Spike in `401` from `/api/webhooks/freeblackmarket`.
- Growth in failed inbound receipts pending retry.
- Missing shipment listing creation after checkout.

## Immediate actions
1. Confirm signature verification code path and current secret configuration.
2. Validate incoming payload canonicalization assumptions with partner.
3. If upstream outage: pause noisy alerts, enable degraded mode comms, continue retries.
4. If secret mismatch: rotate to validated secret pair and reprocess failed receipts.

## Commands
- `rg -n "verifySignature|Invalid signature" api/app/Http/Controllers/Api/Webhooks/FreeBlackMarketWebhookController.php`
- `rg -n "webhooks/freeblackmarket" api/routes/api.php`
- `POST /api/webhooks/freeblackmarket/retry` (after fix)

## Rollback / mitigation criteria
- Roll back recent webhook auth/config changes if either:
  - Signature failures remain >5% after 15 minutes post-fix.
  - No successful receipt processing observed in two retry intervals.

## Post-rollback verification
- Successful signed webhook returns `202` and receipt status `processed`.
- No new `401` spikes after secret rollback.
- Correlation IDs continue to propagate in response body/header paths.
