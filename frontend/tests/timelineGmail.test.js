import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { installChromeStub } from './setup-chrome.js'

vi.mock('@/crm/api.js', async () => {
  const errors = await import('@/shared/api/errors.js')
  return { api: { get: vi.fn(), post: vi.fn() }, ApiError: errors.ApiError, toApiError: errors.toApiError, onApiError: errors.onApiError }
})

const { default: Timeline } = await import('@/crm/components/client/Timeline.vue')
const { useClientStore } = await import('@/crm/stores/client.js')

const email = { id: 1, type: 'email', title: 'Wesele 12.06', body: 'Dzień dobry…', occurred_at: '2026-10-01T10:00:00Z', meta: { thread_id: '18c2a', direction: 'in' } }
const oldEmail = { id: 2, type: 'email', title: 'Bez wątku', body: '', occurred_at: '2026-09-01T10:00:00Z', meta: {} }

function mount(gmail) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const client = useClientStore()
  client.contact = { id: 5, email: 'jan@xyz.pl', name: 'Jan' }
  client.timeline.items = [email, oldEmail]
  const el = document.createElement('div')
  const app = createApp({ render: () => h(Timeline) })
  app.use(pinia)
  if (gmail) app.provide('gmail', gmail)
  app.mount(el)
  return el
}

const buttons = (el, text) => [...el.querySelectorAll('button')].filter((b) => text.test(b.textContent))

beforeEach(() => installChromeStub())

describe('Timeline emails in Gmail', () => {
  it('opens the thread on click and offers reply', async () => {
    const gmail = { openThread: vi.fn(), replyToThread: vi.fn() }
    const el = mount(gmail)
    await nextTick()

    el.querySelector('[role="link"]').click()
    expect(gmail.openThread).toHaveBeenCalledWith('18c2a')

    buttons(el, /Odpowiedz|Reply/)[0].click()
    expect(gmail.replyToThread).toHaveBeenCalledWith('18c2a')
    expect(gmail.openThread).toHaveBeenCalledTimes(1) // the reply button doesn't also open via the card

    // Only the email with a thread id is clickable.
    expect(el.querySelectorAll('[role="link"]')).toHaveLength(1)
  })

  it('renders plain cards outside Gmail', async () => {
    const el = mount(null)
    await nextTick()
    expect(el.querySelectorAll('[role="link"]')).toHaveLength(0)
    expect(buttons(el, /Odpowiedz|Reply/)).toHaveLength(0)
  })
})
