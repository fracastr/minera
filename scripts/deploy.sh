#!/usr/bin/env bash
# Production deploy for /home/ubuntu/minera on the sondek EC2.
# Assumes the tree is already at origin/Prod (GitHub Actions does git reset first).
set -euo pipefail

APP_DIR="/home/ubuntu/minera"

cd "$APP_DIR"

php_artisan() {
  sudo -n php artisan "$@"
}

bring_up() {
  php_artisan up || true
}

trap bring_up EXIT

echo "==> maintenance on"
php_artisan down --retry=60 || true

echo "==> composer"
sudo -n composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist

echo "==> frontend (Laravel Mix / Node 10 on this box)"
sudo -n npm run prod

echo "==> migrate"
php_artisan migrate --force

echo "==> backfill balance events"
php_artisan balance-events:backfill || true

echo "==> caches"
php_artisan optimize:clear
php_artisan config:cache
php_artisan route:cache
php_artisan view:cache

echo "==> reload Apache (mod_php opcache)"
sudo -n apachectl graceful

echo "==> maintenance off"
php_artisan up
trap - EXIT

echo "==> deploy finished $(date -u +%Y-%m-%dT%H:%M:%SZ)"
