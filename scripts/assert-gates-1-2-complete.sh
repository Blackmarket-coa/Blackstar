#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPORT="$ROOT_DIR/reports/release-gate-status.json"

python - "$REPORT" <<'PY'
import json
import sys
from pathlib import Path

report = Path(sys.argv[1])
if not report.exists():
    print("release gate report missing", file=sys.stderr)
    sys.exit(2)

data = json.loads(report.read_text())
status = {item["gate"]: item["status"] for item in data.get("gates", [])}

required = [
    "Gate 1: Staging End-to-End Order Flow Validation",
    "Gate 2: Vendor Visibility Constraints Verification",
]

failed = [gate for gate in required if status.get(gate) != "complete"]
if failed:
    print("incomplete gates:")
    for gate in failed:
        print(f"- {gate}: {status.get(gate)}")
    sys.exit(1)

print("Gate 1 and Gate 2 are complete.")
PY
