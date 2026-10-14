import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, h, nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { installChromeStub } from './setup-chrome.js'

vi.mock('@/crm/api.js', async () => {
  const errors = await import('@/shared/api/errors.js')
  return {
    api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn(), delete: vi.fn() },
    ApiError: errors.ApiError,
    toApiError: errors.toApiError,
    onApiError: errors.onApiError,
  }
})

const { api } = await import('@/crm/api.js')
const { useClientStore } = await import('@/crm/stores/client.js')
const { default: ReservationsPanel } = await import('@/crm/components/client/ReservationsPanel.vue')

const booking = {
  id: 3, contact_id: 5, reservation_date: '2026-12-18', reservation_time: '19:00', starts_at: '2026-12-18T18:00:00Z',
  guests_count: 20, status: 'pending', table_label: 'Sala A', occasion: 'Wigilia firmowa', notes: '', can_edit: true, is_upcoming: true,
}

beforeEach(() => {
  installChromeStub()
  vi.resetAllMocks()
})

describe('editing a reservation from the client card', () => {
  it('opens the reservation form and saves changes to the card', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    const client = useClientStore()
    client.contact = { id: 5, email: 'firma@xyz.pl', name: 'Firma XYZ', upcoming_reservations: [booking] }
    const el = document.createElement('div')
    document.body.appendChild(el)
    createApp({ render: () => h(ReservationsPanel) }).use(pinia).mount(el)
    await nextTick()

    const edit = [...el.querySelectorAll('button')].find((b) => /Edytuj|Edit/.test(b.textContent))
    expect(edit).toBeTruthy()
    edit.click()
    await nextTick()

    const form = document.querySelector('#reservation-form')
    expect(form).toBeTruthy()
    const guests = form.querySelector('input[type="number"]')
    expect(guests.value).toBe('20')
    guests.value = '24'
    guests.dispatchEvent(new Event('input'))

    api.patch.mockResolvedValue({ data: { data: { ...booking, guests_count: 24 } } })
    api.get.mockResolvedValue({ data: { data: [], meta: { current_page: 1, last_page: 1 } } })
    form.dispatchEvent(new Event('submit'))
    await vi.waitFor(() => expect(client.contact.upcoming_reservations[0].guests_count).toBe(24))
    expect(api.patch).toHaveBeenCalledWith('/reservations/3', expect.objectContaining({ guests_count: 24, status: 'pending', occasion: 'Wigilia firmowa' }))
  })
})
