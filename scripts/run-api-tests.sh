#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT_DIR/api"
LOG_DIR="$ROOT_DIR/reports/logs"
mkdir -p "$LOG_DIR"

"$ROOT_DIR/scripts/api-test-preflight.sh"

cd "$API_DIR"

critical_suites=(
  tests/Feature/FreeBlackMarketInteropTest.php
  tests/Feature/ShipmentBoardListingTest.php
  tests/Feature/VendorVisibilityContractTest.php
  tests/Feature/StagingE2EValidationTest.php
)

for suite in "${critical_suites[@]}"; do
  suite_name="$(basename "$suite" .php)"
  echo "Running $suite_name"
  php artisan test --env=testing "$suite" --testdox 2>&1 | tee "$LOG_DIR/${suite_name}.log"
done

echo "Critical API feature suites completed. Logs: $LOG_DIR"
