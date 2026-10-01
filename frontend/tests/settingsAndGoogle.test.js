import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/crm/api.js', async () => {
  const errors = await import('@/shared/api/errors.js')
  return {
    api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn(), delete: vi.fn() },
    ApiError: errors.ApiError,
    toApiError: errors.toApiError,
    onApiError: errors.onApiError,
  }
})
vi.mock('@/crm/google/client.js', () => ({ googleApi: vi.fn() }))

const { api } = await import('@/crm/api.js')
const { googleApi } = await import('@/crm/google/client.js')
const { useSettingsStore } = await import('@/crm/stores/settings.js')
const { importEmailHistory, addressOf } = await import('@/crm/google/emailHistory.js')
const { belongsToMe, syncCalendarItem, googleCalendarTemplateUrl } = await import('@/crm/google/calendarSync.js')

const ok = (data) => Promise.resolve({ data: { data } })
const statuses = [
  { id: 1, key: 'new', name: 'Nowy', color: 'blue', is_default: true },
  { id: 2, key: 'vip', name: 'VIP', color: 'amber', is_default: false },
]

beforeEach(() => {
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('useSettingsStore', () => {
  it('loads dictionaries and preferences once', async () => {
    api.get.mockImplementation((url) => (url === '/settings' ? ok({ statuses, task_categories: [], categories: [], field_templates: [] }) : ok({ default_reminder_time: '09:00' })))
    const settings = useSettingsStore()
    await Promise.all([settings.load(), settings.load()])
    await settings.load()

    expect(api.get).toHaveBeenCalledTimes(2)
    expect(settings.statusByKey('vip').name).toBe('VIP')
    expect(settings.preferences.default_reminder_time).toBe('09:00')
  })

  it('reorders optimistically and keeps the server order', async () => {
    const settings = useSettingsStore()
    settings.dictionaries.statuses = [...statuses]
    let resolve
    api.post.mockReturnValue(new Promise((r) => (resolve = r)))

    const pending = settings.reorder('statuses', [2, 1])
    expect(settings.statuses.map((s) => s.id)).toEqual([2, 1])
    resolve({ data: { data: [statuses[1], statuses[0]] } })
    await pending

    expect(api.post).toHaveBeenCalledWith('/settings/statuses/reorder', { ids: [2, 1] })
  })

  it('deletes an item in use by moving it to another key', async () => {
    api.delete.mockReturnValue(ok(null))
    api.get.mockImplementation((url) => (url === '/settings' ? ok({ statuses: [statuses[1]] }) : ok({})))
    const settings = useSettingsStore()
    await settings.remove('statuses', 1, { moveTo: 'vip' })

    expect(api.delete).toHaveBeenCalledWith('/settings/statuses/1', { params: { move_to: 'vip' } })
    expect(settings.statuses).toHaveLength(1)
  })
})

describe('importEmailHistory', () => {
  it('pages through Gmail and posts the messages to the timeline', async () => {
    googleApi.mockImplementation((method, url) => {
      if (url.includes('?q=')) return Promise.resolve({ messages: [{ id: 'm1' }, { id: 'm2' }] })
      const id = url.match(/messages\/(\w+)\?/)[1]
      return Promise.resolve({
        id,
        threadId: 't1',
        snippet: 'Dzie&#324; dobry &amp; witam',
        internalDate: '1767225600000',
        payload: { headers: [{ name: 'Subject', value: 'Wesele' }, { name: 'From', value: id === 'm1' ? 'Jan <jan@xyz.pl>' : 'me@roma.test' }] },
      })
    })
    api.post.mockReturnValue(ok({ imported: 2, updated: 0, skipped: 0, email_history_synced_at: '2026-10-01T10:00:00Z' }))

    const result = await importEmailHistory({ id: 5, email: 'Jan@XYZ.pl', email_history_synced_at: null })

    expect(new URL(googleApi.mock.calls[0][1]).searchParams.get('q')).toContain('(from:jan@xyz.pl OR to:jan@xyz.pl OR cc:jan@xyz.pl)')
    const [url, payload] = api.post.mock.calls[0]
    expect(url).toBe('/contacts/5/emails/import')
    expect(payload.complete).toBe(true)
    expect(payload.emails.map((e) => [e.message_id, e.direction])).toEqual([['m1', 'in'], ['m2', 'out']])
    expect(payload.emails[0].snippet).toBe('Dzień dobry & witam')
    expect(result).toMatchObject({ found: 2, imported: 2, synced_at: '2026-10-01T10:00:00Z' })
  })

  it('only asks for newer mail after a previous sync', async () => {
    googleApi.mockResolvedValue({ messages: [] })
    api.post.mockReturnValue(ok({ imported: 0, updated: 0, skipped: 0 }))
    await importEmailHistory({ id: 5, email: 'jan@xyz.pl', email_history_synced_at: '2026-09-15T12:00:00Z' })
    expect(new URL(googleApi.mock.calls[0][1]).searchParams.get('q')).toContain('after:2026/9/14')
  })

  it('extracts addresses', () => {
    expect(addressOf('Jan Kowalski <JAN@xyz.pl>')).toBe('jan@xyz.pl')
    expect(addressOf('jan@xyz.pl')).toBe('jan@xyz.pl')
  })
})

describe('calendar sync', () => {
  const task = { id: 7, title: 'Oddzwonić', due_date: '2026-10-20T10:00:00Z', is_completed: false, user_id: 1, assignee: null, calendar_event_id: null }

  it('decides whose calendar an item belongs to', () => {
    expect(belongsToMe('task', task, 1)).toBe(true)
    expect(belongsToMe('task', { ...task, assignee: { id: 2 } }, 1)).toBe(false)
    expect(belongsToMe('task', { ...task, is_completed: true }, 1)).toBe(false)
    expect(belongsToMe('reminder', { user_id: 1, is_done: false }, 1)).toBe(true)
  })

  it('creates an event and stores its id', async () => {
    googleApi.mockResolvedValue({ id: 'evt-1' })
    api.put.mockReturnValue(ok({}))
    const id = await syncCalendarItem('task', task, { meId: 1, preferences: { calendar: { alarm_minutes: 30 } } })

    expect(id).toBe('evt-1')
    const [method, , body] = googleApi.mock.calls[0]
    expect(method).toBe('POST')
    expect(body.reminders.overrides[0].minutes).toBe(30)
    expect(api.put).toHaveBeenCalledWith('/calendar-events/task/7', { external_id: 'evt-1' })
  })

  it('recreates an event deleted in Google Calendar', async () => {
    googleApi.mockImplementationOnce(() => Promise.reject(Object.assign(new Error('gone'), { status: 404 }))).mockResolvedValueOnce({ id: 'evt-2' })
    api.put.mockReturnValue(ok({}))
    const id = await syncCalendarItem('task', { ...task, calendar_event_id: 'evt-1' }, { meId: 1 })
    expect(googleApi.mock.calls.map((c) => c[0])).toEqual(['PATCH', 'POST'])
    expect(id).toBe('evt-2')
  })

  it('removes the event when the task is completed', async () => {
    googleApi.mockResolvedValue(null)
    api.delete.mockReturnValue(ok(null))
    const id = await syncCalendarItem('task', { ...task, is_completed: true, calendar_event_id: 'evt-1' }, { meId: 1 })
    expect(id).toBeNull()
    expect(googleApi).toHaveBeenCalledWith('DELETE', expect.stringContaining('/events/evt-1'))
    expect(api.delete).toHaveBeenCalledWith('/calendar-events/task/7')
  })

  it('builds a Google Calendar template link', () => {
    const url = new URL(googleCalendarTemplateUrl('task', task))
    expect(url.searchParams.get('dates')).toBe('20261020T100000Z/20261020T103000Z')
    expect(url.searchParams.get('text')).toContain('Oddzwonić')
  })
})
