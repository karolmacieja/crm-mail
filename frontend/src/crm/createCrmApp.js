import { createApp } from 'vue'
import { createCrmRouter } from '@/crm/router.js'
import { watchCrmSession } from '@/crm/session.js'
import { useAuthStore } from '@/crm/stores/auth.js'
import { initLocale } from '@/shared/lib/i18n.js'
import CrmApp from './CrmApp.vue'

/**
 * Build the full-page CRM app (Gmail custom route). The caller mounts it,
 * e.g. into a shadow root via mountIsolated().
 *
 * @param {{ pinia: import('pinia').Pinia, gmail: object, initialPath?: string }} options
 *   gmail: helpers from the content script (openThread, searchEmail, ...), provided as 'gmail'.
 */
export function createCrmApp({ pinia, gmail, initialPath }) {
  const app = createApp(CrmApp)
  app.use(pinia)
  app.use(createCrmRouter(pinia, { initialPath }))
  app.provide('gmail', gmail)

  initLocale()
  useAuthStore(pinia).init()
  watchCrmSession(pinia)

  return app
}
