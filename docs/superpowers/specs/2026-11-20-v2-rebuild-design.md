# Balances CMP v2 — diseño de la reconstrucción

Fecha: 2026-11-20 · Rama: `feat/v2-rebuild` (desde `next`) · Destino: https://v2.sondek.cl

## 1. Intención

Rehacer desde cero la aplicación web de balances de CMP. La v1 (www.sondek.cl) es la base funcional: todo lo que hace la v1 lo hace la v2. Además, la v2 tiene que:

- **ayudar a encontrar qué medición está mala y qué cambiar.** Es el dolor principal del usuario;
- mostrar el balance sobre un **diagrama de proceso interactivo** (SVG con zoom y arrastre, medido / reconciliado / error, desbalance por nodo; la referencia visual es el reconciliador de Simplex SIIR);
- llevar al sistema los gráficos y diagramas que hoy se rellenan a mano en los Excel de salida; el export solo los inserta;
- tener una navegación, una portada y unas pantallas rediseñadas, con marca CMP ("Balances CMP", sin Sondek).

**No cambia cómo se calcula el balance.** El solver Flask/GEKKO sigue siendo la única fuente de los números reconciliados. La IA y el diagnóstico leen y sugieren; nunca escriben resultados.

### Anti-objetivos
- Que la IA modifique datos sin acción explícita del usuario.
- Romper www.sondek.cl: comparte la base MySQL y el servicio Flask.
- Cambiar el objetivo o las restricciones de `balance_core`.

## 2. Restricciones

- **BD compartida con www.** Solo migraciones aditivas (tablas o columnas nuevas, nullable). No renombrar ni borrar.
- **Flask compartido con www.** Solo endpoints nuevos; los existentes no cambian de contrato.
- **Servidor:** t3.small con 2 GB de swap, disco al 83 %. Vite se compila con Node 22 (`/opt/node22`). El Node 10 del sistema sigue para el export Excel (xlsx-populate).
- Los componentes son **Fe T** y, en algunos procesos, **Fe Mag** (`procesos.componentes`). La UI se adapta a 1 o 2 componentes.
- Todas las plantillas de salida (`public/Export/Exportar_<Valle>_<Proceso>.xlsx`) tienen hojas de diagrama hechas con imagen + formas. openpyxl las destruye al re-guardar, así que no se re-guardan con openpyxl.

## 3. Enfoques considerados

| | Enfoque | Resultado |
|---|---|---|
| **A** | Mantener Laravel + Flask + MySQL; frontend nuevo con diseño propio; capa de diagnóstico + IA aditiva | **Elegido.** Menor riesgo, compatible con www, el esfuerzo va a lo que el usuario pidió |
| B | Reescribir todo (Next.js + FastAPI) | Descartado: rehace auth, roles, analytics, correo y Drive, que ya funcionan, y complica la BD compartida |
| C | Rediseñar sobre la v2 actual (Vuexy) | Descartado: el usuario pidió partir de cero; hereda estructura y look de plantilla |

## 4. Stack (decisiones)

| Capa | Decisión | Por qué |
|---|---|---|
| Frontend | Vue 3.5 + Vite 7 + TypeScript, en `web/` (package.json propio) | Equipo y código de dominio ya en Vue (reconciliationQuality.js, flowGraph.js se portan) |
| UI | Tailwind CSS v4 + Reka UI (primitivas headless accesibles) + iconos Lucide | Diseño propio, sin look de plantilla, accesible |
| Estado / datos | Pinia + composables con `ofetch` | Ya usado; sin dependencias extra |
| Tablas editables | AG Grid Community 36 | Edición tipo planilla, ya probado en el proyecto |
| Diagrama | Vue Flow (SVG) + elkjs (layout automático por capas) | Zoom/pan/drag nativos, nodos y aristas personalizables |
| Gráficos | ECharts 6 | Ya usado (Sankey); exporta PNG para el Excel |
| Backend | Laravel 13: endpoints nuevos `/api/v2/*` con payloads numéricos limpios | No depende del orden de claves JSON ni de colDefs del servidor |
| Diagnóstico | Flask: módulo `diagnostico.py` + `POST /diagnostico` (numpy) | Matemática en Python junto al solver; no toca `balance_core` |
| Asistente IA | Laravel → API de Claude (`claude-sonnet-5-5`) detrás de `AI_ASSISTANT_ENABLED` (apagado por defecto) | Explica el diagnóstico y conversa; la decisión es del usuario |
| Excel | Node: xlsx-populate (como hoy) + paso que agrega imágenes PNG vía OOXML (JSZip) | Conserva los dibujos de las plantillas |

Marca: tokens de `cmp-branding` (primario #1C68C9, navy #252A3F, valles Copiapó #B13F95, Huasco #00837A, Elqui #FF6462; Work Sans + Archivo). Modo claro y oscuro.

## 5. Arquitectura de información y navegación

Barra lateral con estas secciones:

1. **Inicio (centro de control):** balances recientes del usuario, balances con mediciones fuera de tolerancia, medidores con error recurrente (tendencia), accesos rápidos "Nuevo balance" y "Continuar último".
2. **Balances:** Listado (filtros por valle, proceso, tipo, período y usuario) y **Nuevo balance**.
3. **Espacio de balance** (`/balances/:id`): la pantalla principal, con pestañas:
   - **Diagrama:** flowsheet interactivo. Es la vista por defecto después de correr.
   - **Datos:** tablas de flujos, tolerancias, inventarios y nodos.
   - **Diagnóstico:** sospechosos ordenados, gráficos de desbalance, asistente IA.
   - **Historial:** corridas, con opción de restaurar.

   Barra superior fija: proceso, período, estado (sin correr / corrido / guardado), acciones Correr · Guardar · Exportar.
4. **Analytics** (admin o `dashboard_access`).
5. **Administración:** usuarios (admin) y diagramas de proceso (admin u operador).

La landing pública (`/`) se rediseña con marca CMP y lleva a login.

Nuevo balance = una sola pantalla: elegir valle → proceso → subir Excel, y entrar directo al espacio de balance. Reemplaza el wizard de 3 pasos, que además tenía un bug de validación.

## 6. Funcionalidad v1 que se conserva (paridad)

Auth (login con rate limit, logout, olvidé/restablecer contraseña con StrongPassword, expiración por inactividad de 120 min), roles admin/operator/viewer + `dashboard_access`, importar Excel, editar mediciones, tolerancias, jerarquía e inventarios, correr, resaltar los flujos de un nodo (paint_tables), guardar (nombre, tipo quincenal/mensual, período con sugerencia de fin), "guardar como nuevo" o "sobrescribir" al reabrir, historial de corridas y restauración, export Excel + Drive, listado, analytics (KPIs, serie con drill-down, por valle, proceso y usuario), CRUD de usuarios, panel de calidad (uso de tolerancia, cierre de nodos), Sankey.

### Defectos de la v1 que se corrigen
- Precisión: la grilla guarda valores completos y solo redondea al mostrarlos.
- Payload v2 = arreglos numéricos por flujo y variable, no objetos de columnas UI.
- Guardar persiste también las ediciones hechas después de la última corrida.
- Los deltas de inventario se recalculan (TMS delta = fin − ini; TMH desde humedad).
- El export requiere `balance:write`, se registra el evento `exported` y se ofrece descarga directa además del link de Drive.
- Logout por POST, solo con el token actual.
- forgot-password no revela si el email existe.
- Errores con causa legible (por ejemplo "el solver no respondió", 422 de restauración).

## 7. Diagrama de proceso interactivo

- **Topología:** `datos_entrada.matriz` (nodos × flujos, +1 entra / −1 sale) + `indices_stock`. Un flujo con un solo extremo es entrada o salida del sistema (nodos fantasma de borde); un flujo de stock se dibuja como inventario.
- **Layout:** elkjs `layered` de izquierda a derecha, automático. Admin/operador pueden arrastrar nodos y guardar el layout **por proceso** en la tabla nueva `proceso_diagramas` (proceso_id único, `layout` JSON {nodo: {x,y}}, `updated_by`, timestamps). Si la topología cambia, los nodos nuevos se ubican con ELK y se conservan los guardados.
- **Vista:**
  - selector Masa / Fe T / Fe Mag (el último solo si hay 2 componentes);
  - toggles Medido, Reconciliado y Error relativo (%);
  - desbalance en unidades o %;
  - flujos fuera de tolerancia en rojo y en el límite en ámbar;
  - etiqueta por flujo: `M 1.234 · R 1.240 · E 0,5 %`;
  - el nodo muestra su desbalance;
  - tooltip con detalle; clic en flujo → panel lateral con valor, tolerancia, uso y diagnóstico, y botón "editar medición";
  - clic en nodo → resalta sus flujos (reemplaza paint_tables en esta vista);
  - zoom, pan, ajustar a pantalla, pantalla completa, exportar SVG y PNG.
- La vista de diagnóstico puede **superponer los sospechosos** en el diagrama (borde pulsante + ranking).

## 8. Diagnóstico de errores gruesos (Flask)

`POST /diagnostico {datos_entrada_id}` devuelve JSON plano (no doble-codificado). Lee `datos_entrada` como los otros endpoints y aplica `update_matriz`. Usa numpy (ya instalado); scipy no es necesario (cuantiles χ² con tabla o Wilson–Hilferty).

- **σ por medición:** σᵢ = tolᵢ·|xᵢ| / (100·1,96), con tol = promedio de tol_inf y tol_sup de `restricciones`. Si la tolerancia es 0, se usa un 5 % relativo por defecto (configurable). Mediciones con TMS = 0 o fijas se excluyen.
- **Balance de masa (lineal):** A = matriz sin flujos excluidos.
  - Residuo nodal r = A·x_med; V = diag(σ²); H = A·V·Aᵀ.
  - **Prueba global:** γ = rᵀH⁻¹r ~ χ²(rango A).
  - **Prueba nodal:** zⱼ = rⱼ/√Hⱼⱼ.
  - **Prueba por medición:** ajuste WLS â = V·Aᵀ·H⁻¹·r; Wₐ = V·Aᵀ·H⁻¹·A·V; zᵢ = âᵢ/√Wₐᵢᵢ.
  - Umbral: Šidák al 95 %.
- **Eliminación serial:** se quita el sospechoso con mayor |z|, se recalcula, y se repite hasta pasar la prueba global (máx. 5). Por cada sospechoso se reporta la **corrección estimada** (valor que cierra el balance) y la **reducción de γ**.
- **Finos (Fe T / Fe Mag):** la misma prueba sobre el balance de finos (TMS·ley/100), linealizada en el punto medido; las leyes se reportan como sospechosas con su corrección estimada de ley.
- **Salida:** `global {gamma, gl, critico, pasa}`, `nodos[] {nodo, z, sospechoso}`, `sospechosos[] {flujo, variable: tms|ley_k, z, valor_medido, valor_sugerido, reduccion_gamma, accion}`. `accion` ∈ `revisar_medicion | corregir_valor | ampliar_tolerancia`.
- Los nombres van alineados con `flujos`, `nodos` y `tags`.

**Tendencia histórica (Laravel):** para cada flujo y proceso, el ajuste % de las últimas N corridas guardadas (de los snapshots de `balance_corridas` de balances guardados del mismo proceso). Un medidor con sesgo persistente (mismo signo en ≥ 3 de las últimas 4, |ajuste| > 50 % de tolerancia) se marca "sesgo recurrente". Se calcula al vuelo con caché de 10 min.

**UI del diagnóstico:**
- tarjeta de estado global (pasa / no pasa);
- ranking de sospechosos con barras de |z|;
- gráfico de desbalance por nodo (barras, unidades y %);
- gráfico de uso de tolerancia por flujo (barras ordenadas, umbral 80/100 %);
- sparkline de tendencia por medidor;
- cada sospechoso tiene "Ver en diagrama", "Aplicar sugerencia" (escribe el valor o tolerancia sugerida en la grilla sin correr; el usuario corre) y "Descartar".

## 9. Asistente IA

- `POST /api/v2/balances/{datos_entrada_id}/asistente {mensajes[]}` (Laravel, `balance:write`, throttle 20/min por usuario). Llama a la API de Claude con:
  - contexto compacto: proceso, componentes, diagnóstico, tendencia y top 30 flujos por |ajuste|;
  - system prompt que fija el rol (ingeniero de balances), el idioma (español) y la regla: "nunca inventes números; cita solo valores del contexto".
- **Tools** del modelo: `proponer_accion {flujo, variable, tipo, valor}`. El frontend muestra las propuestas como tarjetas con "Aplicar", que usa el mismo camino que "Aplicar sugerencia".
- Sin streaming en la v1 del asistente (respuesta completa) para simplificar.
- `AI_ASSISTANT_ENABLED=false` y `ANTHROPIC_API_KEY` en `.env`. Con el flag apagado la UI muestra el diagnóstico estadístico sin chat. **Pendiente: OK de CMP para enviar datos de producción a Anthropic.**

## 10. Excel de salida

- Se mantiene el flujo actual: Flask `/get_excel` → `excelnode.js` llena "Utilidad" y "Datos Extra" en la plantilla.
- **Nuevo:** el frontend envía PNGs generados en el navegador junto a la solicitud de export (`POST /api/v2/balances/{id}/export`, multipart):
  - diagrama del proceso;
  - desbalance por nodo;
  - uso de tolerancia.
- `excelnode.js` (post-paso con JSZip, ya dependencia de xlsx-populate) agrega:
  - una hoja nueva **"Diagrama Sistema"** con el diagrama;
  - una hoja **"Gráficos Sistema"** con los gráficos.

  Lo hace escribiendo `xl/media`, `xl/drawings/drawingN.xml`, los rels y el content-types. Las hojas de diagrama hechas a mano quedan intactas, y el usuario puede eliminarlas cuando confíe en las nuevas.
- Respuesta: descarga directa del archivo + link de Drive (si Drive falla, la descarga funciona igual).
- Gráficos nativos editables de Excel: **fuera de alcance** por ahora (las imágenes resuelven el "pegar en la hoja correspondiente").

## 11. Seguridad y operación

- El asistente y el export pasan por `security-reviewer`.
- `flask_minera/db_mysql.py` tiene credenciales de RDS en el código. En el PR del diagnóstico, Flask pasa a leerlas de variables de entorno, con el mismo valor (sin rotar). **La rotación de la contraseña afecta a www y se coordina con el usuario.**
- **Deploy:**
  - `deploy.sh` compila `web/` con Node 22 hacia `public/app-build`;
  - la vista Blade `app` sirve el SPA en todas las rutas no-API de v2;
  - al hacer el corte se eliminan de `next` el Vue 2 (`resources/js`, Mix) y `frontend-v3/`; www sigue en `Prod`.
- **Datos locales:** SQLite temporal con datos de prueba (ver MIGRATION.md); los procesos se siembran con el seeder nuevo `ValleProcesoSeeder` (solo local/test).

## 12. Entregas (rebanadas verticales, en orden)

| # | Rebanada | Contenido | Hecho cuando |
|---|---|---|---|
| S0 | Base | `web/` scaffold, design tokens, shell + navegación, login / logout / olvidé / restablecer, guard de roles, deploy a v2 | Se puede entrar a v2.sondek.cl y navegar el shell |
| S1 | Balance núcleo | API v2 limpia; Nuevo balance (subir Excel); espacio de balance pestaña Datos (grillas), Correr, Guardar, Reabrir (nuevo/sobrescribir), Historial/Restaurar; Listado | Paridad del flujo principal con v1 |
| S2 | Diagrama | Vue Flow + ELK, toggles, colores por tolerancia, panel lateral, export SVG/PNG, layout guardado por proceso | El diagrama de la referencia funciona con datos reales |
| S3 | Diagnóstico | Flask `/diagnostico` + tests numpy, tendencia en Laravel, pestaña Diagnóstico con gráficos y "Aplicar sugerencia", superposición en diagrama | Con una medición alterada a propósito, aparece primera en el ranking |
| S4 | Asistente IA | Endpoint con Claude + tools, chat en Diagnóstico, flag | Con flag on, explica y propone acciones aplicables |
| S5 | Excel | Export con PNGs en hojas nuevas, descarga directa + Drive | El Excel abre en Excel/LibreOffice con dibujos originales intactos |
| S6 | Inicio, Analytics, Admin, Landing | Centro de control, analytics rediseñado, CRUD usuarios, landing CMP | Paridad completa |
| S7 | Corte | Quitar Vue 2 y frontend-v3 de `next`, limpiar deploy | v2.sondek.cl sirve solo la app nueva |

Cada rebanada: plan → TDD (PHPUnit Feature con SQLite, Vitest para lógica TS, pytest para Flask) → revisión (`code-reviewer`, `vue-reviewer` / `php-reviewer` / `python-reviewer`, `security-reviewer` si aplica) → PR a `next` → deploy v2 → verificación en navegador.

## 13. Decisiones tomadas por Claude (el usuario delegó)

Todo lo anterior se decidió de forma autónoma por delegación explícita del usuario, en particular:
- el stack;
- el carpeta `web/`;
- la navegación;
- σ desde tolerancias / 1,96;
- las imágenes en vez de gráficos nativos;
- las hojas nuevas en vez de reemplazar las dibujadas;
- el modelo `claude-sonnet-5-5`;
- el asistente apagado por defecto.

La v2 actual sigue sirviendo en v2.sondek.cl hasta el corte (S7), para no dejar el sitio vacío.
