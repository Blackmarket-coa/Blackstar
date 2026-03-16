#!/usr/bin/env bash
set -euo pipefail

BLACKSTAR_API_URL="${BLACKSTAR_API_URL:-https://staging-api.blackstar.example}"
STAGING_API_SMOKE_REQUIRED="${STAGING_API_SMOKE_REQUIRED:-0}"
BLACKSTAR_AUTH_TOKEN_STORAGE_KEY="${BLACKSTAR_AUTH_TOKEN_STORAGE_KEY:-blackstar.auth.token}"

export BLACKSTAR_API_URL
export BLACKSTAR_AUTH_TOKEN_STORAGE_KEY

node - <<'NODE'
const cfgFactory = require('./apps/blackstar-console/config/environment');
const cfg = cfgFactory('development');
if (!cfg.API.host || !cfg.API.host.startsWith('http')) {
  console.error('console config failed to derive API host');
  process.exit(1);
}
if (!cfg['ember-local-storage']?.namespace) {
  console.error('console config missing storage namespace');
  process.exit(1);
}
console.log('console smoke OK apiHost=' + cfg.API.host + ' tokenNamespace=' + cfg['ember-local-storage'].namespace);
NODE

node apps/blackstar-nav/scripts/smoke.js

echo "Blackstar apps smoke checks passed."

if command -v curl >/dev/null 2>&1; then
  if curl -fsSLI --max-time 15 "$BLACKSTAR_API_URL" >/dev/null 2>&1; then
    echo "staging api reachability check passed: $BLACKSTAR_API_URL"
  else
    msg="staging api reachability check failed: $BLACKSTAR_API_URL"
    if [[ "$STAGING_API_SMOKE_REQUIRED" == "1" ]]; then
      echo "$msg" >&2
      exit 1
    fi
    echo "WARNING: $msg"
  fi
fi
