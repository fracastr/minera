#!/usr/bin/env bash
# Deploy for the sondek EC2: /home/ubuntu/minera (www, branch Prod) or /home/ubuntu/minera-v2 (v2, branch next).
# Assumes the tree is already reset to its branch (GitHub Actions does git reset first).
set -euo pipefail

APP_DIR="${APP_DIR:-/home/ubuntu/minera}"

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

if [ -f frontend-v3/package.json ]; then  # node_modules may linger after git reset
echo "==> frontend v3 (Vite / Node 22 from /opt/node22; system Node 10 stays for Mix and Excel)"
V3_PATH="/opt/node22/bin:$PATH"
(
  cd frontend-v3
  # npm ci only when the lockfile changed: it is slow on this box.
  lock_sha="$(sha256sum package-lock.json | cut -d' ' -f1)"
  if [ "$(cat node_modules/.lock-sha 2>/dev/null || true)" != "$lock_sha" ]; then
    sudo -n env PATH="$V3_PATH" npm ci --no-audit --no-fund
    echo "$lock_sha" | sudo -n tee node_modules/.lock-sha >/dev/null
  fi
  sudo -n env PATH="$V3_PATH" npx vite build
)

echo "==> prune v3 assets older than 7 days"
# Vite does not empty public/v3-build (it is outside its root); keep whatever the manifest still uses.
find public/v3-build/assets -type f -mtime +7 -print0 | while IFS= read -r -d '' f; do
  if grep -qF "$(basename "$f")" public/v3-build/manifest.json; then
    continue
  fi
  sudo -n rm -f "$f"
done
fi

if [ -f web/package.json ]; then  # Balances CMP v2 SPA (only on branches that have it)
echo "==> frontend v2 web/ (Vite / Node 22 from /opt/node22)"
WEB_PATH="/opt/node22/bin:$PATH"
(
  cd web
  lock_sha="$(sha256sum package-lock.json | cut -d' ' -f1)"
  if [ "$(cat node_modules/.lock-sha 2>/dev/null || true)" != "$lock_sha" ]; then
    sudo -n env PATH="$WEB_PATH" npm ci --no-audit --no-fund
    echo "$lock_sha" | sudo -n tee node_modules/.lock-sha >/dev/null
  fi
  sudo -n env PATH="$WEB_PATH" npx vite build
)

echo "==> prune v2 assets older than 7 days"
# Vite does not empty public/app-build (it is outside its root); keep whatever the manifest still uses.
find public/app-build/assets -type f -mtime +7 -print0 | while IFS= read -r -d '' f; do
  if grep -qF "$(basename "$f")" public/app-build/manifest.json; then
    continue
  fi
  sudo -n rm -f "$f"
done
fi

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
