#!/usr/bin/env bash
# Deploy for the sondek EC2: /home/ubuntu/minera (www, branch Prod) or /home/ubuntu/minera-v2 (v2, branch next).
# Each checkout runs its own copy of this script; this one (next) builds only web/.
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

if [ ! -f webpack.mix.js ]; then  # only on branches after the S7 cut; Prod (www) still builds Mix into these dirs
echo "==> remove build leftovers of the old frontends"
# git reset --hard keeps ignored/untracked dirs: the Vue 2 (Mix) and frontend-v3 builds would stay served.
for d in public/js public/css public/fonts public/v3-build public/hot public/v3.hot frontend-v3; do
  if [ -e "$d" ]; then sudo -n rm -rf -- "$d"; fi
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
