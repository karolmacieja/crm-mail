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
const { default: SharingPanel } = await import('@/crm/components/client/SharingPanel.vue')
const { default: OwnedByColleague } = await import('@/crm/components/client/OwnedByColleague.vue')

const ok = (data, meta) => Promise.resolve({ data: meta ? { data, meta } : { data } })
const ownership = { contact_id: 7, owner: { id: 2, name: 'Anna' }, access_requested: false }
const ownersCard = {
  id: 7,
  email: 'gosc@firma.pl',
  name: 'Gość',
  owner: { id: 1, name: 'Ja' },
  access: { is_owner: true, can_manage: true, scopes: ['details', 'notes', 'emails', 'reservations', 'work'], email_ids: [] },
  shares: [],
  access_requests: [{ id: 3, user: { id: 2, name: 'Bartek' }, message: 'Mam jego rezerwację', created_at: '2026-10-04T10:00:00Z' }],
}

function mount(component, setup) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const client = useClientStore()
  setup(client)
  const el = document.createElement('div')
  createApp({ render: () => h(component) }).use(pinia).mount(el)
  return { el, client }
}

beforeEach(() => {
  installChromeStub()
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('private contacts and sharing', () => {
  it('shows a colleague\'s client as owned elsewhere and requests access', async () => {
    api.get.mockImplementation(() => ok(null, undefined))
    api.get.mockResolvedValueOnce({ data: { data: null, meta: { owned_by_colleague: ownership } } })
    api.post.mockImplementation(() => ok({ id: 1, status: 'pending' }))
    const client = useClientStore()

    await client.openEmail({ email: 'gosc@firma.pl', messageId: 'm-1' })
    expect(client.status).toBe('owned_elsewhere')
    expect(client.ownership.owner.name).toBe('Anna')
    // Nothing is logged on a card I cannot see.
    expect(api.post).not.toHaveBeenCalled()

    await client.requestAccess('Obsługuję rezerwację')
    expect(api.post).toHaveBeenCalledWith('/contacts/7/access-requests', { message: 'Obsługuję rezerwację' })
    expect(client.ownership.access_requested).toBe(true)
  })

  it('renders the owner notice with a request button', async () => {
    const { el } = mount(OwnedByColleague, (client) => {
      client.ownership = ownership
    })
    await nextTick()
    expect(el.textContent).toContain('Anna')
    expect([...el.querySelectorAll('button')].some((b) => /dostęp|access/i.test(b.textContent))).toBe(true)
  })

  it('owner sees pending requests; sharing answers them', async () => {
    const { el, client } = mount(SharingPanel, (client) => {
      client.contact = structuredClone(ownersCard)
    })
    await nextTick()
    expect(el.textContent).toContain('Bartek')

    api.post.mockImplementation(() => ok({ id: 11, user: { id: 2, name: 'Bartek' }, scopes: ['details'], email_ids: [] }))
    await client.share({ user_id: 2, scopes: ['details'], email_ids: [] })
    expect(api.post).toHaveBeenCalledWith('/contacts/7/shares', { user_id: 2, scopes: ['details'], email_ids: [] })
    expect(client.contact.shares).toHaveLength(1)
    expect(client.contact.access_requests).toHaveLength(0)
    await nextTick()
    expect(el.textContent).toContain('Bartek')
  })

  it('a colleague with a share cannot manage the card', async () => {
    const { el, client } = mount(SharingPanel, (client) => {
      client.contact = { ...ownersCard, owner: { id: 2, name: 'Anna' }, access: { is_owner: false, can_manage: false, scopes: ['reservations'], email_ids: [4] }, shares: undefined, access_requests: undefined }
    })
    await nextTick()
    expect(client.canManage).toBe(false)
    expect(client.canSee('reservations')).toBe(true)
    expect(client.canSee('details')).toBe(false)
    expect(el.textContent).toContain('Anna')
    expect(el.textContent).not.toMatch(/Udostępnij$|Share$/)
  })
})
