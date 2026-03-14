# One-Shot Prompt: Complete Blackstar Build Gates

```text
You are a principal release engineer for Blackstar. Complete the remaining release-gating work in one execution pass and produce auditable evidence.

Primary objective:
Close all open release gates in `workflows/full-functionality-workplan.md` and update `FEDERATED_LOGISTICS_COMPATIBILITY.md` with final sign-off evidence.

Execution requirements:
1) Read and use these sources as ground truth:
   - `workflows/full-functionality-workplan.md`
   - `FEDERATED_LOGISTICS_COMPATIBILITY.md`
   - `api/docs/testing-quickstart.md`
   - `scripts/api-test-preflight.sh`
   - `scripts/setup-api-test-env.sh`
   - `scripts/run-api-tests.sh`
   - `scripts/complete-gates-1-2.sh`
   - `scripts/verify-release-gates.sh`
   - `scripts/assert-gates-1-2-complete.sh`

2) Enforce strategic design inputs during validation:
   - Cell-structured routing privacy boundaries (need-to-know exposure only).
   - Dead-drop style signaling / metadata minimization for dispatch flows.
   - Tamper-evident settlement/audit anchoring checks (hash capture + verification).
   - Burst/delayed transport behavior for intermittent connectivity scenarios.

3) Execute gates in this order unless blocked:
   a. Gate 4 baseline (preflight/bootstrap/test executability)
   b. Gate 2 vendor visibility contract coverage + denylist assertions
   c. Gate 1 staging E2E scenarios (normal, delayed retry/burst, cancellation edge)
   d. Gate 3 incident runbooks + at least one live or staged drill per runbook

4) For Gate 1, produce hard evidence per scenario:
   - Correlation IDs across webhook receipts, internal events, and API responses.
   - Lifecycle state transitions (`created/claimed/in_transit/delivered` or cancellation path).
   - Hash anchoring evidence for confirmation/POD/dispute checkpoints where implemented.
   - Explicit pass/fail with root cause and minimal remediation if failed.

5) For Gate 2, enforce contract safety:
   - Allowlist assertions for every vendor-facing endpoint/event.
   - Negative checks proving no route internals/private coordination/unnecessary telemetry leakage.
   - Coverage report listing tested and untested surfaces.

6) For Gate 3, validate operational readiness:
   - Runbooks for webhook outage/signature mismatch, replay storm, dead-letter growth, failed deploy rollback.
   - Simulation or drill logs with timeline, operators, commands, expected vs observed outcomes.
   - Rollback criteria and post-rollback verification checklist.

7) Regenerate gate status artifacts and assert completion:
   - Run `scripts/verify-release-gates.sh` and `scripts/assert-gates-1-2-complete.sh`.
   - Update `reports/release-gate-status.md` and `reports/release-gate-status.json`.

8) Update documentation/checklists:
   - Mark completed gates and remaining blockers in `workflows/full-functionality-workplan.md`.
   - Record final checklist status in `FEDERATED_LOGISTICS_COMPATIBILITY.md`.
   - Add/refresh evidence under `reports/` and `reports/logs/`.

9) If blocked by environment constraints:
   - Document exact failing command, error output, dependency/runtime gap, and next action.
   - Provide smallest actionable patch or config change to unblock.
   - Continue executing all other non-blocked steps.

Output format (required):
A) Final gate matrix (Gate 1–4): status, evidence links, blocker notes.
B) Exact commands run (copy-pasteable), with exit codes.
C) Artifacts produced/updated (paths).
D) Minimal patch set summary (what changed and why).
E) Residual risks + go/no-go recommendation.

Quality bar:
- No hand-wavy conclusions; every gate claim must cite an artifact path.
- Prefer automation over manual assertions.
- Keep changes minimal, reversible, and release-focused.
```
