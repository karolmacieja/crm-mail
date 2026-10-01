import { copyFileSync, existsSync, mkdirSync, readFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { crx } from '@crxjs/vite-plugin'
import { createManifest } from './manifest.config.js'

const root = dirname(fileURLToPath(import.meta.url))
const require = createRequire(import.meta.url)

/**
 * InboxSDK (MV3) injects `pageWorld.js` into Gmail's MAIN world via
 * chrome.scripting.executeScript, so the file must sit at the extension root.
 * Copy it from the installed package into public/ before every dev/build run
 * so it always matches the bundled @inboxsdk/core version.
 */
function inboxSdkPageWorld() {
  return {
    name: 'inboxsdk-page-world',
    buildStart() {
      const source = require.resolve('@inboxsdk/core/pageWorld.js')
      const target = resolve(root, 'public/pageWorld.js')
      mkdirSync(dirname(target), { recursive: true })
      if (!existsSync(target) || !readFileSync(source).equals(readFileSync(target))) {
        copyFileSync(source, target)
      }
    },
  }
}

function readEnv(mode) {
  const env = loadEnv(mode, root, 'VITE_')

  if (!env.VITE_INBOXSDK_APP_ID) {
    throw new Error(
      'VITE_INBOXSDK_APP_ID is missing. Register your app at https://www.inboxsdk.com/register ' +
        'and put the id in extension/.env (see .env.example).',
    )
  }

  try {
    const url = new URL(env.VITE_API_BASE_URL)
    if (mode === 'production' && url.protocol !== 'https:' && url.hostname !== 'localhost') {
      throw new Error('VITE_API_BASE_URL must use https:// for production builds.')
    }
  } catch (error) {
    throw new Error(`Invalid VITE_API_BASE_URL "${env.VITE_API_BASE_URL ?? ''}": ${error.message}`)
  }

  return env
}

export default defineConfig(({ mode }) => {
  const env = readEnv(mode)

  return {
    plugins: [vue(), inboxSdkPageWorld(), crx({ manifest: createManifest(env) })],
    resolve: {
      alias: { '@': resolve(root, 'src') },
    },
    build: {
      target: 'chrome114',
      sourcemap: mode !== 'production',
      emptyOutDir: true,
      // The content script bundles InboxSDK (~1 MB); it's loaded from disk, not the network.
      chunkSizeWarningLimit: 1500,
    },
    server: {
      port: 5173,
      strictPort: true,
      hmr: { port: 5173 },
      // Vite >= 6 restricts dev-server CORS; the extension pages need access.
      cors: { origin: [/^chrome-extension:\/\//] },
    },
  }
})
