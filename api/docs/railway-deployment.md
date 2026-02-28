# Railway Deployment Configuration

This repository now includes Railway config files so services can be bootstrapped from source control.

## Files

- `railway.json` → API service (web)
- `railway.worker.json` → queue worker service
- `railway.scheduler.json` → scheduler service

## Service topology

Create one Railway project with:

1. API service (uses `railway.json`)
2. Worker service (use `railway.worker.json` content)
3. Scheduler service (use `railway.scheduler.json` content)
4. MySQL plugin/service
5. Redis plugin/service

## Environment variables (minimum)

Set these on API, worker, and scheduler services:

- `APP_KEY`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `DB_CONNECTION=mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`
- `QUEUE_CONNECTION=redis`

For restricted Composer/GitHub environments (build-time):

- `COMPOSER_REPO_PACKAGIST`
- `COMPOSER_GITHUB_OAUTH_TOKEN` or `COMPOSER_AUTH`
- `COMPOSER_GITHUB_MIRROR`

## Notes

- `railway.json` runs `php deploy.sh` as a pre-deploy step for migrations/bootstrapping.
- Worker and scheduler configs intentionally skip `preDeployCommand` to avoid duplicate migration runs.
- If you use separate repos/services, copy the corresponding JSON content into each service's Railway configuration.
