# Balances CMP

Balances metalúrgicos reconciliados para las plantas de Compañía Minera del Pacífico.

## Arquitectura

| Pieza | Dónde | Qué hace |
|---|---|---|
| API Laravel 13 (PHP 8.3) | `app/`, `routes/api.php` | Auth (tokens), sesiones de balance, diagnóstico, analytics, usuarios y export Excel. Las rutas v1 (`/api/...`) siguen activas; la app usa `/api/v2/...`. |
| SPA `web/` | Vue 3 + Vite + Tailwind + Pinia | La interfaz completa. Se compila a `public/app-build` y la sirve `resources/views/app.blade.php` en toda ruta no-API (`routes/web.php`). |
| Solver Flask | EC2, fuera de este repo (`FLASK_API_URL`) | Reconciliación, diagnóstico de errores gruesos y datos para el Excel. |
| Export Excel | `public/Export/excelnode.js` | Script Node (Node 10 en el servidor) que llena la plantilla del proceso con `xlsx-populate`. Es la única razón del `package.json` de la raíz. |

Las rutas antiguas de la app Vue 2 (`/balances/Listado`, `/dashboard/...`, `/admin/users`, `/v3/...`) redirigen a sus equivalentes.

## Desarrollo

```bash
composer install
cd web && npm ci && npm run dev     # Vite hot (Node 22)
php artisan serve
```

El entorno local con SQLite, el túnel al solver y los datos de prueba se describen en [docs/desarrollo-local.md](docs/desarrollo-local.md).

## Build y checks

```bash
cd web && npx vue-tsc --noEmit && npx vitest run && npx vite build   # → public/app-build
php artisan test
npm ci && node public/Export/addImages.test.js                        # raíz: solo xlsx-populate
```

CI (`.github/workflows/ci.yml`) corre lo mismo: PHPUnit Unit, `web/` (tipos, tests y build) y el test del Excel.

## Deploy

- `next` → v2.sondek.cl (`/home/ubuntu/minera-v2`), con `.github/workflows/deploy-v2.yml`.
- `Prod` → www.sondek.cl (`/home/ubuntu/minera`), con su propia copia del script.

Ambos workflows hacen `git reset --hard` y luego ejecutan `scripts/deploy.sh`. El script corre composer, compila `web/` con Node 22 (`/opt/node22`), borra los builds viejos de Vue 2 y frontend-v3, migra, ejecuta `balance-events:backfill`, regenera las cachés y recarga PHP-FPM.

En la raíz nunca se ejecuta `npm install` durante el deploy: el Excel usa los `node_modules` que ya están en el servidor.
