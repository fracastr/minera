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

echo "==> prune JS chunks older than 7 days"
# Keep anything the live app.js still references: a silent Mix failure leaves the old bundle serving.
find public/js/chunks -type f -mtime +7 -print0 | while IFS= read -r -d '' f; do
  hash="$(basename "$f" | cut -d. -f2)"
  if grep -qF "$hash" public/js/app.js; then
    continue
  fi
  sudo -n rm -f "$f"
done

echo "==> migrate"
php_artisan migrate --force

echo "==> backfill balance events"
php_artisan balance-events:backfill || true

echo "==> caches"
php_artisan optimize:clear
php_artisan config:cache
php_artisan route:cache
php_artisan view:cache

echo "==> reload PHP-FPM (opcache)"
sudo -n systemctl reload php8.3-fpm

echo "==> maintenance off"
php_artisan up
trap - EXIT

echo "==> deploy finished $(date -u +%Y-%m-%dT%H:%M:%SZ)"
