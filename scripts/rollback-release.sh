#!/usr/bin/env bash
set -euo pipefail

# One-command rollback helper used by incident response and CI verification.
#
# Required env vars for a real rollback:
#   CLUSTER, API_SERVICE, SCHEDULER_SERVICE, EVENTS_SERVICE, ROLLBACK_VERSION
#
# Optional:
#   DEPLOY_TOOL (default: ./ecs-tool)
#   CONTAINER_NAME_PATTERN (default: '{container_name}')
#   VERIFY_COMMAND (default: ./scripts/verify-rollback-health.sh)
#   DRY_RUN=1 (default: 1)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

DEPLOY_TOOL="${DEPLOY_TOOL:-./ecs-tool}"
CONTAINER_NAME_PATTERN="${CONTAINER_NAME_PATTERN:-{container_name}}"
VERIFY_COMMAND="${VERIFY_COMMAND:-./scripts/verify-rollback-health.sh}"
DRY_RUN="${DRY_RUN:-1}"

required_vars=(CLUSTER API_SERVICE SCHEDULER_SERVICE EVENTS_SERVICE ROLLBACK_VERSION)
missing=()
for v in "${required_vars[@]}"; do
  if [[ -z "${!v:-}" ]]; then
    missing+=("$v")
  fi
done

if ((${#missing[@]} > 0)); then
  echo "[ERR] Missing required env vars: ${missing[*]}" >&2
  echo "[HINT] Export required vars and rerun. Example:" >&2
  echo "  CLUSTER=prod-cluster API_SERVICE=api SCHEDULER_SERVICE=scheduler EVENTS_SERVICE=events ROLLBACK_VERSION=abc12345 ./scripts/rollback-release.sh" >&2
  exit 1
fi

rollback_cmd=(
  "$DEPLOY_TOOL" deploy
  --image_tag "${CONTAINER_NAME_PATTERN}-${ROLLBACK_VERSION}"
  --cluster "$CLUSTER"
  -s "$API_SERVICE"
  -s "$SCHEDULER_SERVICE"
  -s "$EVENTS_SERVICE"
)

echo "[INFO] Rollback target image tag suffix: ${ROLLBACK_VERSION}"
echo "[INFO] Rollback command: ${rollback_cmd[*]}"

if [[ "$DRY_RUN" == "1" ]]; then
  echo "[DRY-RUN] Skipping deploy and verification."
  exit 0
fi

"${rollback_cmd[@]}"

if [[ -n "$VERIFY_COMMAND" ]]; then
  echo "[INFO] Running post-rollback verification: $VERIFY_COMMAND"
  eval "$VERIFY_COMMAND"
fi

echo "[OK] Rollback deployment and verification completed."
