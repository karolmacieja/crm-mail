import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiError } from '@/shared/api/errors.js'
import { adminApi } from '@/admin/api.js'

export const useAdminStatsStore = defineStore('adminStats', () => {
  const data = ref(null)
  const loading = ref(false)
  const error = ref(null)

  async function fetch() {
    loading.value = true
    error.value = null
    try {
      data.value = (await adminApi.get('/admin/stats')).data.data
    } catch (e) {
      error.value = toApiError(e)
    } finally {
      loading.value = false
    }
  }

  return { data, loading, error, fetch }
})
