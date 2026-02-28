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
