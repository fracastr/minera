# frontend-v3

Vue 3 / Vuetify 3 (Vuexy v10.12.1, Vue + Laravel starter kit) shell for the frontend migration.
It lives next to the current Vue 2 app and is served by Laravel at `/v3` (`routes/web.php`,
`resources/views/application-v3.blade.php`). It talks to the same `/api` with Sanctum tokens.

Needs Node >= 20.19 (Vite 7). The current app keeps building with Laravel Mix from the repo root.

```bash
cd frontend-v3
npm install
npm run build:icons   # once after install (npm 11 skips the postinstall)
npx vite build        # -> public/v3-build (gitignored)
npx vite              # dev server; writes public/v3.hot so Laravel loads assets from Vite
```

Not deployed yet: the prod box runs Node 10 and `scripts/deploy.sh` does not build this folder.
