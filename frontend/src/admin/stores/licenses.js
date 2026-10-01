import { defineStore } from 'pinia'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { adminApi } from '@/admin/api.js'

/** Licenses and seat assignments ("Licencje i Przydziały"). */
export const useLicensesStore = defineStore('adminLicenses', () => {
  const list = createPaginatedList((params) => adminApi.get('/admin/licenses', { params }), { group_id: '', state: '' })

  const replace = (license) => {
    list.upsert(license)
    return license
  }

  /** @param {{group_id, seats, days?|expires_at?, plan?, starts_at?, status?, notes?}} payload */
  async function create(payload) {
    const license = (await adminApi.post('/admin/licenses', payload)).data.data
    await list.load()
    return license
  }

  const update = async (id, payload) => replace((await adminApi.patch(`/admin/licenses/${id}`, payload)).data.data)
  const extend = async (id, days) => replace((await adminApi.post(`/admin/licenses/${id}/extend`, { days })).data.data)
  const assign = async (id, userId) => replace((await adminApi.post(`/admin/licenses/${id}/assignments`, { user_id: userId })).data.data)
  const unassign = async (id, userId) => replace((await adminApi.delete(`/admin/licenses/${id}/assignments/${userId}`)).data.data)

  async function remove(license) {
    await adminApi.delete(`/admin/licenses/${license.id}`)
    list.remove(license.id)
  }

  return { list: list.state, load: list.load, create, update, extend, assign, unassign, remove }
})
