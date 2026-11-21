# Desarrollo local

Laravel corre sobre una copia SQLite de los datos y el SPA `web/` se sirve con Vite en modo hot. No hace falta MySQL ni RDS.

## Una vez por checkout

Los worktrees no traen los archivos no versionados. Desde la raíz del repo:

```bash
cp -cR <checkout-principal>/vendor vendor     # clon APFS; o `composer install`
ln -s <checkout-principal>/.env .env
(cd web && npm ci)
```

## Variables para trabajar con SQLite

Exporta estas variables antes de `php artisan ...`. Así no se toca la base de datos del `.env`:

```bash
export APP_ENV=local MAIL_MAILER=log QUEUE_CONNECTION=sync \
  DB_CONNECTION=sqlite DB_DATABASE=/ruta/a/dev.sqlite \
  SESSION_DRIVER=array CACHE_STORE=array CACHE_DRIVER=array \
  FLASK_API_URL=http://127.0.0.1:18080/flaskapi
```

- `dev.sqlite` es una copia anonimizada de producción. Si es nueva, créala con `php artisan migrate`.
- Usa una copia por servidor (por ejemplo, `dev-<puerto>.sqlite`) para que dos sesiones no se pisen.
- El solver Flask corre en el EC2. Para usarlo, abre un túnel SSH hacia el puerto `18080`. Sin el túnel, todo funciona menos Correr, Diagnóstico y Exportar.
- El Excel usa `NODEPATH`, el Node con `xlsx-populate` instalado: `npm ci` en la raíz.

## Servir

```bash
php artisan serve --port=8000      # API + vista app.blade.php
cd web && npm run dev              # Vite hot; escribe public/app.hot y la vista lo usa
```

Para probar el build de producción: `cd web && npm run build` (escribe en `public/app-build`) y borra `public/app.hot`.

## Datos de prueba

- Login admin de la copia local: `user43@example.com` / `Prueba-1234!`.
- Balance real de referencia: balance 53 (`datos_entrada` 2541).

## Checks antes de un PR

```bash
cd web && npx vue-tsc --noEmit && npx vitest run && npx vite build
php artisan test                    # PHPUnit; los tests Feature usan SQLite en memoria
node public/Export/addImages.test.js
```
