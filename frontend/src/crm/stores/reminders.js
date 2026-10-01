import { defineStore } from 'pinia'
import { computed } from 'vue'
import { api, toApiError } from '@/crm/api.js'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { useClientStore } from './client.js'
import { useDashboardStore } from './dashboard.js'
import { useGoogleStore } from './google.js'

/**
 * "Kalendarz Przypomnień", grouped as in the mockup:
 * from emails, about reservations/events, general.
 */
export const useRemindersStore = defineStore('reminders', () => {
  const list = createPaginatedList((params) => api.get('/reminders', { params }), { window: 'open', type: '', search: '' })
  list.state.meta.per_page = 100

  const grouped = computed(() => {
    const groups = { email: [], reservation: [], general: [] }
    for (const reminder of list.state.items) groups[reminder.type]?.push(reminder)
    return groups
  })

  function afterChange(item, options) {
    useClientStore().syncReminder(item, options)
    useDashboardStore().invalidate()
    // Own items also land in the person's Google Calendar (when enabled in Settings).
    useGoogleStore().syncCalendar('reminder', item, options)
  }


  async function create(payload) {
    try {
      const reminder = (await api.post('/reminders', payload)).data.data
      await list.load()
      afterChange(reminder)
      return reminder
    } catch (e) {
      throw toApiError(e)
    }
  }

  async function update(reminder, patch) {
    try {
      const updated = (await api.patch(`/reminders/${reminder.id}`, patch)).data.data
      list.upsert(updated)
      afterChange(updated)
      return updated
    } catch (e) {
      throw toApiError(e)
    }
  }

  const markDone = (reminder, done = true) => update(reminder, { is_done: done })

  async function remove(reminder) {
    try {
      await api.delete(`/reminders/${reminder.id}`)
      list.remove(reminder.id)
      afterChange(reminder, { removed: true })
    } catch (e) {
      throw toApiError(e)
    }
  }

  function clear() {
    list.reset()
  }

  return { clear, list: list.state, load: list.load, grouped, create, update, markDone, remove }
})
