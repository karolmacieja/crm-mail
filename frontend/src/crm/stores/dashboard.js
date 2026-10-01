import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, toApiError } from '@/crm/api.js'

/**
 * Dashboard numbers: separate counters for tasks and reminders
 * (overdue / today / 7 days), contacts, reservations, emails.
 */
export const useDashboardStore = defineStore('dashboard', () => {
  const summary = ref(null)
  const loading = ref(false)
  const error = ref(null)
  /** Selected tab per panel, as in the mockup ("Zaległe", "Dziś", "7 dni"). */
  const tabs = ref({ tasks: 'today', reminders: 'overdue' })

  let refreshQueued = false

  async function fetch() {
    loading.value = true
    error.value = null
    try {
      summary.value = (await api.get('/dashboard/summary')).data.data
    } catch (e) {
      error.value = toApiError(e)
    } finally {
      loading.value = false
    }
  }

  /** Refresh after a mutation elsewhere, but only if the dashboard was loaded; batched per tick. */
  function invalidate() {
    if (!summary.value || refreshQueued) return
    refreshQueued = true
    queueMicrotask(() => {
      refreshQueued = false
      fetch()
    })
  }

  function clear() {
    summary.value = null
    error.value = null
  }

  return { summary, loading, error, tabs, fetch, invalidate, clear }
})
