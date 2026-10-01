import axios from 'axios'
import { notifyApiError, toApiError } from '@/shared/api/errors.js'
import { API_BASE_URL, REQUEST_TIMEOUT_MS } from '@/shared/lib/config.js'
import { locale } from '@/shared/lib/i18n.js'

/**
 * HTTP client of the Master Admin web panel: Sanctum SPA authentication.
 * The browser keeps the session cookie; axios copies the XSRF-TOKEN cookie
 * into the X-XSRF-TOKEN header (withXSRFToken is needed because the API
 * lives on another subdomain, e.g. api.domena.pl vs app.domena.pl).
 */
const csrfUrl = `${new URL(API_BASE_URL).origin}/sanctum/csrf-cookie`

const common = {
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  timeout: REQUEST_TIMEOUT_MS,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
}

export const adminApi = axios.create({ ...common, baseURL: API_BASE_URL })

let csrfPromise = null

/** Fetch the CSRF cookie once; call again with force after a 419. */
export function ensureCsrfCookie({ force = false } = {}) {
  if (force) csrfPromise = null
  csrfPromise ??= axios.get(csrfUrl, common).catch((error) => {
    csrfPromise = null
    throw toApiError(error)
  })
  return csrfPromise
}

adminApi.interceptors.request.use(async (config) => {
  config.headers.set('Accept-Language', locale.value)
  if (!['get', 'head', 'options'].includes((config.method ?? 'get').toLowerCase())) {
    await ensureCsrfCookie()
  }
  return config
})

adminApi.interceptors.response.use(
  (response) => response,
  async (error) => {
    // CSRF token rotated / expired: refresh it and retry the request once.
    if (error.response?.status === 419 && !error.config.__retriedCsrf) {
      await ensureCsrfCookie({ force: true })
      return adminApi.request({ ...error.config, __retriedCsrf: true })
    }

    const apiError = toApiError(error)
    notifyApiError(apiError)
    return Promise.reject(apiError)
  },
)
