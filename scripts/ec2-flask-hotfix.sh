#!/usr/bin/env bash
set -euo pipefail
cd /home/ubuntu/minera

if ! grep -q "'flask' =>" config/services.php; then
  python3 << 'PY'
from pathlib import Path
p = Path("config/services.php")
text = p.read_text()
needle = "    ],\n\n];"
insert = """    ],\n\n    'flask' => [\n        'url' => env('FLASK_API_URL'),\n    ],\n\n    'node' => [\n        'path' => env('NODEPATH'),\n    ],\n\n];"""
if needle not in text:
    raise SystemExit("services.php pattern not found")
p.write_text(text.replace(needle, insert, 1))
print("patched services.php")
PY
else
  echo "services.php already has flask config"
fi

sed -i "s/env('FLASK_API_URL')/config('services.flask.url')/g" app/Http/Controllers/BalancesController.php app/Http/Controllers/UtilsController.php
sed -i "s/env('NODEPATH')/config('services.node.path')/g" app/Http/Controllers/UtilsController.php

php artisan config:cache
php artisan route:cache

php -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "flask url: ".config("services.flask.url").PHP_EOL;
echo "node path: ".config("services.node.path").PHP_EOL;
echo "import url: ".config("services.flask.url")."/get_balance".PHP_EOL;
'

echo "hotfix applied"
