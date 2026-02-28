# Incident Readiness Action Items (Prioritized)

## Critical
1. **Codify deployment rollback automation**
   - Owner: Release Engineering
   - Due: Before release cut
   - Deliverable: one-command rollback + verification job in CI/CD.

2. **Establish dead-letter growth paging threshold**
   - Owner: SRE
   - Due: Before release cut
   - Deliverable: alert policy for `failed/dead_letter` slope and absolute threshold.

## High
3. **Run full end-to-end incident game day in dependency-complete staging**
   - Owner: SRE + API team
   - Deliverable: executed webhook outage/replay/dead-letter/rollback drills with timing SLA.

4. **Document partner secret rotation procedure**
   - Owner: Integration owner
   - Deliverable: secure dual-key rotation runbook with cutover/rollback windows.

## Medium
5. **Add dashboard links directly into runbooks**
   - Owner: Observability team
   - Deliverable: metric/log/trace links per incident type.

6. **Backfill retry endpoint contract tests**
   - Owner: API QA
   - Deliverable: automated test for `/api/webhooks/freeblackmarket/retry` response and safety behavior.
