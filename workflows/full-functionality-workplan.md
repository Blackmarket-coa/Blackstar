# Full Functionality Workplan: Release-Gating Items

This work document tracks the remaining gating items needed to move from "implemented components" to a fully functional, release-ready system.

## Goal

Validate and operationalize the system so all release-blocker checklist items have objective evidence of completion.

## Gating Items

### Gate 1: Staging End-to-End Order Flow Validation

**Why this is gating**
- We need evidence that the full flow works in a real staging environment (checkout -> webhook ingestion -> listing claim -> relay/lifecycle updates -> delivered sync).

**Definition of done**
- At least 3 complete staging runs from FreeBlackMarket checkout through `delivered` with traceable correlation IDs.
- No manual DB edits required in successful runs.
- Results are documented with timestamps and linked logs/events.

**AI prompt pack**

```text
You are a release validation engineer.
Create and execute a staging E2E validation plan for Blackstar + FreeBlackMarket integration.

Requirements:
1) Validate full path: checkout -> delivery.option.selected webhook -> shipment listing created -> claim -> in_transit -> delivered.
2) Capture correlation IDs and prove they appear consistently in inbound receipts, outbound events, and API responses.
3) Run at least 3 independent scenarios (normal, delayed retry, cancellation edge).
4) Produce a machine-readable report (markdown + JSON artifact) with pass/fail per step and links to logs.
5) If a scenario fails, provide minimal patch recommendations and rerun failed scenario.

Return:
- Final validation matrix
- Exact commands/API calls used
- Evidence references
- Remaining blockers (if any)
```

---

### Gate 2: Vendor Visibility Constraints Verification

**Why this is gating**
- Visibility policy exists, but we still need comprehensive evidence that all vendor-facing API/UI surfaces expose only approved fields.

**Definition of done**
- Field-level allowlist tests exist for all vendor-facing payloads.
- Negative assertions prove route internals/private coordination/vehicle telemetry are not exposed by default.
- Contract tests run in CI and pass.

**AI prompt pack**

```text
You are a security-focused API QA engineer.
Implement and run vendor-visibility contract verification for Blackstar.

Requirements:
1) Enumerate every vendor-facing API response and event payload.
2) Build allowlist-based tests that assert only approved fields are present.
3) Add denylist checks for route internals, private coordination data, and non-required vehicle telemetry.
4) Include regression tests for shipment.claimed, shipment.in_transit, shipment.delivered, shipment.disputed payloads.
5) Generate a coverage report showing endpoints/events tested and missing surfaces.

Output:
- Test files added/updated
- Pass/fail report
- Any schema changes required
```

---

### Gate 3: Incident Runbook + Rollback Simulation Validation

**Why this is gating**
- Operational readiness is incomplete without tested runbooks and rollback drills.

**Definition of done**
- Runbooks documented for: webhook outage, event replay spike, failed outbound dispatch, bad deployment rollback.
- At least one simulation/drill per runbook completed and recorded.
- Recovery time, decision points, and rollback verification captured.

**AI prompt pack**

```text
You are an SRE incident readiness lead.
Create and validate incident runbooks and rollback simulation for Blackstar release readiness.

Deliverables:
1) Runbooks for:
   - FreeBlackMarket webhook signature mismatch/outage
   - duplicate/replay event storm
   - outbound event dispatch dead-letter growth
   - failed deployment requiring rollback
2) For each runbook, run a tabletop or scripted simulation in staging-like environment.
3) Record timeline, responders, commands run, expected vs observed results.
4) Define rollback criteria and post-rollback verification checks.
5) Produce a final readiness scorecard with open risks.

Provide:
- Markdown runbook docs
- Simulation logs/checklists
- Action items prioritized by severity
```

---

### Gate 4: Local/CI Test Executability Baseline

**Why this is gating**
- We need reproducible test execution to continuously verify behavior before release.

**Definition of done**
- Dependency bootstrap is documented and automated (Composer + JS deps as needed).
- Core feature suites execute in CI and locally using one documented command set.
- Failures provide actionable output.

**AI prompt pack**

```text
You are a build/release engineer.
Make the Blackstar test suites reliably executable in local dev and CI.

Tasks:
1) Identify missing dependency/bootstrap steps blocking php artisan test.
2) Add/update setup scripts and docs for deterministic environment initialization.
3) Ensure critical feature suites run in CI with clear job logs.
4) Add a lightweight preflight command that checks required binaries/files and exits with actionable guidance.
5) Provide a final quickstart: clone -> setup -> run tests in minimal commands.

Return:
- Updated scripts/config/docs
- CI job summary
- Before/after execution results
```

---

## Recommended Execution Order

1. Gate 4 (test executability baseline)
2. Gate 2 (vendor visibility contract tests)
3. Gate 1 (staging E2E validation)
4. Gate 3 (incident + rollback simulations)

## Release Readiness Exit Criteria

All gates are complete only when each has:
- Implemented changes merged
- Automated verification passing where applicable
- Human-readable evidence artifacts committed under `workflows/` or `api/docs/`
- Checklist updates recorded in `FEDERATED_LOGISTICS_COMPATIBILITY.md`


## Current Execution Status (Updated)

| Gate | Status | Evidence | Notes |
|---|---|---|---|
| Gate 1: Staging End-to-End Order Flow Validation | ⚠️ Partially complete (rerun attempted on PHP 8.2 runtime, still environment-blocked) | `api/tests/Feature/StagingE2EValidationTest.php`, `reports/staging-e2e-validation.md`, `reports/staging-e2e-validation.json`, `reports/logs/composer-install-g12.log`, `reports/logs/preflight-g12-check.log` | Scenarios were re-run with `/root/.phpenv/versions/8.2snapshot/bin/php`; execution remains blocked by GitHub network restrictions during Composer install + missing `ext-sodium`, which leaves incomplete vendor bootstrap. |
| Gate 2: Vendor Visibility Constraints Verification | ⚠️ Partially complete (surface coverage complete; runtime execution attempted but blocked) | `api/tests/Feature/VendorVisibilityContractTest.php`, `reports/vendor-visibility-contract-coverage.md`, `reports/vendor-visibility-contract-coverage.json`, `reports/logs/VendorVisibilityContractTest.log` | Coverage artifacts are complete; direct suite execution in the compliant PHP runtime is blocked by incomplete dependency bootstrap in this environment. |
| Gate 3: Incident Runbook + Rollback Simulation Validation | ✅ Complete in staged tabletop/scripted form | `reports/incident-readiness/runbooks/`, `reports/incident-readiness/simulations/`, `reports/incident-readiness/readiness-scorecard.md`, `reports/incident-readiness/action-items-prioritized.md` | Runbooks and scenario simulations recorded with timelines/responders/commands/observed-vs-expected. |
| Gate 4: Local/CI Test Executability Baseline | ✅ Implemented baseline with preflight/setup/CI critical suites | `scripts/api-test-preflight.sh`, `scripts/setup-api-test-env.sh`, `scripts/run-api-tests.sh`, `.github/workflows/ci.yml`, `api/docs/testing-quickstart.md` | Deterministic setup and CI wiring are in place; environment constraints still affect this specific container. |

### Remaining Release-Blocking Work

1. Execute Gate 1 suite in a dependency-complete staging/CI runtime (supported PHP + extensions + successful Composer bootstrap).
2. Convert Gate 3 tabletop completion into at least one live operational drill using deployed staging services and dashboard-linked evidence.
3. Reflect final gate sign-offs in `FEDERATED_LOGISTICS_COMPATIBILITY.md` once runtime validations pass.

### Gate Evidence Automation

- `scripts/complete-gates-1-2.sh` runs the Gate 1 scenarios + Gate 2 vendor contract suite with captured `.log/.exit` artifacts.
- `scripts/verify-release-gates.sh` generates `reports/release-gate-status.json` and `reports/release-gate-status.md` from current evidence files.

### Deferred Scope

- Contribution Credits remains a documented deferment (non-goal) until post-gate stabilization.
