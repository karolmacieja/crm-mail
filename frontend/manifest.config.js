import { readFileSync } from 'node:fs'
import { defineManifest } from '@crxjs/vite-plugin'

const pkg = JSON.parse(readFileSync(new URL('./package.json', import.meta.url), 'utf8'))

/**
 * Manifest V3 definition. CRXJS compiles this into dist/manifest.json and
 * rewrites the source paths below to the bundled output files.
 *
 * The API origin is injected from VITE_API_BASE_URL so host_permissions only
 * grant access to Gmail and *your* backend (no broad "<all_urls>").
 */
export function createManifest(env) {
  const apiOrigin = new URL(env.VITE_API_BASE_URL).origin

  return defineManifest({
    manifest_version: 3,
    name: 'Gmail CRM',
    short_name: 'Gmail CRM',
    description: 'Lightweight CRM inside Gmail: contacts, deal status and follow-up reminders next to every email.',
    version: pkg.version,
    minimum_chrome_version: '114',
    icons: {
      16: 'icons/icon-16.png',
      32: 'icons/icon-32.png',
      48: 'icons/icon-48.png',
      128: 'icons/icon-128.png',
    },
    action: {
      default_title: 'Gmail CRM',
      default_popup: 'src/extension/popup/index.html',
      default_icon: {
        16: 'icons/icon-16.png',
        32: 'icons/icon-32.png',
      },
    },
    background: {
      service_worker: 'src/extension/background/index.js',
      type: 'module',
    },
    content_scripts: [
      {
        matches: ['https://mail.google.com/*'],
        js: ['src/extension/content/content.js'],
        run_at: 'document_end',
      },
    ],
    permissions: ['storage', 'scripting'],
    host_permissions: ['https://mail.google.com/*', `${apiOrigin}/*`],
    web_accessible_resources: [
      {
        // pageWorld.js: InboxSDK MAIN-world script. Icons: rendered by Gmail
        // itself (sidebar tab, nav item, app toolbar) so Gmail must be able to load them.
        resources: ['pageWorld.js', 'icons/*.png'],
        matches: ['https://mail.google.com/*'],
      },
    ],
  })
}
