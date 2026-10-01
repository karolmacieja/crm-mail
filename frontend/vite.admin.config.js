import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'

const root = dirname(fileURLToPath(import.meta.url))

/**
 * Master Admin web panel: a regular SPA for the browser (e.g. https://app.domena.pl).
 *   npm run dev:admin    → http://localhost:5173 (listed in SANCTUM_STATEFUL_DOMAINS)
 *   npm run build:admin  → dist-admin/ (serve with an SPA fallback to index.html)
 */
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, root, 'VITE_')
  try {
    new URL(env.VITE_API_BASE_URL)
  } catch {
    throw new Error(`Invalid VITE_API_BASE_URL "${env.VITE_API_BASE_URL ?? ''}" (see .env.example).`)
  }

  return {
    root: resolve(root, 'src/admin'),
    envDir: root,
    // .htaccess for Apache/LiteSpeed hosting (copied into dist-admin).
    publicDir: resolve(root, 'public-admin'),
    plugins: [vue()],
    resolve: { alias: { '@': resolve(root, 'src') } },
    css: { postcss: resolve(root, 'postcss.config.js') },
    build: {
      outDir: resolve(root, 'dist-admin'),
      emptyOutDir: true,
      sourcemap: mode !== 'production',
    },
    server: { port: 5173, strictPort: true },
  }
})
