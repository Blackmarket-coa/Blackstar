#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT_DIR/api"

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

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan key:generate --env=testing --force
php artisan config:clear
php artisan cache:clear
php artisan migrate --env=testing --force

echo "API test environment initialized."
