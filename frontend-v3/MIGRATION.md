# Vue 2 → Vue 3 migration guide (frontend-v3)

Port each screen of the Vue 2 app (`resources/js/src`, bootstrap-vue + Vuexy v7) to this app
(Vue 3.5 + Vuetify 3, Vuexy v10.12.1). Goal: **same behavior and data**, Vuetify look, no purple.

## Rules
- Behavior parity: same API calls (`/api/...`), same fields, validations, permissions, messages,
  edge cases. Read the old component fully before writing the new one. Don't change the backend.
- Routes are file-based (`resources/js/pages`). Keep the old URL paths, e.g.
  `pages/balances/Listado.vue` → `/v3/balances/Listado`. Query params stay the same (`?balance_id=`).
- Permissions: `definePage({ meta: { action, subject } })` with the old `meta.action` + `meta.resource`
  (resource → subject). Public pages: `meta: { layout: 'blank', public: true }`.
- HTTP: `$api('/balances/get_listado')` (ofetch, base `/api`, Bearer token, 401 → login).
  ofetch returns the body directly (old axios code used `response.data`). Errors: `e.response.status`, `e.data`.
  Downloads/blobs: `$api(url, { responseType: 'blob' })`.
- i18n: same keys as the old app (`plugins/i18n/locales/es.json|en.json`, copied from the old app).
  Use `const { t } = useI18n()` / `$t()` in templates. Add new keys to both files.
- Toasts: `const { success, error, warning, info } = useNotify()` (replaces vue-toastification).
- Users: `useCookie('userData').value` (`{ id, fullName, email, role, ability }`); abilities via `useAbility()` / `$can`.
- UI: Vuetify components (VCard, VRow/VCol, VBtn, VDataTable, VDialog, VSelect/AppSelect, AppTextField,
  AppDateTimePicker…). Look at `resources/js/@core/components` before adding anything.
  Theme colors via Vuetify (`primary` is CMP blue); dark mode must work (don't hardcode light colors).
  Spanish copy as in the old app; no emoji in UI text.
- Auto-imports: vue, vue-router, pinia, @vueuse/core, composables/, utils/, @core/utils & composables.
- Components shared between pages go in `resources/js/views/<area>/` (not in pages/).
- New npm deps: `npm install <pkg>@<exact-version>` inside frontend-v3 (commit package.json + lock).
- Commit only your own files; don't reformat others.

## Local test environment
- One-time per checkout (worktrees lack untracked files):
  `ln -s /Users/franciscocastro/Documents/projects/Minera/minera/vendor vendor` and
  `ln -s /Users/franciscocastro/Documents/projects/Minera/minera/.env .env` at the repo root;
  `cd frontend-v3 && npm install && npm run build:icons`.
- Build: `cd frontend-v3 && npx vite build` (→ `public/v3-build`). Must pass with no errors.
- Serve (SQLite copy of anonymized prod data, Flask solver via an SSH tunnel on :18080 already open):
  `/private/tmp/claude-501/-Users-franciscocastro-Documents-projects-Minera/d239a82c-89f5-43b5-9f96-6bf4c471e0e7/scratchpad/v3dev/serve.sh <repo-root> <port>` (run in background) → http://127.0.0.1:<port>/v3/
- Login: `user43@example.com` / `Prueba-1234!` (admin). Real data: balance 53 (datos_entrada 2541).
- Compare with the old app on the same server: http://127.0.0.1:<port>/ (needs `npm run prod` at the
  repo root once to build it; same login).
- Browser checks with the chrome-devtools tools (use an isolatedContext); check the console for errors.
