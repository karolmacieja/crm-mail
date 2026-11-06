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
const { default: TaskItem } = await import('@/crm/components/TaskItem.vue')
const { default: ReminderItem } = await import('@/crm/components/ReminderItem.vue')
const { default: ReminderDetailsModal } = await import('@/crm/components/ReminderDetailsModal.vue')

const page = (items) => Promise.resolve({ data: { data: items, meta: { current_page: 1, last_page: 1, total: items.length } } })
const contact = { id: 5, email: 'jan@xyz.pl', name: 'Jan' }

function mount(component, props) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const el = document.createElement('div')
  document.body.appendChild(el)
  createApp({ render: () => h(component, props) }).use(pinia).mount(el)
  return el
}

const button = (root, pattern) => [...root.querySelectorAll('button')].find((b) => pattern.test(b.title || b.textContent))

function type(input, value) {
  input.value = value
  input.dispatchEvent(new Event('input'))
}

beforeEach(() => {
  installChromeStub()
  document.body.innerHTML = ''
  vi.resetAllMocks()
  api.get.mockImplementation(() => new Promise(() => {}))
})

describe('editing tasks', () => {
  it('opens the form filled with the task and saves only the edited fields', async () => {
    const task = {
      id: 4, title: 'Wysłać menu', type: 'follow_up', priority: 'high', due_date: '2026-11-10T09:00:00Z', is_completed: false, can_edit: true,
      contact_id: 5, contact, assignee: null, description: 'Wersja wegańska', source_email_id: 'm1', source_email_subject: 'Wesele',
    }
    const el = mount(TaskItem, { task })
    button(el, /Edytuj zadanie|Edit task/).click()
    await nextTick()

    const form = document.querySelector('#task-form')
    expect(form).toBeTruthy()
    const title = form.querySelector('input:not([type])')
    expect(title.value).toBe('Wysłać menu')
    expect(form.querySelector('textarea').value).toBe('Wersja wegańska')
    expect(form.querySelector('input[type="datetime-local"]').value).not.toBe('')
    type(title, 'Wysłać menu weselne')

    api.patch.mockResolvedValue({ data: { data: { ...task, title: 'Wysłać menu weselne' } } })
    form.dispatchEvent(new Event('submit'))
    await vi.waitFor(() => expect(api.patch).toHaveBeenCalled())
    const [url, payload] = api.patch.mock.calls[0]
    expect(url).toBe('/tasks/4')
    expect(payload).toMatchObject({ title: 'Wysłać menu weselne', priority: 'high', contact_id: 5, description: 'Wersja wegańska' })
    expect(payload).not.toHaveProperty('source_email_id')
    expect(api.post).not.toHaveBeenCalled()
  })

  it('hides the edit button on read-only tasks', () => {
    const el = mount(TaskItem, { task: { id: 1, title: 'Cudze', is_completed: false, can_edit: false } })
    expect(button(el, /Edytuj zadanie|Edit task/)).toBeUndefined()
  })
})

describe('editing reminders', () => {
  const reminder = {
    id: 9, title: 'Wrócić do wyceny', type: 'email', remind_at: '2026-11-12T09:00:00Z', is_done: false, can_edit: true,
    contact, contact_id: 5, reservation_id: null, notes: 'Zapytać o datę', source_email_id: 'm1', source_thread_id: 't1',
  }

  it('saves changes from the list and keeps the source email', async () => {
    const el = mount(ReminderItem, { reminder })
    useRemindersStore()
    button(el, /Edytuj przypomnienie|Edit reminder/).click()
    await nextTick()

    const form = document.querySelector('#reminder-form')
    const title = form.querySelector('input:not([type])')
    expect(title.value).toBe('Wrócić do wyceny')
    expect(form.querySelector('textarea').value).toBe('Zapytać o datę')
    type(form.querySelector('input[type="datetime-local"]'), '2026-11-20T10:30')

    api.patch.mockResolvedValue({ data: { data: { ...reminder, remind_at: '2026-11-20T09:30:00Z' } } })
    form.dispatchEvent(new Event('submit'))
    await vi.waitFor(() => expect(api.patch).toHaveBeenCalled())
    const [url, payload] = api.patch.mock.calls[0]
    expect(url).toBe('/reminders/9')
    expect(payload).toMatchObject({ title: 'Wrócić do wyceny', type: 'email', contact_id: 5, notes: 'Zapytać o datę' })
    expect(new Date(payload.remind_at).getTime()).toBe(new Date('2026-11-20T10:30').getTime())
    expect(payload).not.toHaveProperty('source_email_id')
    await vi.waitFor(() => expect(document.querySelector('#reminder-form')).toBeNull())
  })

  it('the details window has an "Edytuj" button that switches to the form', async () => {
    mount(ReminderDetailsModal, { reminder })
    await nextTick()
    button(document.body, /^\s*(Edytuj|Edit)\s*$/).click()
    await nextTick()
    expect(document.querySelector('#reminder-form')).toBeTruthy()
  })

  it('an edit that moves the reminder updates it in the list', async () => {
    api.get.mockImplementation((url) => (url === '/reminders' ? page([reminder]) : new Promise(() => {})))
    setActivePinia(createPinia())
    const reminders = useRemindersStore()
    await reminders.load()
    api.patch.mockResolvedValue({ data: { data: { ...reminder, title: 'Nowy tytuł' } } })
    await reminders.update(reminder, { title: 'Nowy tytuł' })
    expect(reminders.list.items[0].title).toBe('Nowy tytuł')
    expect(useTasksStore()).toBeTruthy()
  })
})
