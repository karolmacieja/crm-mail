import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

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
const { useTasksStore } = await import('@/crm/stores/tasks.js')
const { useDashboardStore } = await import('@/crm/stores/dashboard.js')

const ok = (data, meta) => Promise.resolve({ data: meta ? { data, meta } : { data } })
const timelinePage = (items = []) => ok(items, { current_page: 1, last_page: 1 })

const jan = {
  id: 5,
  email: 'jan@xyz.pl',
  name: 'Jan Kowalski',
  tasks: [{ id: 1, contact_id: 5, is_completed: false, due_date: '2026-10-20T10:00:00Z' }],
  reminders: [],
  upcoming_reservations: [],
  custom_fields: [],
  open_tasks_count: 1,
}
const email = { email: 'Jan@XYZ.pl', name: 'Jan Kowalski', subject: 'Wigilia na 20 osób', messageId: 'msg-1', threadId: 't-1' }

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('useClientStore', () => {
  it('opens an email: looks up the sender, logs the email and loads the timeline', async () => {
    api.get.mockImplementation((url) => (url === '/contacts/lookup' ? ok(jan) : timelinePage([{ id: 9, type: 'email' }])))
    api.post.mockImplementation(() => ok({}))
    const client = useClientStore()

    await client.openEmail(email)

    expect(client.isSidebarOpen).toBe(true)
    expect(client.status).toBe('ready')
    expect(api.get).toHaveBeenCalledWith('/contacts/lookup', { params: { email: 'jan@xyz.pl' } })
    expect(api.post).toHaveBeenCalledWith('/contacts/5/emails', expect.objectContaining({ message_id: 'msg-1', subject: 'Wigilia na 20 osób' }))
    expect(client.timeline.items).toHaveLength(1)
    expect(client.openTasks).toHaveLength(1)
  })

  it('marks unknown senders as not_found and can add them to the CRM', async () => {
    api.get.mockImplementation((url) => (url === '/contacts/lookup' ? ok(null) : timelinePage()))
    api.post.mockImplementation((url) => (url === '/contacts' ? ok({ ...jan, tasks: [] }) : ok({})))
    const client = useClientStore()

    await client.openEmail(email)
    expect(client.status).toBe('not_found')
    expect(client.displayName).toBe('Jan Kowalski')

    await client.createFromContext({ phone: '+48 600 100 200' })
    expect(api.post).toHaveBeenCalledWith('/contacts', expect.objectContaining({ email: 'jan@xyz.pl', is_client: true, phone: '+48 600 100 200' }))
    expect(client.status).toBe('ready')
    expect(api.post).toHaveBeenCalledWith('/contacts/5/emails', expect.anything())
  })

  it('drops the response of an email that is no longer focused', async () => {
    let resolveSlow
    api.get.mockImplementation((url, { params } = {}) => {
      if (url !== '/contacts/lookup') return timelinePage()
      if (params.email === 'slow@x.pl') return new Promise((r) => (resolveSlow = () => r({ data: { data: { ...jan, id: 1, email: 'slow@x.pl' } } })))
      return ok(jan)
    })
    api.post.mockImplementation(() => ok({}))
    const client = useClientStore()

    const slow = client.openEmail({ email: 'slow@x.pl' })
    await client.openEmail(email)
    resolveSlow()
    await slow

    expect(client.contact.id).toBe(5)
  })

  it('reservations created from an email keep the Gmail message id', async () => {
    api.get.mockImplementation((url) => (url === '/contacts/lookup' ? ok(jan) : timelinePage()))
    api.post.mockImplementation((url) =>
      url === '/reservations' ? ok({ id: 3, is_upcoming: true, starts_at: '2026-12-18T18:00:00Z' }) : ok({}),
    )
    const client = useClientStore()
    await client.openEmail(email)

    await client.addReservation({ reservation_date: '2026-12-18', reservation_time: '19:00', guests_count: 20 })

    expect(api.post).toHaveBeenCalledWith('/reservations', expect.objectContaining({ contact_id: 5, source_email_id: 'msg-1', guests_count: 20 }))
    expect(client.nextReservation.id).toBe(3)
  })

  it('notes are prepended to the timeline and update the last activity', async () => {
    api.get.mockImplementation(() => ok(jan))
    api.get.mockImplementationOnce(() => ok(jan)).mockImplementationOnce(() => timelinePage([{ id: 1, type: 'email' }]))
    api.post.mockImplementation(() => ok({ id: 2, type: 'note', occurred_at: '2026-10-14T11:00:00Z' }))
    const client = useClientStore()
    await client.openContact(5)

    await client.addNote('Rabat 5%')

    expect(client.timeline.items.map((a) => a.id)).toEqual([2, 1])
    expect(client.contact.last_activity.type).toBe('note')
  })
})

describe('useTasksStore ↔ useClientStore', () => {
  it('completing a task updates the open client profile and refreshes the dashboard', async () => {
    api.get.mockImplementation((url) => (url === '/contacts/5' ? ok(jan) : url === '/dashboard/summary' ? ok({ tasks: {} }) : timelinePage()))
    const client = useClientStore()
    const dashboard = useDashboardStore()
    await client.openContact(5)
    await dashboard.fetch()
    api.get.mockClear()

    api.patch.mockImplementation(() => ok({ ...jan.tasks[0], is_completed: true }))
    await useTasksStore().toggle(jan.tasks[0])
    await Promise.resolve()

    expect(client.contact.open_tasks_count).toBe(0)
    expect(api.get).toHaveBeenCalledWith('/dashboard/summary')
  })

  it('groups tasks into the mockup board columns', () => {
    const tasks = useTasksStore()
    tasks.list.items = [
      { id: 1, type: 'follow_up', priority: 'high', time_status: 'upcoming', is_completed: false },
      { id: 2, type: 'follow_up', priority: 'normal', time_status: 'today', is_completed: false },
      { id: 3, type: 'follow_up', priority: 'normal', time_status: 'upcoming', is_completed: false },
      { id: 4, type: 'offer', priority: 'normal', time_status: 'later', is_completed: false },
      { id: 5, type: 'internal', priority: 'normal', time_status: 'upcoming', is_completed: false },
    ]
    const ids = (column) => tasks.board[column].map((t) => t.id)
    expect(ids('urgent')).toEqual([1, 2])
    expect(ids('follow_up')).toEqual([3])
    expect(ids('offer')).toEqual([4])
    expect(ids('internal')).toEqual([5])
  })
})
