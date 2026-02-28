#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPORT_JSON="$ROOT_DIR/reports/release-gate-status.json"
REPORT_MD="$ROOT_DIR/reports/release-gate-status.md"

python - "$ROOT_DIR" "$REPORT_JSON" "$REPORT_MD" <<'PY'
import json
from pathlib import Path
import sys

root = Path(sys.argv[1])
report_json = Path(sys.argv[2])
report_md = Path(sys.argv[3])

def exists(rel):
    return (root / rel).exists()

def read_json(rel):
    path = root / rel
    if not path.exists():
        return None
    try:
        return json.loads(path.read_text())
    except Exception:
        return None

gates = []

staging = read_json('reports/staging-e2e-validation.json') or {}
scenario_statuses = [s.get('status') for s in staging.get('scenarios', [])]
s1 = bool(scenario_statuses) and all(status in {'passed', 'complete'} for status in scenario_statuses)
gates.append({
    'gate': 'Gate 1: Staging End-to-End Order Flow Validation',
    'status': 'complete' if s1 else 'incomplete',
    'evidence': [
        'reports/staging-e2e-validation.md',
        'reports/staging-e2e-validation.json',
        'reports/logs/staging-s1-normal-rerun.log',
        'reports/logs/staging-s2-delayed-retry.log',
        'reports/logs/staging-s3-cancellation-edge.log',
    ],
})

coverage = read_json('reports/vendor-visibility-contract-coverage.json') or {}
coverage_ok = all(s.get('covered') for s in coverage.get('api_surfaces', [])) and all(e.get('covered') for e in coverage.get('outbound_events', []))
vendor_log_ok = exists('reports/logs/VendorVisibilityContractTest.log')
s2 = coverage_ok and vendor_log_ok
gates.append({
    'gate': 'Gate 2: Vendor Visibility Constraints Verification',
    'status': 'complete' if s2 else 'incomplete',
    'evidence': [
        'api/tests/Feature/VendorVisibilityContractTest.php',
        'reports/vendor-visibility-contract-coverage.md',
        'reports/vendor-visibility-contract-coverage.json',
    ],
})

s3 = all(exists(p) for p in [
    'reports/incident-readiness/readiness-scorecard.md',
    'reports/incident-readiness/runbooks/freeblackmarket-webhook-signature-mismatch-outage.md',
    'reports/incident-readiness/simulations/webhook-signature-mismatch-outage.log',
])
gates.append({
    'gate': 'Gate 3: Incident Runbook + Rollback Simulation Validation',
    'status': 'complete' if s3 else 'incomplete',
    'evidence': [
        'reports/incident-readiness/readiness-scorecard.md',
        'reports/incident-readiness/runbooks/freeblackmarket-webhook-signature-mismatch-outage.md',
        'reports/incident-readiness/simulations/webhook-signature-mismatch-outage.log',
    ],
})

s4 = all(exists(p) for p in [
    'scripts/api-test-preflight.sh',
    'scripts/setup-api-test-env.sh',
    'scripts/run-api-tests.sh',
    'api/docs/testing-quickstart.md',
])
gates.append({
    'gate': 'Gate 4: Local/CI Test Executability Baseline',
    'status': 'complete' if s4 else 'incomplete',
    'evidence': [
        'scripts/api-test-preflight.sh',
        'scripts/setup-api-test-env.sh',
        'scripts/run-api-tests.sh',
        'api/docs/testing-quickstart.md',
    ],
})

report_json.write_text(json.dumps({'generated_at': 'auto', 'gates': gates}, indent=2) + '\n')

lines = ['# Release Gate Status Report', '']
for gate in gates:
    icon = '✅' if gate['status'] == 'complete' else '⚠️'
    lines.append(f"- {icon} **{gate['gate']}**: {gate['status']}")
    for evidence in gate['evidence']:
        lines.append(f"  - `{evidence}`")
report_md.write_text('\n'.join(lines) + '\n')
print('Wrote', report_json)
print('Wrote', report_md)
PY
