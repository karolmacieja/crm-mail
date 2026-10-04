import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { installChromeStub } from './setup-chrome.js'

vi.mock('@/crm/api.js', async () => {
  const errors = await import('@/shared/api/errors.js')
  return {
    api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
    ApiError: errors.ApiError,
    toApiError: errors.toApiError,
    onApiError: errors.onApiError,
  }
})

const { api } = await import('@/crm/api.js')
const { useClientStore } = await import('@/crm/stores/client.js')
const { default: Timeline } = await import('@/crm/components/client/Timeline.vue')

const mine = { id: 1, type: 'email', contact_id: 5, title: 'Cennik 2027', occurred_at: '2026-10-01T10:00:00Z', meta: {}, team_shared: false }
const fromTeam = { id: 2, type: 'email', contact_id: 9, title: 'Okólnik', occurred_at: '2026-10-02T10:00:00Z', meta: {}, team_shared: true, team_shared_by: { id: 2, name: 'Anna' } }

beforeEach(() => {
  installChromeStub()
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('business correspondence', () => {
  it('remembers that colleagues keep private cards of an unknown sender', async () => {
    api.get.mockResolvedValueOnce({ data: { data: null, meta: { personal_cards: { category: { id: 4, name: 'Firmowa' }, team_emails: 3 } } } })
    const client = useClientStore()
    await client.openEmail({ email: 'biuro@hurtownia.pl' })
    expect(client.status).toBe('not_found')
    expect(client.personalInfo).toEqual({ category: { id: 4, name: 'Firmowa' }, team_emails: 3 })
  })

  it('adds own emails to the team pool and labels pool emails', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const client = useClientStore()
    client.contact = { id: 5, email: 'biuro@hurtownia.pl', is_personal: true }
    client.timeline.items = [mine, fromTeam]
    const el = document.createElement('div')
    createApp({ render: () => h(Timeline) }).use(pinia).mount(el)
    await nextTick()

    expect(el.textContent).toContain('Anna')
    const add = [...el.querySelectorAll('button')].find((b) => /puli|pool/i.test(b.textContent) && !/Anna/.test(b.textContent))
    api.post.mockResolvedValue({ data: { data: { ...mine, team_shared: true } } })
    add.click()
    await nextTick()
    await nextTick()
    expect(api.post).toHaveBeenCalledWith('/contacts/5/activities/1/team-share')
    expect(client.timeline.items[0].team_shared).toBe(true)
  })
})
