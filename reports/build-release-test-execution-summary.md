# Build/Release Test Execution Summary

## CI job summary
A new GitHub Actions job was added to `.github/workflows/ci.yml`:

### Job: `API Critical Feature Suites`
1. Checkout repository with submodules.
2. Setup PHP 8.2 with required extensions (`mbstring`, `xml`, `curl`, `json`, `sodium`, `pdo_sqlite`).
3. Install Composer dependencies in `api/`.
4. Run deterministic setup script: `./scripts/setup-api-test-env.sh`.
5. Run preflight: `./scripts/api-test-preflight.sh`.
6. Run critical suites: `./scripts/run-api-tests.sh`.
7. Upload suite logs from `reports/logs/*.log` as CI artifact.

## Before/after execution results (current container)

### Before (direct test invocation)
- Command: `cd api && php artisan test --filter=FreeBlackMarketInteropTest tests/Feature/FreeBlackMarketInteropTest.php`
- Exit code: `255`
- Result: fails immediately due missing `api/vendor/autoload.php`.

### After (preflight command)
- Command: `./scripts/api-test-preflight.sh`
- Exit code: `1` (expected in this environment)
- Result: fails fast with actionable diagnostics:
  - unsupported PHP version (8.5.3-dev vs required <=8.2.30),
  - missing `sodium` extension,
  - missing `.env.testing`, `vendor/autoload.php`, and `database/testing.sqlite`.

This is a controlled failure mode replacing opaque `artisan` bootstrap failures.

## Deterministic quickstart
```bash
./scripts/setup-api-test-env.sh
./scripts/run-api-tests.sh
```
