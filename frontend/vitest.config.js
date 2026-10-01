import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

const root = dirname(fileURLToPath(import.meta.url))

export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': resolve(root, 'src') } },
  define: {
    'import.meta.env.VITE_API_BASE_URL': JSON.stringify('http://api.test/api'),
    'import.meta.env.VITE_INBOXSDK_APP_ID': JSON.stringify('sdk_test'),
  },
  test: {
    environment: 'happy-dom',
    include: ['tests/**/*.test.js'],
  },
})
