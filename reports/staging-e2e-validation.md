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
