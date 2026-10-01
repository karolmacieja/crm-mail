import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiError } from '@/shared/api/errors.js'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { adminApi } from '@/admin/api.js'

/** Restaurants ("Grupy"). */
export const useGroupsStore = defineStore('adminGroups', () => {
  const list = createPaginatedList((params) => adminApi.get('/admin/groups', { params }), { search: '', is_active: '' })
  const current = ref(null)
  const currentLoading = ref(false)
  const currentError = ref(null)

  /** Lightweight list for <select>s (users / licenses forms). */
  const options = ref([])

  async function loadOptions() {
    const { data } = await adminApi.get('/admin/groups', { params: { per_page: 100 } })
    options.value = data.data.map(({ id, name, is_active }) => ({ id, name, is_active }))
  }

  async function show(id) {
    currentLoading.value = true
    currentError.value = null
    try {
      current.value = (await adminApi.get(`/admin/groups/${id}`)).data.data
    } catch (error) {
      currentError.value = toApiError(error)
    } finally {
      currentLoading.value = false
    }
  }

  async function create(payload) {
    const group = (await adminApi.post('/admin/groups', payload)).data.data
    await Promise.all([list.load(), loadOptions()])
    return group
  }

  async function update(id, payload) {
    const group = (await adminApi.patch(`/admin/groups/${id}`, payload)).data.data
    list.upsert(group)
    if (current.value?.id === id) current.value = group
    return group
  }

  /** Irreversible; the API requires the slug as confirmation. */
  async function remove(group, confirmSlug) {
    await adminApi.delete(`/admin/groups/${group.id}`, { params: { confirm: confirmSlug } })
    list.remove(group.id)
    options.value = options.value.filter((option) => option.id !== group.id)
    if (current.value?.id === group.id) current.value = null
  }

  return { list: list.state, load: list.load, current, currentLoading, currentError, options, loadOptions, show, create, update, remove }
})
