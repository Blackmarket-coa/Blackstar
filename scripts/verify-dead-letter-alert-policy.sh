#!/usr/bin/env bash
set -euo pipefail

POLICY_FILE="reports/incident-readiness/dead-letter-alert-policy.yaml"

python - "$POLICY_FILE" <<'PY'
import sys
from pathlib import Path

path = Path(sys.argv[1])
if not path.exists():
    print(f"missing policy file: {path}", file=sys.stderr)
    raise SystemExit(1)

text = path.read_text()

def must_contain(fragment: str) -> None:
    if fragment not in text:
        print(f"missing required fragment: {fragment}", file=sys.stderr)
        raise SystemExit(1)

for required in [
    "absolute_count:",
    "slope_per_5m:",
    "pager_channel:",
    "runbook:",
]:
    must_contain(required)

# Tiny parse for numeric threshold values.
values = {}
current_key = None
for raw in text.splitlines():
    line = raw.strip()
    if line.startswith("absolute_count:"):
        current_key = "absolute_count"
    elif line.startswith("slope_per_5m:"):
        current_key = "slope_per_5m"
    elif line.startswith("value:") and current_key:
        try:
            values[current_key] = float(line.split(":", 1)[1].strip())
        except ValueError:
            print(f"invalid numeric value under {current_key}", file=sys.stderr)
            raise SystemExit(1)
        current_key = None

if values.get("absolute_count", 0) <= 0:
    print("absolute_count threshold must be > 0", file=sys.stderr)
    raise SystemExit(1)
if values.get("slope_per_5m", 0) <= 0:
    print("slope_per_5m threshold must be > 0", file=sys.stderr)
    raise SystemExit(1)

print("Dead-letter alert policy verification passed.")
PY
