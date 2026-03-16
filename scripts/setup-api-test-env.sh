#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT_DIR/api"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "PHP binary '$PHP_BIN' not found on PATH." >&2
  exit 1
fi

if ! command -v "$COMPOSER_BIN" >/dev/null 2>&1; then
  echo "Composer binary '$COMPOSER_BIN' not found on PATH." >&2
  exit 1
fi

cd "$API_DIR"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

cat > .env.testing <<'EOF'
APP_NAME=Blackstar
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=database/testing.sqlite

BROADCAST_DRIVER=log
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
MAIL_MAILER=array

FREEBLACKMARKET_WEBHOOK_SECRET=test-webhook-secret
FREEBLACKMARKET_OUTBOUND_SECRET=test-outbound-secret
FREEBLACKMARKET_OUTBOUND_URL=https://fbm.example/events
FREEBLACKMARKET_MAX_RETRIES=3
FREEBLACKMARKET_RETRY_BACKOFF_SECONDS=1
EOF

mkdir -p database
: > database/testing.sqlite


if [[ -n "${COMPOSER_REPO_PACKAGIST:-}" ]]; then
  "$PHP_BIN" "$(command -v "$COMPOSER_BIN")" config -g repo.packagist composer "$COMPOSER_REPO_PACKAGIST"
fi

if [[ -n "${COMPOSER_GITHUB_OAUTH_TOKEN:-}" ]]; then
  "$PHP_BIN" "$(command -v "$COMPOSER_BIN")" config -g github-oauth.github.com "$COMPOSER_GITHUB_OAUTH_TOKEN"
fi

if [[ -n "${COMPOSER_AUTH:-}" ]]; then
  export COMPOSER_AUTH
fi

# Respect proxy-constrained environments for Composer and git HTTP fallback.
if [[ -n "${HTTPS_PROXY:-${https_proxy:-}}" ]]; then
  export HTTPS_PROXY="${HTTPS_PROXY:-${https_proxy}}"
fi
if [[ -n "${HTTP_PROXY:-${http_proxy:-}}" ]]; then
  export HTTP_PROXY="${HTTP_PROXY:-${http_proxy}}"
fi

if [[ -n "${HTTPS_PROXY:-}" || -n "${HTTP_PROXY:-}" ]]; then
  git config --global http.proxy "${HTTPS_PROXY:-${HTTP_PROXY}}"
  git config --global https.proxy "${HTTPS_PROXY:-${HTTP_PROXY}}"
fi


if [[ -n "${COMPOSER_GITHUB_MIRROR:-}" ]]; then
  git config --global url."${COMPOSER_GITHUB_MIRROR}".insteadOf https://github.com/
  git config --global url."${COMPOSER_GITHUB_MIRROR}".insteadOf git@github.com:
fi

if ! "$PHP_BIN" -m | awk '{print tolower($0)}' | grep -qx "sodium"; then
  cat >&2 <<'EOF'
Missing required PHP extension 'sodium'.
Install/enable ext-sodium in the active PHP runtime before running Gate 1/2 suites.
EOF
  exit 2
fi

export COMPOSER_ALLOW_SUPERUSER=1
"$PHP_BIN" "$(command -v "$COMPOSER_BIN")" install --no-interaction --prefer-dist --optimize-autoloader

"$PHP_BIN" artisan key:generate --env=testing --force
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan cache:clear
"$PHP_BIN" artisan migrate --env=testing --force

echo "API test environment initialized."
