import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  plugins: [
    // Built into Laravel's public/ next to the old apps; resources/views/app.blade.php reads the manifest.
    laravel({
      input: ['src/main.ts'],
      publicDirectory: '../public',
      buildDirectory: 'app-build',
      hotFile: '../public/app.hot',
      refresh: false,
    }),
    vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
    tailwindcss(),
  ],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  test: { environment: 'jsdom' },
})
