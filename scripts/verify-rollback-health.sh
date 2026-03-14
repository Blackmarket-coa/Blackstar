#!/usr/bin/env bash
set -euo pipefail

# Fast health checks intended for immediate post-rollback verification.
# Assumes API base URL is reachable and test token is optional.

API_BASE_URL="${API_BASE_URL:-http://localhost}"
AUTH_TOKEN="${AUTH_TOKEN:-}"

headers=(-H "Accept: application/json")
if [[ -n "$AUTH_TOKEN" ]]; then
  headers+=(-H "Authorization: Bearer $AUTH_TOKEN")
fi

echo "[INFO] Checking API health endpoint..."
curl -fsS "${API_BASE_URL}/api" >/dev/null || {
  echo "[ERR] API base endpoint check failed" >&2
  exit 1
}

echo "[INFO] Checking retry endpoint response shape..."
retry_resp="$(curl -fsS -X POST "${API_BASE_URL}/api/webhooks/freeblackmarket/retry" "${headers[@]}")"
if [[ "$retry_resp" != *'"status"'* ]]; then
  echo "[ERR] Retry endpoint response missing status field: $retry_resp" >&2
  exit 1
fi

echo "[OK] Rollback health checks passed."
