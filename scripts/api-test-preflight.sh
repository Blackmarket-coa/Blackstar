#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT_DIR/api"

errors=()
warns=()

ok() { echo "[OK] $1"; }
err() { echo "[ERR] $1"; }
warn() { echo "[WARN] $1"; }

if ! command -v php >/dev/null 2>&1; then
  errors+=("PHP binary not found. Install PHP 8.2.x.")
else
  php_version="$(php -r 'echo PHP_VERSION;')"
  if php -r 'exit(version_compare(PHP_VERSION, "8.0.0", ">=") && version_compare(PHP_VERSION, "8.3.0", "<") ? 0 : 1);'; then
    ok "PHP version $php_version is within supported range (>=8.0 <8.3)."
  else
    errors+=("PHP version $php_version is unsupported by api/composer.json (requires >=8.0 <8.3).")
  fi
fi

if ! command -v composer >/dev/null 2>&1; then
  errors+=("Composer binary not found. Install Composer 2.x.")
else
  ok "Composer is available: $(composer --version | head -n1)"
  if [[ -n "${COMPOSER_REPO_PACKAGIST:-}" ]]; then
    ok "Using COMPOSER_REPO_PACKAGIST override for restricted network bootstrap."
  else
    warns+=("COMPOSER_REPO_PACKAGIST not set; default Packagist/GitHub sources will be used.")
  fi

  if [[ -n "${COMPOSER_AUTH:-}" || -n "${COMPOSER_GITHUB_OAUTH_TOKEN:-}" ]]; then
    ok "Composer auth override detected for private/rate-limited dependency access."
  else
    warns+=("No Composer auth override detected (COMPOSER_AUTH or COMPOSER_GITHUB_OAUTH_TOKEN).")
  fi
fi

for ext in sodium pdo_sqlite mbstring xml curl json; do
  if php -m | awk '{print tolower($0)}' | grep -qx "$ext"; then
    ok "PHP extension '$ext' is enabled."
  else
    errors+=("Missing PHP extension '$ext'.")
  fi
done

if [[ -f "$API_DIR/composer.lock" ]]; then
  ok "composer.lock present."
else
  errors+=("api/composer.lock missing; dependency resolution will be non-deterministic.")
fi

if [[ -f "$API_DIR/.env.testing" ]]; then
  ok "api/.env.testing present."
else
  warns+=("api/.env.testing missing; run scripts/setup-api-test-env.sh to generate deterministic test env.")
fi

if [[ -f "$API_DIR/vendor/autoload.php" ]]; then
  ok "Composer vendor autoload present."
else
  warns+=("api/vendor/autoload.php missing; run scripts/setup-api-test-env.sh.")
fi

if [[ -f "$API_DIR/database/testing.sqlite" ]]; then
  ok "SQLite test database present (api/database/testing.sqlite)."
else
  warns+=("api/database/testing.sqlite missing; run scripts/setup-api-test-env.sh.")
fi

if ((${#warns[@]})); then
  for w in "${warns[@]}"; do warn "$w"; done
fi

if ((${#errors[@]})); then
  for e in "${errors[@]}"; do err "$e"; done
  cat <<'EOF'

Actionable guidance:
1) Use supported PHP: 8.2.x.
2) Install required PHP extensions: sodium, pdo_sqlite, mbstring, xml, curl, json.
3) Initialize test environment:
   ./scripts/setup-api-test-env.sh
4) Run critical suites:
   ./scripts/run-api-tests.sh
EOF
  exit 1
fi

echo "Preflight checks passed."
