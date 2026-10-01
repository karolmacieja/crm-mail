import { defineStore } from 'pinia'
import { computed } from 'vue'
import { api, toApiError } from '@/crm/api.js'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { useClientStore } from './client.js'
import { useDashboardStore } from './dashboard.js'

/**
 * "Moduł Zadań". Board columns follow the mockup:
 * Pilne (high priority or due today/overdue), Kontakt i Follow-up, Oferty, Wewnętrzne.
 */
export const useTasksStore = defineStore('tasks', () => {
  const list = createPaginatedList((params) => api.get('/tasks', { params }), {
    window: 'open',
    type: '',
    search: '',
    assigned_to: '',
  })
  list.state.meta.per_page = 100

  const board = computed(() => {
    const columns = { urgent: [], follow_up: [], offer: [], internal: [] }
    for (const task of list.state.items) {
      const urgent = !task.is_completed && (task.priority === 'high' || ['overdue', 'today'].includes(task.time_status))
      columns[urgent ? 'urgent' : task.type]?.push(task)
    }
    return columns
  })

  function afterChange(task, options) {
    useClientStore().syncTask(task, options)
    useDashboardStore().invalidate()
  }

  async function create(payload) {
    try {
      const task = (await api.post('/tasks', payload)).data.data
      await list.load()
      afterChange(task)
      return task
    } catch (e) {
      throw toApiError(e)
    }
  }

  async function update(task, patch) {
    try {
      const updated = (await api.patch(`/tasks/${task.id}`, patch)).data.data
      list.upsert(updated)
      afterChange(updated)
      return updated
    } catch (e) {
      throw toApiError(e)
    }
  }

  const toggle = (task) => update(task, { is_completed: !task.is_completed })

  async function remove(task) {
    try {
      await api.delete(`/tasks/${task.id}`)
      list.remove(task.id)
      afterChange(task, { removed: true })
    } catch (e) {
      throw toApiError(e)
    }
  }

  function clear() {
    list.reset()
  }

  return { clear, list: list.state, load: list.load, board, create, update, toggle, remove }
})
