import { createApp, h } from 'vue'
// `?inline` returns the compiled CSS as a string instead of injecting it into
// Gmail's <head>, so Tailwind's preflight can never restyle Gmail itself.
import styles from '@/shared/styles/tailwind.css?inline'

/**
 * Mount a Vue component inside `host` using a Shadow DOM boundary.
 *
 *   host (plain <div> handed to InboxSDK / Gmail)
 *   └─ #shadow-root
 *      ├─ <style> (our Tailwind build, scoped to this shadow tree)
 *      └─ <div class="gcrm-root"> ← Vue app
 *
 * Gmail's CSS can't reach in and ours can't leak out.
 *
 * @param {HTMLElement} host
 * @param {import('vue').Component} component
 * @param {{ props?: object, pinia: import('pinia').Pinia, provide?: Record<string, unknown> }} options
 *   `props` may be a reactive() object: the component re-renders when it changes.
 */
export function mountIsolated(host, component, { props = {}, pinia, provide = {} }) {
  host.classList.add('gcrm-host')
  const shadow = host.shadowRoot ?? host.attachShadow({ mode: 'open' })

  const style = document.createElement('style')
  style.textContent = styles

  const container = document.createElement('div')
  container.className = 'gcrm-root-container'
  shadow.replaceChildren(style, container)

  const app = createApp({
    name: 'GmailCrmRoot',
    render: () => h(component, { ...props }),
  })

  app.use(pinia)
  for (const [key, value] of Object.entries(provide)) app.provide(key, value)

  app.config.errorHandler = (error, _instance, info) => {
    console.error(`[Gmail CRM] ${info}:`, error)
  }

  app.mount(container)

  let mounted = true
  return {
    app,
    unmount() {
      if (!mounted) return
      mounted = false
      try {
        app.unmount()
      } finally {
        shadow.replaceChildren()
      }
    },
  }
}
