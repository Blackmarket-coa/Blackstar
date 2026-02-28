#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="$ROOT_DIR/reports/logs"
mkdir -p "$LOG_DIR"

PHP_BIN="${PHP_BIN:-php}"
PHP_PATH="$(command -v "$PHP_BIN")"
PHP_DIR="$(dirname "$PHP_PATH")"

run_capture() {
  local name="$1"
  shift
  local log="$LOG_DIR/${name}.log"
  local exitf="$LOG_DIR/${name}.exit"

  echo "[RUN] $*"
  if "$@" >"$log" 2>&1; then
    echo 0 >"$exitf"
  else
    echo $? >"$exitf"
  fi
  if [[ -f "$log" ]]; then
    local lines
    lines=$(wc -l <"$log" || echo 0)
    if [[ "$lines" -gt 250 ]]; then
      tail -n 250 "$log" >"${log}.tmp" && mv "${log}.tmp" "$log"
    fi
  fi
  echo "[DONE] $name exit=$(cat "$exitf") log=$log"
}

run_capture preflight-g12 "$PHP_BIN" -v
run_capture preflight-g12-check bash -lc "PATH=$PHP_DIR:\$PATH ./scripts/api-test-preflight.sh"
run_capture composer-install-g12 bash -lc "PATH=$PHP_DIR:\$PATH composer --working-dir=api install --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-req=ext-sodium"

run_capture staging-s1-normal-rerun bash -lc "PATH=$PHP_DIR:\$PATH php api/artisan test --env=testing --filter=test_scenario_normal_full_lifecycle_with_correlation_consistency api/tests/Feature/StagingE2EValidationTest.php"
run_capture staging-s2-delayed-retry bash -lc "PATH=$PHP_DIR:\$PATH php api/artisan test --env=testing --filter=test_scenario_delayed_retry_dispatches_outbound_event_after_initial_failure api/tests/Feature/StagingE2EValidationTest.php"
run_capture staging-s3-cancellation-edge bash -lc "PATH=$PHP_DIR:\$PATH php api/artisan test --env=testing --filter=test_scenario_cancellation_edge_cancels_in_transit_listing api/tests/Feature/StagingE2EValidationTest.php"
run_capture VendorVisibilityContractTest bash -lc "PATH=$PHP_DIR:\$PATH php api/artisan test --env=testing api/tests/Feature/VendorVisibilityContractTest.php"

./scripts/verify-release-gates.sh

echo "Gate 1/2 execution attempt completed. See reports/release-gate-status.md"
