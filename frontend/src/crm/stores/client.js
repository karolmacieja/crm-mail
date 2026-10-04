import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { api, toApiError } from '@/crm/api.js'
import { useGoogleStore } from './google.js'
import { useInboxStore } from './inbox.js'

/**
 * The client / email currently in focus.
 *
 * One store feeds both views of the same person:
 *  - ContextSidebar.vue   (slides in when an email is clicked on the dashboard
 *                          or opened in Gmail),
 *  - FullClientProfile.vue (opened from the "Klienci" list).
 * Editing in one is immediately visible in the other.
 */
export const useClientStore = defineStore('client', () => {
  /**
   * Where the focus came from. For emails: { source: 'email', email, name,
   * subject, snippet, messageId, threadId, receivedAt }. For list clicks:
   * { source: 'list', contactId }.
   */
  const context = ref(null)
  /** Contact profile as returned by GET /contacts/{id} (null = not in CRM yet). */
  const contact = ref(null)
  /** idle | loading | ready | not_found | owned_elsewhere | error */
  const status = ref('idle')
  /**
   * The sender is a colleague's private client:
   * { contact_id, owner: { id, name }, access_requested }.
   */
  const ownership = ref(null)
  /**
   * Business correspondence kept by colleagues on their own private cards:
   * { category: { id, name }, team_emails } – a new card will be personal too.
   */
  const personalInfo = ref(null)
  const error = ref(null)
  const isSidebarOpen = ref(false)

  const timeline = reactive({ items: [], page: 1, lastPage: 1, type: '', loading: false, error: null })

  // Every open*() call bumps this; responses for an older focus are dropped.
  let focusId = 0

  const hasContact = computed(() => contact.value !== null)
  const displayName = computed(() => contact.value?.name || context.value?.name || contact.value?.email || context.value?.email || '')
  const email = computed(() => contact.value?.email ?? context.value?.email ?? null)
  const nextReservation = computed(() => contact.value?.upcoming_reservations?.[0] ?? null)
  const openTasks = computed(() => (contact.value?.tasks ?? []).filter((task) => !task.is_completed))
  const pendingReminders = computed(() => contact.value?.reminders ?? [])
  /** What I may do on this card (older API responses: full access). */
  const access = computed(() => contact.value?.access ?? { is_owner: true, can_manage: true, scopes: SHARE_SCOPES, email_ids: [] })
  const canManage = computed(() => access.value.can_manage)
  const canSee = (scope) => access.value.can_manage || access.value.scopes.includes(scope)

  // ------------------------------------------------------------------ focus

  /**
   * Dashboard / Gmail: an email was clicked. Opens the right sidebar, except
   * in Gmail's own thread panel (sidebar: false), which renders the card itself.
   */
  async function openEmail(message, { sidebar = true } = {}) {
    const id = ++focusId
    context.value = { source: 'email', ...message, email: String(message.email).trim().toLowerCase() }
    isSidebarOpen.value = sidebar
    contact.value = null
    ownership.value = null
    personalInfo.value = null
    status.value = 'loading'
    error.value = null
    resetTimeline()

    try {
      const { data } = await api.get('/contacts/lookup', { params: { email: context.value.email } })
      if (id !== focusId) return

      contact.value = data.data
      ownership.value = data.meta?.owned_by_colleague ?? null
      personalInfo.value = data.meta?.personal_cards ?? null
      status.value = data.data ? 'ready' : ownership.value ? 'owned_elsewhere' : 'not_found'

      if (data.data) {
        await logContextEmail(id)
        await loadTimeline({ reset: true })
        importHistoryInBackground(id)
      }
    } catch (e) {
      if (id === focusId) setError(e)
    }
  }

  /** "Klienci" list: open the full profile (sidebar stays closed). */
  async function openContact(contactId, { sidebar = false } = {}) {
    const id = ++focusId
    context.value = { source: 'list', contactId }
    isSidebarOpen.value = sidebar
    if (contact.value?.id !== contactId) contact.value = null
    ownership.value = null
    status.value = 'loading'
    error.value = null
    resetTimeline()

    try {
      const { data } = await api.get(`/contacts/${contactId}`)
      if (id !== focusId) return
      contact.value = data.data
      status.value = 'ready'
      await loadTimeline({ reset: true })
      importHistoryInBackground(id)
    } catch (e) {
      if (id === focusId) setError(e)
    }
  }

  /**
   * Past Gmail correspondence: full import the first time a contact is
   * opened or created, afterwards only new emails (see useGoogleStore).
   */
  async function importHistoryInBackground(id, { manual = false } = {}) {
    const google = useGoogleStore()
    const target = contact.value
    if (!target || !google.enabled) return
    const result = manual ? await google.importHistory(target) : await google.maybeAutoImport(target)
    if (!result || id !== focusId || contact.value?.id !== target.id) return
    contact.value.email_history_synced_at = result.synced_at
    if (result.imported || result.updated) await loadTimeline({ reset: true })
  }

  /** "Wczytaj historię z Gmaila" button on the timeline. */
  const importHistory = () => importHistoryInBackground(focusId, { manual: true })

  function closeSidebar() {
    isSidebarOpen.value = false
  }

  /** Forget everything (logout / account switch). */
  function clear() {
    focusId++
    context.value = null
    contact.value = null
    ownership.value = null
    status.value = 'idle'
    error.value = null
    isSidebarOpen.value = false
    resetTimeline()
  }

  async function reload() {
    if (contact.value) return openContact(contact.value.id, { sidebar: isSidebarOpen.value })
    if (context.value?.source === 'email') return openEmail(context.value, { sidebar: isSidebarOpen.value })
  }

  // ---------------------------------------------------------------- contact

  /** "Add to CRM" from an email that isn't linked to a contact yet. */
  async function createFromContext(payload = {}) {
    const id = focusId
    const { data } = await call(() =>
      api.post('/contacts', {
        email: context.value?.email,
        name: context.value?.name || null,
        is_client: true,
        ...payload,
      }),
    )
    useInboxStore().rememberSender(data.data)
    if (id !== focusId) return data.data

    contact.value = data.data
    status.value = 'ready'
    await logContextEmail(id)
    await loadTimeline({ reset: true })
    importHistoryInBackground(id)
    return data.data
  }

  async function updateContact(patch) {
    const { data } = await call(() => api.patch(`/contacts/${contact.value.id}`, patch))
    mergeContact(data.data)
    useInboxStore().rememberSender(contact.value)
    return data.data
  }

  async function deleteContact() {
    await call(() => api.delete(`/contacts/${contact.value.id}`))
    useInboxStore().rememberSender(null, { removed: true, email: contact.value.email })
    contact.value = null
    status.value = context.value?.source === 'email' ? 'not_found' : 'idle'
    resetTimeline()
  }

  // ---------------------------------------------------------------- sharing

  /** "Poproś o dostęp" to a colleague's client. */
  async function requestAccess(message = null) {
    const contactId = ownership.value?.contact_id ?? contact.value?.id
    await call(() => api.post(`/contacts/${contactId}/access-requests`, { message }))
    if (ownership.value) ownership.value = { ...ownership.value, access_requested: true }
  }

  /** Share with the team (userId null) or one colleague: { user_id, scopes, email_ids }. */
  async function share(payload) {
    const { data } = await call(() => api.post(`/contacts/${contact.value.id}/shares`, payload))
    const others = (contact.value.shares ?? []).filter((s) => s.id !== data.data.id)
    contact.value.shares = [...others, data.data].sort((a, b) => (a.user ? 1 : 0) - (b.user ? 1 : 0))
    contact.value.access_requests = (contact.value.access_requests ?? []).filter((r) => payload.user_id != null && r.user.id !== payload.user_id)
    return data.data
  }

  async function unshare(shareId) {
    await call(() => api.delete(`/contacts/${contact.value.id}/shares/${shareId}`))
    contact.value.shares = (contact.value.shares ?? []).filter((s) => s.id !== shareId)
  }

  /** "Przekaż opiekę": a colleague becomes the owner; I keep full access. */
  async function transfer(userId) {
    await call(() => api.post(`/contacts/${contact.value.id}/transfer`, { user_id: userId }))
    await reload()
  }

  /** Business correspondence: put one email of my personal card into the team pool (or take it back). */
  async function setTeamShared(activity, shared) {
    const url = `/contacts/${contact.value.id}/activities/${activity.id}/team-share`
    const { data } = await call(() => (shared ? api.post(url) : api.delete(url)))
    timeline.items = timeline.items.map((a) => (a.id === activity.id ? data.data : a))
    return data.data
  }

  async function declineRequest(requestId) {
    await call(() => api.post(`/access-requests/${requestId}/decline`))
    if (contact.value) contact.value.access_requests = (contact.value.access_requests ?? []).filter((r) => r.id !== requestId)
  }

  // ---------------------------------------------------------- custom fields

  async function addCustomField(field) {
    const { data } = await call(() => api.post(`/contacts/${contact.value.id}/custom-fields`, field))
    contact.value.custom_fields = [...(contact.value.custom_fields ?? []), data.data]
    return data.data
  }

  async function updateCustomField(fieldId, patch) {
    const { data } = await call(() => api.patch(`/contacts/${contact.value.id}/custom-fields/${fieldId}`, patch))
    contact.value.custom_fields = contact.value.custom_fields.map((f) => (f.id === fieldId ? data.data : f))
    return data.data
  }

  async function removeCustomField(fieldId) {
    await call(() => api.delete(`/contacts/${contact.value.id}/custom-fields/${fieldId}`))
    contact.value.custom_fields = contact.value.custom_fields.filter((f) => f.id !== fieldId)
  }

  // --------------------------------------------------------------- timeline

  function resetTimeline() {
    Object.assign(timeline, { items: [], page: 1, lastPage: 1, loading: false, error: null })
  }

  async function loadTimeline({ reset = false, type } = {}) {
    if (!contact.value) return
    const id = focusId
    if (type !== undefined) timeline.type = type
    const page = reset || type !== undefined ? 1 : timeline.page + 1

    timeline.loading = true
    timeline.error = null
    try {
      const { data } = await api.get(`/contacts/${contact.value.id}/activities`, {
        params: { page, ...(timeline.type ? { type: timeline.type } : {}) },
      })
      if (id !== focusId) return
      timeline.items = page === 1 ? data.data : [...timeline.items, ...data.data]
      timeline.page = data.meta.current_page
      timeline.lastPage = data.meta.last_page
    } catch (e) {
      if (id === focusId) timeline.error = toApiError(e)
    } finally {
      if (id === focusId) timeline.loading = false
    }
  }

  const hasMoreTimeline = computed(() => timeline.page < timeline.lastPage)

  /** "Zapisz do osi czasu". */
  async function addNote(body) {
    const { data } = await call(() => api.post(`/contacts/${contact.value.id}/notes`, { body }))
    prependActivity(data.data)
    return data.data
  }

  // ----------------------------------------------------------- reservations

  /** New reservation for the focused client; linked to the focused email if any. */
  async function addReservation(payload) {
    const { data } = await call(() =>
      api.post('/reservations', {
        contact_id: contact.value.id,
        source_email_id: context.value?.messageId ?? null,
        ...payload,
      }),
    )
    upsertReservation(data.data)
    await loadTimeline({ reset: true }) // "reservation.created" system entry
    return data.data
  }

  async function updateReservation(reservationId, patch) {
    const { data } = await call(() => api.patch(`/reservations/${reservationId}`, patch))
    upsertReservation(data.data)
    await loadTimeline({ reset: true })
    return data.data
  }

  // ------------------------------------------------------ tasks & reminders

  /** Called by the tasks store after any change so the open profile stays current. */
  function syncTask(task, { removed = false } = {}) {
    if (!contact.value || task.contact_id !== contact.value.id) return
    const others = (contact.value.tasks ?? []).filter((t) => t.id !== task.id)
    contact.value.tasks = removed ? others : sortBy([...others, task], (t) => [t.is_completed ? 1 : 0, t.due_date ?? '9999'])
    contact.value.open_tasks_count = contact.value.tasks.filter((t) => !t.is_completed).length
  }

  /** Called by the reminders store; the profile only lists pending reminders. */
  function syncReminder(reminder, { removed = false } = {}) {
    if (!contact.value || reminder.contact_id !== contact.value.id) return
    const others = (contact.value.reminders ?? []).filter((r) => r.id !== reminder.id)
    contact.value.reminders = removed || reminder.is_done ? others : sortBy([...others, reminder], (r) => [r.remind_at])
  }

  // ---------------------------------------------------------------- helpers

  /** Log the focused Gmail message on the timeline (idempotent server-side). */
  async function logContextEmail(id) {
    const ctx = context.value
    if (!ctx?.messageId || !contact.value || id !== focusId) return
    try {
      await api.post(`/contacts/${contact.value.id}/emails`, {
        message_id: ctx.messageId,
        thread_id: ctx.threadId ?? null,
        subject: ctx.subject ?? null,
        snippet: ctx.snippet ?? null,
        from: ctx.email,
        direction: ctx.direction ?? 'in',
        sent_at: ctx.receivedAt ?? null,
      })
    } catch {
      // Non-critical: the timeline simply won't show this email.
    }
  }

  function mergeContact(updated) {
    // PATCH responses also carry relations; keep anything they don't include.
    contact.value = { ...contact.value, ...Object.fromEntries(Object.entries(updated).filter(([, v]) => v !== undefined)) }
  }

  function upsertReservation(reservation) {
    const others = (contact.value.upcoming_reservations ?? []).filter((r) => r.id !== reservation.id)
    contact.value.upcoming_reservations = reservation.is_upcoming
      ? sortBy([...others, reservation], (r) => [r.starts_at])
      : others
  }

  /** Edit / delete own notes; keeps the notes list under "Dane kontaktowe" and the timeline in sync. */
  async function updateNote(noteId, body) {
    const { data } = await call(() => api.patch(`/contacts/${contact.value.id}/activities/${noteId}`, { body }))
    const replace = (list) => list.map((a) => (a.id === noteId ? data.data : a))
    contact.value.recent_notes = replace(contact.value.recent_notes ?? [])
    timeline.items = replace(timeline.items)
    return data.data
  }

  async function deleteNote(noteId) {
    await call(() => api.delete(`/contacts/${contact.value.id}/activities/${noteId}`))
    timeline.items = timeline.items.filter((a) => a.id !== noteId)
    const wasRecent = (contact.value.recent_notes ?? []).some((a) => a.id === noteId)
    contact.value.notes_count = Math.max(0, (contact.value.notes_count ?? 1) - 1)
    if (wasRecent) {
      // Refill the short list from the server (an older note moves up).
      const { data } = await api.get(`/contacts/${contact.value.id}/activities`, { params: { type: 'note', per_page: 3 } })
      contact.value.recent_notes = data.data
    }
  }

  function prependActivity(activity) {
    if (activity.type === 'note') {
      contact.value.recent_notes = [activity, ...(contact.value.recent_notes ?? [])].slice(0, 3)
      contact.value.notes_count = (contact.value.notes_count ?? 0) + 1
    }
    timeline.items = [activity, ...timeline.items]
    contact.value.last_activity_at = activity.occurred_at
    contact.value.last_activity = { type: activity.type, event: activity.event, occurred_at: activity.occurred_at }
  }

  function setError(e) {
    error.value = toApiError(e)
    status.value = 'error'
  }

  async function call(request) {
    try {
      return await request()
    } catch (e) {
      throw toApiError(e)
    }
  }

  return {
    context,
    contact,
    status,
    ownership,
    personalInfo,
    error,
    isSidebarOpen,
    timeline,
    hasContact,
    displayName,
    email,
    nextReservation,
    openTasks,
    pendingReminders,
    hasMoreTimeline,
    access,
    canManage,
    canSee,
    requestAccess,
    share,
    unshare,
    transfer,
    declineRequest,
    setTeamShared,
    openEmail,
    openContact,
    closeSidebar,
    clear,
    reload,
    createFromContext,
    updateContact,
    deleteContact,
    addCustomField,
    updateCustomField,
    removeCustomField,
    loadTimeline,
    addNote,
    updateNote,
    deleteNote,
    importHistory,
    addReservation,
    updateReservation,
    syncTask,
    syncReminder,
  }
})

/** Sections a share can cover (see the backend's ContactAccess::SCOPES). */
export const SHARE_SCOPES = ['details', 'notes', 'emails', 'reservations', 'work']

function sortBy(items, key) {
  return [...items].sort((a, b) => {
    const ka = key(a)
    const kb = key(b)
    for (let i = 0; i < ka.length; i++) {
      if (ka[i] < kb[i]) return -1
      if (ka[i] > kb[i]) return 1
    }
    return 0
  })
}
