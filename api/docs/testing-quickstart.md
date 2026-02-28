# Blackstar API Test Quickstart (Local + CI)

## Why this exists
`php artisan test` can fail early when environment bootstrap is incomplete (unsupported PHP version, missing extensions, missing `vendor/`, missing `.env.testing`, or missing test DB file).

This guide makes test execution deterministic for local dev and CI.

## Minimal commands (clone -> setup -> run)

```bash
git clone <repo-url>
cd Blackstar
./scripts/setup-api-test-env.sh
./scripts/run-api-tests.sh
```

## Preflight only (fast diagnostics)

```bash
./scripts/api-test-preflight.sh
```

If preflight fails, it prints actionable steps and exits non-zero.

## Critical feature suites run by `run-api-tests.sh`
- `tests/Feature/FreeBlackMarketInteropTest.php`
- `tests/Feature/ShipmentBoardListingTest.php`
- `tests/Feature/VendorVisibilityContractTest.php`
- `tests/Feature/StagingE2EValidationTest.php`

Logs are written to `reports/logs/*.log`.

## CI behavior
The CI workflow includes an `API Critical Feature Suites` job that:
1. sets up PHP 8.2 + required extensions,
2. installs Composer dependencies,
3. runs deterministic setup,
4. runs preflight,
5. runs critical feature suites,
6. uploads `reports/logs/*.log` as artifact.

## Troubleshooting
- **Unsupported PHP version**: use PHP `8.2.x`.
- **Missing extension (`sodium`, `pdo_sqlite`, etc.)**: install/enable extension.
- **`vendor/autoload.php` missing**: run `./scripts/setup-api-test-env.sh`.
- **Registry/auth fetch errors**: configure Composer auth for required package sources.
