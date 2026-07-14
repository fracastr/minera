# Minera

Sistema web para el cálculo, ajuste y trazabilidad de **balances de masa metalúrgicos** en procesos de mina-planta de hierro (magnetita/hematita). Reemplaza planillas Excel dispersas por un flujo controlado, con roles, historial y exportación estandarizada, para operaciones organizadas jerárquicamente en **Empresa → Valle → Proceso** (p. ej. valles de Copiapó, Huasco y Elqui, con procesos como Puerto, CNN, Planta Magnetita, Los Colorados, Pellet, Elqui, Pleito).

## El problema que resuelve

En una planta de procesamiento de mineral de hierro, cada cierto período (quincenal) se necesita **cuadrar un balance de masa**: cuánto mineral entró y salió de cada nodo del proceso (stockpiles, chancado, concentradora, puerto, etc.), con qué ley de hierro (Fet, y en algunos procesos también FeMag), y cómo se reparten las diferencias entre lo medido en terreno y lo que exige la coherencia física del proceso (lo que entra = lo que sale + variación de inventario).

Hacer esto a mano en Excel es lento, propenso a errores y difícil de auditar (quién corrió qué balance, con qué datos, cuándo). **Minera** digitaliza ese flujo:

1. Un operador sube el archivo de mediciones de un proceso/quincena.
2. La app delega el cálculo numérico (reconciliación/ajuste del balance) a un motor externo y arma tablas editables (mediciones, restricciones, inventarios, balance por nodos).
3. El usuario ajusta valores dentro de restricciones definidas y vuelve a correr el balance las veces que necesite.
4. El balance final se guarda con su historial (quién, cuándo, para qué proceso) y puede exportarse a un Excel con formato estándar por planta, publicado automáticamente en Google Drive.

## Stack técnico

**Backend**
- PHP 7.3/8.0 · Laravel 8
- Laravel Sanctum — autenticación por token para SPA
- Fruitcake Laravel CORS
- Maatwebsite/Excel — importación de planillas
- `nao-pon/flysystem-google-drive` + `google/apiclient` — disco de Storage sobre Google Drive
- `symfony/process` — invocación de un script Node.js externo para generar Excel
- AWS SDK PHP (S3 disponible como filesystem alternativo)
- PHPUnit (scaffold de tests)

**Frontend**
- Vue 2.6 + Vue Router + Vuex, sobre la base de la plantilla de administración **Vuexy** (Bootstrap-Vue)
- `ag-grid-vue` — tablas de datos editables (el corazón de la UI de balances)
- ApexCharts / Chart.js / ECharts, FullCalendar, vee-validate, vue-i18n, vue-form-wizard
- `@casl/ability` + `@casl/vue` — permisos por rol (admin / operator / viewer), reflejando las mismas reglas del backend
- Axios con interceptores propios (token Bearer + CSRF + manejo global de 401/403/422/500)
- Laravel Mix / Webpack para el build

**Integraciones externas**
- **API Flask (Python)**, vía `FLASK_API_URL`: motor numérico que resuelve el balance de masa (`/get_balance`, `/correr_balance`, `/paint_tables`, `/get_excel`). Este repo no incluye ese servicio; solo lo consume por HTTP.
- **Script Node.js** (`public/excelnode.js`), invocado como proceso hijo: genera el archivo Excel final con la plantilla correspondiente al proceso.
- **Google Drive API**: sube el Excel generado y devuelve un link para compartir.

> Nota: gran parte de `resources/js/src/views` (chat, e-commerce, invoice, email, calendario, componentes UI, etc.) corresponde a las páginas de demostración de la plantilla Vuexy y no está conectada a lógica de negocio real. Los módulos propios del proyecto son `views/balances`, `views/admin` y la capa de autenticación.

## Arquitectura y módulos principales

### Dominio (jerarquía de negocio)
`Empresas` → `Valles` → `Procesos` → `Balances` / `Datos_entrada`

- **Empresas**: razón social + RUT.
- **Valles**: agrupación geográfica/operacional dentro de una empresa.
- **Procesos**: planta o etapa específica dentro de un valle; guarda en JSON los **componentes** químicos que aplican (Fet solo, o Fet + FeMag), lo que determina dinámicamente cuántas columnas tienen las tablas del balance.
- **Datos_entrada**: snapshot editable (JSON) de mediciones, restricciones, jerarquías e inventarios de una corrida de balance en curso.
- **Balances**: registro histórico de un balance ya guardado (nombre, tipo, proceso, usuario, fecha).

### Autenticación y autorización (`AuthController`, `UserController`, `User`, `UserAbilityService`)
- Login con Sanctum (token Bearer), rate limiting independiente por IP y por cuenta (`RouteServiceProvider::configureRateLimiting`), recuperación de contraseña por correo, política de contraseña fuerte (`StrongPassword`), sesiones con expiración por inactividad (`CheckSessionTimeout`), CSRF y cabeceras de seguridad estrictas (`AddSecurityHeaders`).
- Tres roles (`admin`, `operator`, `viewer`) con habilidades definidas una sola vez en `UserAbilityService` y consumidas tanto por middleware de rutas (`EnsureUserHasBalancePermission`, `EnsureUserIsAdmin`) como por CASL en el frontend, evitando que las reglas de permisos diverjan entre capas.
- CRUD de usuarios restringido a administradores (`views/admin/Users.vue` + `UserController`).

### Módulo de Balances (`BalancesController`, `BalanceFormWizard.vue`, `Balances.vue`)
- **Wizard de 3 pasos** (valle → proceso → balance) que arma el contexto antes de subir el archivo.
- **Importación** (`import`): sube el Excel, lo reenvía al motor Flask, y transforma la respuesta en 4 tablas independientes renderizadas con ag-Grid:
  - *Mediciones*: valores medidos vs. calculados por el balance, por flujo.
  - *Restricciones*: cotas superior/inferior aceptadas por variable y su jerarquía de ajuste.
  - *Balance por nodos*: TMS y finos por nodo del proceso.
  - *Inventarios*: TMH/TMS inicial, final y delta por stock, con humedad y componentes.
- **Recalcular** (`correr_balance`): toma las ediciones manuales del usuario, las reenvía al motor de cálculo y refresca las 4 tablas — permite iterar sin volver a subir el archivo original.
- **Pintado de filas** (`paint_tables`): resalta en la UI qué filas quedaron dentro/fuera de tolerancia tras el ajuste.
- **Guardado** (`save_balance`): persiste el balance final asociado a usuario/proceso, dejando trazabilidad de quién lo generó.
- **Listado** (`get_listado`): historial de balances con resolución del usuario creador (incluso para registros antiguos sin relación directa, vía parsing del nombre del archivo almacenado).

### Exportación a Excel (`UtilsController::getExcel`)
Pipeline de tres saltos en una sola llamada: pide los datos al motor Flask → invoca un proceso Node.js (`excelnode.js`) que arma el `.xlsx` con la plantilla del proceso correspondiente → sube el resultado a Google Drive con un nombre normalizado (`valle_proceso_usuario_timestamp.xlsx`) y devuelve el link para compartir.

## Cómo levantar el proyecto localmente

### Requisitos
- PHP 7.3+ u 8.0, Composer
- Node.js + npm
- MySQL (u otro motor soportado por Eloquent)
- Acceso a un servicio Flask que exponga los endpoints de cálculo (`FLASK_API_URL`) para que los flujos de balance funcionen end-to-end
- Node ejecutable accesible en `NODEPATH` y credenciales de Google Drive si se quiere probar la exportación a Excel

### Backend
```bash
composer install
cp .env.example .env   # si no existe, crear .env con las variables listadas abajo
php artisan key:generate
php artisan migrate --seed   # crea tablas y un usuario admin inicial (AdminUserSeeder)
php artisan serve
```

### Frontend
```bash
npm install
npm run dev      # build de desarrollo
npm run watch    # build con watch
npm run hot      # dev server con hot reload (webpack-dev-server)
npm run production
```

### Variables de entorno relevantes
Además de las estándar de Laravel (`APP_KEY`, `DB_*`, `MAIL_*`, `SESSION_*`), este proyecto usa:

| Variable | Uso |
|---|---|
| `FLASK_API_URL` | Base URL del motor de cálculo del balance |
| `NODEPATH` | Ruta al ejecutable de Node usado por `Symfony\Process` para generar el Excel |
| `GOOGLE_DRIVE_CLIENT_ID` / `GOOGLE_DRIVE_CLIENT_SECRET` / `GOOGLE_DRIVE_REFRESH_TOKEN` / `GOOGLE_DRIVE_FOLDER_ID` | Credenciales OAuth para el disco `google` (Storage) |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_BUCKET` / `AWS_DEFAULT_REGION` | Disco S3 alternativo |
| `LOGIN_RATE_LIMIT_MAX_ATTEMPTS` / `LOGIN_RATE_LIMIT_IP_MAX_ATTEMPTS` / `LOGIN_RATE_LIMIT_DECAY_SECONDS` | Configuración fina del throttling de login |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios habilitados para auth por cookie/token con Sanctum |

## Tests

El repo trae únicamente el scaffold por defecto de Laravel (`tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`); no hay tests de negocio implementados todavía. Para correrlos:

```bash
php artisan test
# o
vendor/bin/phpunit
```

## Highlights

- **Orquestación de un cálculo de balance de masa en varias capas**: Laravel no calcula el balance — coordina la subida del archivo, la llamada al motor numérico externo, y la traducción de esa respuesta en 4 estructuras de tabla (`BalancesController::createTable*`) cuya forma cambia dinámicamente según si el proceso mide 2 o 3 componentes químicos (Fet, o Fet + FeMag), permitiendo además reejecutar el cálculo con ediciones manuales sin perder el estado guardado.
- **Pipeline de exportación de 3 saltos**: una sola acción del usuario dispara una llamada al servicio de cálculo, la ejecución de un proceso Node.js externo vía `Symfony\Process` para maquetar el Excel con la plantilla exacta de cada planta, y la subida automática a Google Drive vía API con OAuth refresh token, devolviendo un link listo para compartir.
- **Endurecimiento de seguridad más allá del scaffold de Laravel**: rate limiting de login diferenciado por IP y por cuenta, validación de contraseñas contra el dataset de HaveIBeenPwned mediante *k-anonymity* (`StrongPassword`), CSP y cabeceras de seguridad estrictas en producción, expiración de sesión por inactividad, y un sistema de habilidades por rol compartido explícitamente entre backend (middleware) y frontend (CASL) para que ambos lados nunca diverjan.
