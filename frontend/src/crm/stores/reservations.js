import { defineStore } from 'pinia'
import { api, toApiError } from '@/crm/api.js'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { useClientStore } from './client.js'
import { useDashboardStore } from './dashboard.js'
import { useGoogleStore } from './google.js'
import { useRemindersStore } from './reminders.js'

/**
 * "Rezerwacje": bookings of the clients I can see, in the same windows as
 * tasks and reminders – overdue (open, past days), today, next 7 days.
 */
export const useReservationsStore = defineStore('reservations', () => {
  const list = createPaginatedList((params) => api.get('/reservations', { params }), { window: 'today', search: '' })
  list.state.meta.per_page = 50

  function afterChange(item, options = {}) {
    useClientStore().syncReservation(item, options)
    useDashboardStore().invalidate()
    const google = useGoogleStore()
    // Bookings I add land in my Google Calendar (Settings → Calendar), with their reminder.
    google.syncCalendar('reservation', item, options)
    if (item.auto_reminder) {
      google.syncCalendar('reminder', item.auto_reminder)
      const reminders = useRemindersStore()
      if (reminders.list.loaded) reminders.load()
    }
  }

  async function create(payload) {
    try {
      const reservation = (await api.post('/reservations', payload)).data.data
      if (list.state.loaded) await list.load()
      afterChange(reservation)
      return reservation
    } catch (e) {
      throw toApiError(e)
    }
  }

  async function update(reservation, patch) {
    try {
      const updated = (await api.patch(`/reservations/${reservation.id}`, patch)).data.data
      list.upsert(updated)
      afterChange(updated)
      return updated
    } catch (e) {
      throw toApiError(e)
    }
  }

  const setStatus = (reservation, status) => update(reservation, { status })

  async function remove(reservation) {
    try {
      await api.delete(`/reservations/${reservation.id}`)
      list.remove(reservation.id)
      afterChange(reservation, { removed: true })
    } catch (e) {
      throw toApiError(e)
    }
  }

  return { list: list.state, load: list.load, create, update, setStatus, remove, clear: () => list.reset() }
})
