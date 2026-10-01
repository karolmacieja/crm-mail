import { defineStore } from 'pinia'
import { reactive } from 'vue'
import { api, toApiError } from '@/lib/api.js'

const normalizeEmail = (email) => String(email ?? '').trim().toLowerCase()

/**
 * CRM state shared by every Vue app mounted in the Gmail tab (sidebar panels
 * and the dashboard use the same Pinia instance), so an edit made in one
 * place is immediately reflected in the other.
 */
export const useCrmStore = defineStore('crm', () => {
  /** email -> { status: 'loading'|'loaded'|'error', contact: object|null, error: ApiError|null } */
  const lookups = reactive({})

  const contactList = reactive({
    items: [],
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 },
    filters: { search: '', status: '', page: 1 },
    loading: false,
    error: null,
    loaded: false,
  })

  const summary = reactive({ data: null, loading: false, error: null })

  const pendingLookups = new Map()
  let listRequestId = 0
  let summaryRefreshQueued = false

  // ---------------------------------------------------------------- helpers

  /** Merge a contact returned by the API into every cache that holds it. */
  function upsertContact(contact, { previousEmail } = {}) {
    if (previousEmail && normalizeEmail(previousEmail) !== contact.email) {
      delete lookups[normalizeEmail(previousEmail)]
    }

    const cached = lookups[contact.email]?.contact
    lookups[contact.email] = {
      status: 'loaded',
      error: null,
      contact: {
        ...cached,
        ...contact,
        tasks: contact.tasks ?? cached?.tasks ?? [],
      },
    }

    const index = contactList.items.findIndex((item) => item.id === contact.id)
    if (index !== -1) {
      contactList.items[index] = { ...contactList.items[index], ...contact }
    }
  }

  function findCachedContactById(contactId) {
    return Object.values(lookups).find((entry) => entry.contact?.id === contactId)?.contact ?? null
  }

  function adjustOpenTaskCount(contactId, delta) {
    const cached = findCachedContactById(contactId)
    if (cached) cached.open_tasks_count = Math.max(0, (cached.open_tasks_count ?? 0) + delta)

    const listed = contactList.items.find((item) => item.id === contactId)
    if (listed) listed.open_tasks_count = Math.max(0, (listed.open_tasks_count ?? 0) + delta)
  }

  /** Re-fetch dashboard numbers after task changes, but only if the dashboard has loaded them. */
  function refreshSummarySoon() {
    if (!summary.data || summaryRefreshQueued) return
    summaryRefreshQueued = true
    queueMicrotask(() => {
      summaryRefreshQueued = false
      fetchSummary().catch(() => {})
    })
  }

  // --------------------------------------------------------------- contacts

  async function lookupContact(email, { force = false } = {}) {
    const key = normalizeEmail(email)
    if (!key) return null

    if (!force && lookups[key]?.status === 'loaded') return lookups[key].contact
    if (pendingLookups.has(key)) return pendingLookups.get(key)

    lookups[key] = { status: 'loading', contact: lookups[key]?.contact ?? null, error: null }

    const promise = api
      .get('/contacts/lookup', { params: { email: key } })
      .then(({ data }) => {
        lookups[key] = { status: 'loaded', contact: data.data, error: null }
        return data.data
      })
      .catch((error) => {
        const apiError = toApiError(error)
        lookups[key] = { status: 'error', contact: null, error: apiError }
        throw apiError
      })
      .finally(() => pendingLookups.delete(key))

    pendingLookups.set(key, promise)
    return promise
  }

  async function fetchContacts(filters = {}) {
    Object.assign(contactList.filters, filters)
    const requestId = ++listRequestId
    contactList.loading = true
    contactList.error = null

    try {
      const { data } = await api.get('/contacts', {
        params: {
          search: contactList.filters.search || undefined,
          status: contactList.filters.status || undefined,
          page: contactList.filters.page,
          per_page: contactList.meta.per_page,
        },
      })

      // Ignore out-of-order responses (fast typing in the search box).
      if (requestId !== listRequestId) return

      contactList.items = data.data
      contactList.meta = {
        current_page: data.meta.current_page,
        last_page: data.meta.last_page,
        total: data.meta.total,
        per_page: data.meta.per_page,
      }
      contactList.loaded = true
    } catch (error) {
      if (requestId === listRequestId) contactList.error = toApiError(error)
    } finally {
      if (requestId === listRequestId) contactList.loading = false
    }
  }

  async function createContact(payload) {
    try {
      const { data } = await api.post('/contacts', payload)
      upsertContact(data.data)
      if (contactList.loaded) fetchContacts()
      refreshSummarySoon()
      return data.data
    } catch (error) {
      throw toApiError(error)
    }
  }

  async function updateContact(contact, payload) {
    try {
      const { data } = await api.patch(`/contacts/${contact.id}`, payload)
      upsertContact(data.data, { previousEmail: contact.email })
      if (payload.status !== undefined) refreshSummarySoon()
      return data.data
    } catch (error) {
      throw toApiError(error)
    }
  }

  async function deleteContact(contact) {
    try {
      await api.delete(`/contacts/${contact.id}`)
    } catch (error) {
      const apiError = toApiError(error)
      if (apiError.status !== 404) throw apiError
    }

    lookups[normalizeEmail(contact.email)] = { status: 'loaded', contact: null, error: null }
    contactList.items = contactList.items.filter((item) => item.id !== contact.id)
    contactList.meta.total = Math.max(0, contactList.meta.total - 1)
    refreshSummarySoon()
  }

  // ------------------------------------------------------------------ tasks

  async function createTask(contact, { title, due_date }) {
    try {
      const { data } = await api.post('/tasks', { contact_id: contact.id, title, due_date })
      const cached = findCachedContactById(contact.id)
      if (cached) cached.tasks = sortTasks([...(cached.tasks ?? []), data.data])
      adjustOpenTaskCount(contact.id, 1)
      refreshSummarySoon()
      return data.data
    } catch (error) {
      throw toApiError(error)
    }
  }

  async function updateTask(task, payload) {
    try {
      const { data } = await api.patch(`/tasks/${task.id}`, payload)
      const cached = findCachedContactById(task.contact_id)
      if (cached?.tasks) {
        cached.tasks = sortTasks(cached.tasks.map((t) => (t.id === task.id ? data.data : t)))
      }
      if (payload.is_completed !== undefined && payload.is_completed !== task.is_completed) {
        adjustOpenTaskCount(task.contact_id, payload.is_completed ? -1 : 1)
      }
      refreshSummarySoon()
      return data.data
    } catch (error) {
      throw toApiError(error)
    }
  }

  async function deleteTask(task) {
    try {
      await api.delete(`/tasks/${task.id}`)
    } catch (error) {
      const apiError = toApiError(error)
      if (apiError.status !== 404) throw apiError
    }

    const cached = findCachedContactById(task.contact_id)
    if (cached?.tasks) cached.tasks = cached.tasks.filter((t) => t.id !== task.id)
    if (!task.is_completed) adjustOpenTaskCount(task.contact_id, -1)
    refreshSummarySoon()
  }

  // -------------------------------------------------------------- dashboard

  async function fetchSummary() {
    summary.loading = true
    summary.error = null
    try {
      const { data } = await api.get('/dashboard/summary', {
        params: { tz: Intl.DateTimeFormat().resolvedOptions().timeZone },
      })
      summary.data = data.data
    } catch (error) {
      summary.error = toApiError(error)
    } finally {
      summary.loading = false
    }
  }

  /** Clear everything (e.g. on logout or account switch). */
  function $reset() {
    Object.keys(lookups).forEach((key) => delete lookups[key])
    contactList.items = []
    contactList.loaded = false
    contactList.error = null
    contactList.filters = { search: '', status: '', page: 1 }
    summary.data = null
    summary.error = null
  }

  return {
    lookups,
    contactList,
    summary,
    lookupContact,
    fetchContacts,
    createContact,
    updateContact,
    deleteContact,
    createTask,
    updateTask,
    deleteTask,
    fetchSummary,
    $reset,
  }
})

/** Open tasks first, then by due date (no due date last), mirroring the API ordering. */
function sortTasks(tasks) {
  return [...tasks].sort((a, b) => {
    if (a.is_completed !== b.is_completed) return a.is_completed ? 1 : -1
    if (!a.due_date !== !b.due_date) return a.due_date ? -1 : 1
    return (Date.parse(a.due_date ?? 0) || 0) - (Date.parse(b.due_date ?? 0) || 0)
  })
}
