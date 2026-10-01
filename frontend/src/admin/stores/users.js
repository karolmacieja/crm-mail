import { defineStore } from 'pinia'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { adminApi } from '@/admin/api.js'

/** Accounts ("Użytkownicy"). Only the Master Admin manages them. */
export const useUsersStore = defineStore('adminUsers', () => {
  const list = createPaginatedList((params) => adminApi.get('/admin/users', { params }), { search: '', group_id: '', role: '' })

  /** @param {{name, email, password, role, group_id?, license_id?}} payload */
  async function create(payload) {
    const user = (await adminApi.post('/admin/users', payload)).data.data
    await list.load()
    return user
  }

  async function update(id, payload) {
    const user = (await adminApi.patch(`/admin/users/${id}`, payload)).data.data
    list.upsert(user)
    return user
  }

  async function remove(user) {
    await adminApi.delete(`/admin/users/${user.id}`)
    list.remove(user.id)
  }

  /** Sign the user out of every Gmail extension. */
  async function revokeTokens(user) {
    return (await adminApi.post(`/admin/users/${user.id}/revoke-tokens`)).data.revoked
  }

  return { list: list.state, load: list.load, create, update, remove, revokeTokens }
})
