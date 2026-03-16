#!/usr/bin/env bash
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
STRICT_NETWORK="${STRICT_NETWORK:-0}"

fail() { echo "[FAIL] $*" >&2; exit 1; }
warn() { echo "[WARN] $*"; }
ok() { echo "[OK] $*"; }

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  fail "PHP binary '$PHP_BIN' not found on PATH."
fi

php_version="$($PHP_BIN -r 'echo PHP_VERSION;')"
if "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.0.0", ">=") && version_compare(PHP_VERSION, "8.3.0", "<") ? 0 : 1);'; then
  ok "PHP version $php_version is compatible (>=8.0 <8.3)."
else
  fail "PHP version $php_version is incompatible; expected >=8.0 <8.3."
fi

if "$PHP_BIN" -m | awk '{print tolower($0)}' | grep -qx sodium; then
  ok "PHP extension 'sodium' is enabled."
else
  fail "Missing required PHP extension 'sodium'."
fi

have_packagist=0
have_mirror_path=0
[[ -n "${COMPOSER_REPO_PACKAGIST:-}" ]] && have_packagist=1
if [[ -n "${COMPOSER_GITHUB_MIRROR:-}" && ( -n "${COMPOSER_AUTH:-}" || -n "${COMPOSER_GITHUB_OAUTH_TOKEN:-}" ) ]]; then
  have_mirror_path=1
fi

if [[ "$have_packagist" -eq 1 || "$have_mirror_path" -eq 1 ]]; then
  ok "Composer source path configured for restricted-network bootstrap."
else
  message="No restricted-network Composer source path configured. Set COMPOSER_REPO_PACKAGIST or COMPOSER_GITHUB_MIRROR with COMPOSER_AUTH/COMPOSER_GITHUB_OAUTH_TOKEN."
  if [[ "$STRICT_NETWORK" == "1" ]]; then
    fail "$message"
  else
    warn "$message"
  fi
fi

if command -v curl >/dev/null 2>&1; then
  if [[ -n "${COMPOSER_REPO_PACKAGIST:-}" ]]; then
    if curl -fsSLI --max-time 15 "$COMPOSER_REPO_PACKAGIST" >/dev/null 2>&1; then
      ok "Configured COMPOSER_REPO_PACKAGIST endpoint reachable."
    else
      message="Configured COMPOSER_REPO_PACKAGIST endpoint is not reachable: $COMPOSER_REPO_PACKAGIST"
      [[ "$STRICT_NETWORK" == "1" ]] && fail "$message" || warn "$message"
    fi
  fi

  if [[ -n "${COMPOSER_GITHUB_MIRROR:-}" ]]; then
    if curl -fsSLI --max-time 15 "$COMPOSER_GITHUB_MIRROR" >/dev/null 2>&1; then
      ok "Configured COMPOSER_GITHUB_MIRROR endpoint reachable."
    else
      message="Configured COMPOSER_GITHUB_MIRROR endpoint is not reachable: $COMPOSER_GITHUB_MIRROR"
      [[ "$STRICT_NETWORK" == "1" ]] && fail "$message" || warn "$message"
    fi
  fi
fi

ok "Gate runtime provisioning checks passed."
