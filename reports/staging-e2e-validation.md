# Staging E2E Validation Report: Blackstar + FreeBlackMarket Integration

## Runtime used for this rerun
- PHP binary: `/root/.phpenv/versions/8.2snapshot/bin/php` (8.2.31-dev)
- Composer install attempt: `reports/logs/composer-install-php82.log` (exit 1)
- Preflight attempt: `reports/logs/preflight-php82.log` (exit 1)

## Validation matrix

| Scenario | Path covered | Result | Exit | Evidence |
|---|---|---|---:|---|
| S1 Normal | checkout -> `delivery.option.selected` -> listing -> claim -> `in_transit` -> `delivered` | **BLOCKED** | 255 | `reports/logs/staging-s1-normal-rerun.log` |
| S2 Delayed retry | listing created -> claim outbound failure -> retry -> dispatch | **BLOCKED** | 255 | `reports/logs/staging-s2-delayed-retry.log` |
| S3 Cancellation edge | listing claimed/in_transit -> `order.cancelled` -> cancelled | **BLOCKED** | 255 | `reports/logs/staging-s3-cancellation-edge.log` |

## Vendor visibility suite execution
- Command: `PATH=/root/.phpenv/versions/8.2snapshot/bin:$PATH php api/artisan test --env=testing api/tests/Feature/VendorVisibilityContractTest.php`
- Result: **BLOCKED** (exit 255)
- Evidence: `reports/logs/VendorVisibilityContractTest.log`, `reports/logs/VendorVisibilityContractTest.exit`

## Current blockers
1. Composer dependency fetches to GitHub are blocked (`CONNECT tunnel failed, response 403`) while installing locked dependencies.
2. `ext-sodium` is unavailable in the local 8.2 runtime.
3. Partial vendor tree causes bootstrap failure (`Illuminate\Foundation\Application` class missing), preventing test execution.

## Status
Gate 1 and Gate 2 remain incomplete in this environment because scenario and contract suites cannot execute to completion until dependency bootstrap succeeds.
