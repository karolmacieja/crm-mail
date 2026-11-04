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
const { useTasksStore } = await import('@/crm/stores/tasks.js')
const { useRemindersStore } = await import('@/crm/stores/reminders.js')
const { useDashboardStore } = await import('@/crm/stores/dashboard.js')
const { useGoogleStore } = await import('@/crm/stores/google.js')
const { sourceThreadId } = await import('@/crm/lib/emailSource.js')
const { default: ReminderDetailsModal } = await import('@/crm/components/ReminderDetailsModal.vue')

const page = (items) => Promise.resolve({ data: { data: items, meta: { current_page: 1, last_page: 1, total: items.length } } })

beforeEach(() => {
  installChromeStub()
  setActivePinia(createPinia())
  vi.resetAllMocks()
})

describe('completing removes the item at once', () => {
  it('a task marked done leaves the "open" list and the dashboard', async () => {
    const task = { id: 1, title: 'Oferta', is_completed: false, contact_id: null }
    api.get.mockImplementation((url) => (url === '/tasks' ? page([task, { id: 2, title: 'Menu', is_completed: false }]) : new Promise(() => {})))
    const tasks = useTasksStore()
    await tasks.load()
    const dashboard = useDashboardStore()
    dashboard.summary = { tasks: { overdue: 1, due_today: 0, upcoming_week: 0, lists: { overdue: [task], today: [], upcoming: [] } } }

    api.patch.mockResolvedValue({ data: { data: { ...task, is_completed: true } } })
    await tasks.toggle(task)

    expect(tasks.list.items.map((t) => t.id)).toEqual([2])
    expect(dashboard.summary.tasks.lists.overdue).toEqual([])
    expect(dashboard.summary.tasks.overdue).toBe(0)
  })

  it('a reminder marked done disappears; under "Wykonane" it stays', async () => {
    const reminder = { id: 7, title: 'Oddzwonić', is_done: false, type: 'general' }
    api.get.mockImplementation((url) => (url === '/reminders' ? page([reminder]) : new Promise(() => {})))
    const reminders = useRemindersStore()
    await reminders.load()
    api.patch.mockResolvedValue({ data: { data: { ...reminder, is_done: true } } })
    await reminders.markDone(reminder)
    expect(reminders.list.items).toEqual([])

    await reminders.load({ filters: { window: 'done' } })
    api.patch.mockResolvedValue({ data: { data: { ...reminder, is_done: true, notes: 'x' } } })
    api.get.mockImplementation((url) => (url === '/reminders' ? page([{ ...reminder, is_done: true }]) : new Promise(() => {})))
    await reminders.load()
    await reminders.update({ ...reminder, is_done: true }, { notes: 'x' })
    expect(reminders.list.items).toHaveLength(1)
  })
})

describe('the email a reminder was created from', () => {
  it('prefers the remembered thread, then the dashboard placeholder, then Gmail', async () => {
    expect(await sourceThreadId({ source_thread_id: 't1', source_email_id: 'm1' })).toBe('t1')
    expect(await sourceThreadId({ source_email_id: 'thread:t2' })).toBe('t2')
    useGoogleStore().threadOf = vi.fn(async () => 't3')
    expect(await sourceThreadId({ source_email_id: 'm3' })).toBe('t3')
    useGoogleStore().threadOf = vi.fn(async () => null)
    expect(await sourceThreadId({ source_email_id: 'm4' })).toBe('m4')
    expect(await sourceThreadId({})).toBeNull()
  })

  it('shows the client and the email, and replies in that Gmail thread', async () => {
    const reminder = {
      id: 9, title: 'Wrócić do wyceny', type: 'email', remind_at: '2026-11-05T09:00:00Z', is_done: false, can_edit: true,
      contact: { id: 5, email: 'jan@xyz.pl', name: 'Jan' }, source_email_id: 'm1', source_thread_id: 't1', source_email_subject: 'Wesele 12.06',
    }
    api.get.mockImplementation((url) =>
      url === '/contacts/5'
        ? Promise.resolve({ data: { data: { id: 5, name: 'Jan Kowalski', phone: '+48 600 100 200', company: 'XYZ' } } })
        : page([{ id: 3, type: 'email', title: 'Wesele 12.06', body: 'Dzień dobry, prosimy o wycenę…', occurred_at: '2026-11-01T10:00:00Z', meta: { message_id: 'm1', thread_id: 't1' } }]),
    )
    const gmail = { openThread: vi.fn(), replyToThread: vi.fn() }
    const pinia = createPinia()
    setActivePinia(pinia)
    const el = document.createElement('div')
    document.body.appendChild(el)
    createApp({ render: () => h(ReminderDetailsModal, { reminder }) }).use(pinia).provide('gmail', gmail).mount(el)
    await vi.waitFor(() => expect(document.body.textContent).toContain('prosimy o wycenę'))
    expect(document.body.textContent).toContain('+48 600 100 200')
    expect(document.body.textContent).toContain('Wesele 12.06')

    const reply = [...document.querySelectorAll('button')].find((b) => /Odpowiedz|Reply/.test(b.textContent))
    reply.click()
    await nextTick()
    await vi.waitFor(() => expect(gmail.replyToThread).toHaveBeenCalledWith('t1'))
  })
})
