import { createApp, h } from 'vue'
// `?inline` returns the compiled CSS as a string instead of injecting it into
// Gmail's <head>, so Tailwind's preflight can never restyle Gmail itself.
import styles from '@/shared/styles/tailwind.css?inline'

/**
 * Prepare `host` (a plain <div> handed to InboxSDK) for a Vue app:
 *
 *   host
 *   └─ #shadow-root
 *      ├─ <style> (our Tailwind + Font Awesome CSS, scoped to this tree)
 *      └─ <div class="gcrm-root-container"> ← Vue app
 *
 * Gmail's CSS can't reach in and ours can't leak out.
 */
function prepareShadowRoot(host) {
  host.classList.add('gcrm-host')
  const shadow = host.shadowRoot ?? host.attachShadow({ mode: 'open' })

  const style = document.createElement('style')
  style.textContent = styles

  const container = document.createElement('div')
  container.className = 'gcrm-root-container gcrm-root'
  container.style.height = '100%'
  shadow.replaceChildren(style, container)

  return { shadow, container }
}

/**
 * Mount an already configured Vue app (router, pinia, provide) into a shadow root.
 * @returns {{ app: import('vue').App, unmount(): void }}
 */
export function mountAppIsolated(host, app) {
  const { shadow, container } = prepareShadowRoot(host)
  app.config.errorHandler ??= (error, _instance, info) => console.error(`[GastroFlowx] ${info}:`, error)
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

/**
 * Mount a single component (props may be a reactive() object: the component
 * re-renders when it changes).
 *
 * @param {{ props?: object, pinia: import('pinia').Pinia, provide?: Record<string, unknown> }} options
 */
export function mountIsolated(host, component, { props = {}, pinia, provide = {} }) {
  const app = createApp({ name: 'GastroFlowxRoot', render: () => h(component, { ...props }) })
  app.use(pinia)
  for (const [key, value] of Object.entries(provide)) app.provide(key, value)
  return mountAppIsolated(host, app)
}
