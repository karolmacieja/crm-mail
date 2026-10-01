import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, onApiError, toApiError } from '@/lib/api.js'
import { clearAuth, getAuth, onAuthChanged, setAuth } from '@/lib/storage.js'

export const useAuthStore = defineStore('auth', () => {
  const ready = ref(false)
  const session = ref(null) // { token, expires_at, user }
  const licenseError = ref(null) // { code, message } from a 402 response

  let initPromise = null

  const isAuthenticated = computed(() => Boolean(session.value?.token))
  const user = computed(() => session.value?.user ?? null)
  const license = computed(() => user.value?.license ?? null)
  const hasValidLicense = computed(() => Boolean(license.value?.is_valid) && !licenseError.value)

  function applySession(next) {
    const previousUserId = session.value?.user?.id
    session.value = next
    if (!next || next.user?.id !== previousUserId) licenseError.value = null
  }

  /** Idempotent: safe to call from every mounted app (sidebar, dashboard, popup). */
  function init() {
    initPromise ??= (async () => {
      applySession(await getAuth())
      onAuthChanged(applySession)

      onApiError((error) => {
        if (error.isLicenseError) {
          const firstNotice = !licenseError.value
          licenseError.value = { code: error.code, message: error.message }
          // Cached license data is stale -> fetch the real expiry date to display.
          if (firstNotice) refreshUser().catch(() => {})
        }
        // 401s: the service worker already cleared storage -> onAuthChanged logs us out.
      })

      ready.value = true

      // Refresh license/account info in the background on every page load.
      if (session.value?.token) refreshUser().catch(() => {})
    })()

    return initPromise
  }

  async function login({ email, password }) {
    try {
      const { data } = await api.post('/auth/login', {
        email,
        password,
        device_name: `Gmail CRM extension (${navigator.platform || 'Chrome'})`,
      })

      const next = { token: data.token, expires_at: data.expires_at, user: data.user }
      applySession(next)
      await setAuth(next)
      return data.user
    } catch (error) {
      throw toApiError(error)
    }
  }

  async function logout() {
    try {
      if (session.value?.token) await api.post('/auth/logout')
    } catch {
      // Token may already be invalid; logging out locally is what matters.
    } finally {
      applySession(null)
      await clearAuth()
    }
  }

  async function refreshUser() {
    const { data } = await api.get('/auth/me')
    const current = await getAuth()
    if (!current?.token) return null

    const next = { ...current, user: data.data }
    applySession(next)
    if (next.user.license?.is_valid) licenseError.value = null
    await setAuth(next)
    return next.user
  }

  return {
    ready,
    session,
    licenseError,
    isAuthenticated,
    user,
    license,
    hasValidLicense,
    init,
    login,
    logout,
    refreshUser,
  }
})
