# Staging E2E Validation Report: Blackstar + FreeBlackMarket Integration

## Validation matrix

| Scenario | Path covered | Result | Correlation proof status | Evidence |
|---|---|---|---|---|
| S1 Normal | checkout -> `delivery.option.selected` -> listing -> claim -> `in_transit` -> `delivered` | **BLOCKED** | Blocked before runtime bootstrap | `reports/logs/staging-s1-normal.log` |
| S2 Delayed retry | listing created -> claim outbound failure -> retry -> dispatch | **BLOCKED** | Blocked before runtime bootstrap | `reports/logs/staging-s2-delayed-retry.log` |
| S3 Cancellation edge | listing claimed/in_transit -> `order.cancelled` -> cancelled | **BLOCKED** | Blocked before runtime bootstrap | `reports/logs/staging-s3-cancellation-edge.log` |

## Execution plan (implemented)
- Scenario definitions and assertions are implemented in `api/tests/Feature/StagingE2EValidationTest.php`:
  - Normal full lifecycle + correlation checks.
  - Delayed retry flow.
  - Cancellation edge flow.

## Exact commands used

### Dependency/bootstrap attempts
1. `PHPENV_VERSION=8.2snapshot composer --working-dir=api install --no-interaction --prefer-dist --ignore-platform-req=ext-sodium`
2. `PHPENV_VERSION=8.2snapshot composer --working-dir=api install --no-interaction --prefer-dist --ignore-platform-req=php --ignore-platform-req=ext-sodium`

### Scenario executions
3. `PHPENV_VERSION=8.2snapshot php api/artisan test --filter=test_scenario_normal_full_lifecycle_with_correlation_consistency api/tests/Feature/StagingE2EValidationTest.php`
4. `PHPENV_VERSION=8.2snapshot php api/artisan test --filter=test_scenario_delayed_retry_dispatches_outbound_event_after_initial_failure api/tests/Feature/StagingE2EValidationTest.php`
5. `PHPENV_VERSION=8.2snapshot php api/artisan test --filter=test_scenario_cancellation_edge_cancels_in_transit_listing api/tests/Feature/StagingE2EValidationTest.php`

### Failed-scenario rerun after patch recommendation
6. `PHPENV_VERSION=8.2snapshot php api/artisan test --filter=test_scenario_normal_full_lifecycle_with_correlation_consistency api/tests/Feature/StagingE2EValidationTest.php`

## API calls covered by scenario implementation
- `POST /api/webhooks/freeblackmarket` (`delivery.option.selected`)
- `POST /api/shipment-board-listings/{id}/claim`
- `POST /api/shipment-board-listings/{id}/status` (`in_transit`, `delivered`)
- `POST /api/webhooks/freeblackmarket/retry`
- `POST /api/webhooks/freeblackmarket` (`order.cancelled`)

## Correlation ID consistency design/assertions
Expected correlation IDs are asserted across:
- webhook API response (`correlation_id`),
- inbound receipt persistence (`fbm_inbound_event_receipts.correlation_id`),
- outbound events (`fbm_outbound_events.correlation_id`),
- claim/status API response (`correlation_id`).

## Failure analysis and minimal patch recommendations

### Observed blockers
1. `php artisan test` fails before test execution due missing `api/vendor/autoload.php`.
2. Composer bootstrap fails in this environment due:
   - PHP constraint mismatch (`8.2.31-dev` > `<=8.2.30`) unless ignored,
   - GitHub network restrictions (`CONNECT tunnel failed, response 403`) preventing full dependency fetch.

### Minimal patch recommendations
1. **Test-runtime compatibility patch**: relax `api/composer.json` PHP upper bound from `<=8.2.30` to `<=8.2` or `^8.2` (or pin CI/local exactly to 8.2.30).
2. **Dependency bootstrap reliability patch**: configure Composer auth/mirror for GitHub sources in CI/staging-like runners where outbound GitHub access is restricted.
3. **Execution guard patch**: keep preflight gate (`scripts/api-test-preflight.sh`) as required step before staging validation execution.

## Remaining blockers
- Runtime dependencies are unavailable in this execution environment, so no live scenario reached app-level request handling.
- Correlation ID consistency is verified at test design level, but runtime proof requires successful dependency bootstrap and test execution.
# Staging E2E Validation Report: Blackstar + FreeBlackMarket

## Validation Matrix

| Scenario | Step | Result | Evidence |
|---|---|---|---|
| S1 Normal lifecycle | checkout -> delivery.option.selected -> listing -> claim -> in_transit -> delivered | BLOCKED (runtime deps missing) | `reports/logs/scenario-normal-initial.log`, `reports/logs/scenario-normal-rerun-after-patch.log` |
| S2 Delayed retry | first outbound dispatch fails; retry dispatch succeeds | BLOCKED (tests cannot execute without vendor deps) | `api/tests/Feature/StagingE2EValidationTest.php` |
| S3 Cancellation edge | in_transit listing cancelled via order.cancelled webhook | BLOCKED (tests cannot execute without vendor deps) | `api/tests/Feature/StagingE2EValidationTest.php` |

## Correlation-ID Trace Plan
- Inbound receipt assertions: `fbm_inbound_event_receipts.correlation_id`.
- Outbound event assertions: `fbm_outbound_events.correlation_id` for `shipment.claimed`, `shipment.in_transit`, `shipment.delivered`.
- API response assertions: `correlation_id` in claim/status responses.

## Exact Commands / API Calls Used

### Commands
1. `cd /workspace/Blackstar/api && php artisan test --filter=test_scenario_normal_full_lifecycle_with_correlation_consistency tests/Feature/StagingE2EValidationTest.php`
2. `cd /workspace/Blackstar/api && composer install --no-interaction --prefer-dist`
3. `cd /workspace/Blackstar/api && composer install --no-interaction --prefer-dist --ignore-platform-reqs`
4. `php -l /workspace/Blackstar/api/app/Http/Controllers/Api/ShipmentBoardListingController.php`
5. `php -l /workspace/Blackstar/api/tests/Feature/StagingE2EValidationTest.php`

### API Calls in scenario implementation
- `POST /api/webhooks/freeblackmarket` (delivery.option.selected)
- `POST /api/shipment-board-listings/{id}/claim`
- `POST /api/shipment-board-listings/{id}/status` (`in_transit`, `delivered`)
- `POST /api/webhooks/freeblackmarket/retry`
- `POST /api/webhooks/freeblackmarket` (order.cancelled)

## Failure + Patch + Rerun
- Initial execution failed before test bootstrap due missing `vendor/autoload.php`.
- Minimal patch implemented anyway to close a traceability gap: claim/status endpoints now return `correlation_id` in JSON.
- Rerun attempted for failed scenario; still blocked by missing dependencies.

## Remaining Blockers
1. Composer cannot complete install in this environment because GitHub fetches are blocked (`CONNECT tunnel failed, response 403`).
2. Without `vendor/`, Laravel test runner cannot execute E2E scenarios.
