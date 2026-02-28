# Staging E2E Validation Report: Blackstar + FreeBlackMarket Integration

## Validation matrix

| Scenario | Path covered | Result | Evidence |
|---|---|---|---|
| S1 Normal | checkout -> `delivery.option.selected` -> listing -> claim -> `in_transit` -> `delivered` | **BLOCKED** | `reports/logs/staging-s1-normal-rerun.log` |
| S2 Delayed retry | listing created -> claim outbound failure -> retry -> dispatch | **BLOCKED** | `reports/logs/staging-s2-delayed-retry.log` |
| S3 Cancellation edge | listing claimed/in_transit -> `order.cancelled` -> cancelled | **BLOCKED** | `reports/logs/staging-s3-cancellation-edge.log` |

## Exact scenario commands executed

1. `php api/artisan test --filter=test_scenario_normal_full_lifecycle_with_correlation_consistency api/tests/Feature/StagingE2EValidationTest.php`
2. `php api/artisan test --filter=test_scenario_delayed_retry_dispatches_outbound_event_after_initial_failure api/tests/Feature/StagingE2EValidationTest.php`
3. `php api/artisan test --filter=test_scenario_cancellation_edge_cancels_in_transit_listing api/tests/Feature/StagingE2EValidationTest.php`

## Current blockers

1. `api/vendor/autoload.php` missing.
2. Runtime PHP in this container is `8.5.3-dev`, outside supported policy (`>=8.0 <8.3`).
3. `ext-sodium` is unavailable.

## Status

Scenario definitions remain in `api/tests/Feature/StagingE2EValidationTest.php`; execution evidence above shows all three attempted reruns are still blocked by runtime prerequisites.
