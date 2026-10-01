import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { onApiError, toApiError } from '@/shared/api/errors.js'
import { adminApi, ensureCsrfCookie } from '@/admin/api.js'

/**
 * Master Admin session (cookie-based). The server is the source of truth:
 * on load we ask /api/web/me whether the session cookie is still valid.
 */
export const useAdminAuthStore = defineStore('adminAuth', () => {
  const user = ref(null)
  const ready = ref(false)
  let readyPromise = null

  const isAuthenticated = computed(() => user.value !== null)
  const role = computed(() => user.value?.role ?? null)
  // The web panel has no license gate; kept for the shared router guard.
  const hasValidLicense = computed(() => true)

  onApiError((error) => {
    if (error.status === 401 || error.status === 419) user.value = null
  })

  function ensureReady() {
    readyPromise ??= adminApi
      .get('/web/me')
      .then(({ data }) => {
        user.value = data.data
      })
      .catch(() => {
        user.value = null
      })
      .finally(() => {
        ready.value = true
      })

    return readyPromise
  }

  async function login({ email, password, remember = false }) {
    try {
      await ensureCsrfCookie({ force: true })
      const { data } = await adminApi.post('/web/login', { email, password, remember })
      user.value = data.user
      ready.value = true
      return data.user
    } catch (error) {
      throw toApiError(error)
    }
  }

  async function logout() {
    try {
      await adminApi.post('/web/logout')
    } catch {
      // Session may already be gone; the local state is what matters.
    } finally {
      user.value = null
    }
  }

  return { user, ready, isAuthenticated, role, hasValidLicense, ensureReady, login, logout }
})
