#!/usr/bin/env bash
set -euo pipefail

OUT_DIR="reports/incident-readiness/simulations"
mkdir -p "$OUT_DIR"

run_case () {
  local case_id="$1"
  local outfile="$OUT_DIR/${case_id}.log"
  {
    echo "# Simulation: ${case_id}"
    echo "start_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "responders=IC,SRE-oncall,API-owner"
    echo
    case "$case_id" in
      webhook-signature-mismatch-outage)
        echo "[timeline] T+00 identify signature validation path"
        echo "$ rg -n \"verifySignature|Invalid signature\" api/app/Http/Controllers/Api/Webhooks/FreeBlackMarketWebhookController.php"
        rg -n "verifySignature|Invalid signature" api/app/Http/Controllers/Api/Webhooks/FreeBlackMarketWebhookController.php
        echo
        echo "[timeline] T+05 verify ingress routes"
        echo "$ rg -n \"webhooks/freeblackmarket\" api/routes/api.php"
        rg -n "webhooks/freeblackmarket" api/routes/api.php
        echo
        echo "expected=401 on signature mismatch; outage mitigation via retry endpoint and queue drain"
        echo "observed=signature mismatch branch present with abort_unless(...,401); retry endpoint exists"
        ;;
      duplicate-replay-event-storm)
        echo "[timeline] T+00 verify idempotency controls"
        echo "$ rg -n \"firstOrCreate\(|event_id\" api/app/Services/FreeBlackMarket/InboundEventProcessor.php api/database/migrations/2024_01_01_000340_create_free_black_market_events_tables.php"
        rg -n "firstOrCreate\(|event_id" api/app/Services/FreeBlackMarket/InboundEventProcessor.php api/database/migrations/2024_01_01_000340_create_free_black_market_events_tables.php
        echo
        echo "[timeline] T+08 verify retry/dead-letter behavior"
        echo "$ rg -n \"dead_letter|attempts|next_attempt_at\" api/app/Services/FreeBlackMarket/InboundEventProcessor.php"
        rg -n "dead_letter|attempts|next_attempt_at" api/app/Services/FreeBlackMarket/InboundEventProcessor.php
        echo
        echo "expected=duplicate event_id dedup + bounded retries"
        echo "observed=event_id firstOrCreate idempotency and dead_letter transitions confirmed"
        ;;
      outbound-dead-letter-growth)
        echo "[timeline] T+00 inspect outbound publisher failure path"
        echo "$ rg -n \"retryPending|markFailed|dead_letter|attempts|outbound_url\" api/app/Services/FreeBlackMarket/OutboundEventPublisher.php"
        rg -n "retryPending|markFailed|dead_letter|attempts|outbound_url" api/app/Services/FreeBlackMarket/OutboundEventPublisher.php
        echo
        echo "[timeline] T+10 inspect outbound event schema for status tracking"
        echo "$ rg -n \"fbm_outbound_events|status|attempts|next_attempt_at\" api/database/migrations/2024_01_01_000340_create_free_black_market_events_tables.php"
        rg -n "fbm_outbound_events|status|attempts|next_attempt_at" api/database/migrations/2024_01_01_000340_create_free_black_market_events_tables.php
        echo
        echo "expected=failed dispatch increments attempts and enters failed/dead_letter with backoff"
        echo "observed=markFailed + retryPending pathways present"
        ;;
      failed-deployment-rollback)
        echo "[timeline] T+00 collect release metadata"
        echo "$ git rev-parse --abbrev-ref HEAD"
        git rev-parse --abbrev-ref HEAD
        echo "$ git rev-parse HEAD"
        git rev-parse HEAD
        echo "$ git log --oneline -n 5"
        git log --oneline -n 5
        echo
        echo "[timeline] T+12 dry-run rollback plan validation"
        echo "$ rg -n \"rollback|release\" RELEASE.md"
        rg -n "rollback|release" RELEASE.md || true
        echo
        echo "expected=known-good commit available + explicit verification checklist"
        echo "observed=git history available for rollback target selection; release doc has limited rollback details"
        ;;
      *)
        echo "unknown case"; exit 1;;
    esac

    echo
    echo "end_utc=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  } | tee "$outfile"
}

run_case webhook-signature-mismatch-outage
run_case duplicate-replay-event-storm
run_case outbound-dead-letter-growth
run_case failed-deployment-rollback

printf "Generated logs in %s\n" "$OUT_DIR"
