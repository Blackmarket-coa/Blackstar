#!/usr/bin/env bash
set -euo pipefail

# CI guard: ensure one-command rollback script remains executable in dry-run mode.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

CLUSTER=ci-cluster \
API_SERVICE=ci-api \
SCHEDULER_SERVICE=ci-scheduler \
EVENTS_SERVICE=ci-events \
ROLLBACK_VERSION=deadbeef \
DRY_RUN=1 \
./scripts/rollback-release.sh

echo "Rollback automation verification passed."
