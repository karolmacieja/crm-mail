import axios, { AxiosError, AxiosHeaders } from 'axios'
import { API_BASE_URL, API_MESSAGE_TYPE, REQUEST_TIMEOUT_MS } from '@/shared/lib/config.js'
import { ApiError, notifyApiError, onApiError, toApiError } from '@/shared/api/errors.js'
import { locale } from '@/shared/lib/i18n.js'

// Re-exported so CRM code imports everything API-related from one place.
export { ApiError, onApiError, toApiError }

/**
 * Axios adapter that forwards the request to the extension service worker.
 *
 * Why: requests made from the Gmail content script carry the
 * `https://mail.google.com` origin and are subject to CORS and Gmail's
 * network quirks. The service worker has host_permissions for the API, keeps
 * the bearer token out of the content script, and centrally handles 401s.
 */
function backgroundAdapter(config) {
  return new Promise((resolve, reject) => {
    const request = {
      url: axios.getUri(config),
      method: (config.method ?? 'get').toUpperCase(),
      headers: AxiosHeaders.from(config.headers).toJSON(true),
      body: config.data ?? null,
      timeout: config.timeout || REQUEST_TIMEOUT_MS,
    }

    const handleReply = (reply) => {
      if (!reply) {
        reject(new AxiosError('No response from the extension background worker.', AxiosError.ERR_NETWORK, config))
        return
      }
      if (reply.error) {
        reject(new AxiosError(reply.error.message, reply.error.code, config))
        return
      }

      const response = {
        data: reply.data,
        status: reply.status,
        statusText: reply.statusText,
        headers: AxiosHeaders.from(reply.headers),
        config,
        request: null,
      }

      if (!config.validateStatus || config.validateStatus(response.status)) {
        resolve(response)
      } else {
        reject(
          new AxiosError(
            `Request failed with status code ${response.status}`,
            response.status >= 500 ? AxiosError.ERR_BAD_RESPONSE : AxiosError.ERR_BAD_REQUEST,
            config,
            null,
            response,
          ),
        )
      }
    }

    try {
      chrome.runtime.sendMessage({ type: API_MESSAGE_TYPE, request }, (reply) => {
        const lastError = chrome.runtime.lastError
        if (lastError) {
          reject(new AxiosError(lastError.message, 'ERR_EXTENSION', config))
          return
        }
        handleReply(reply)
      })
    } catch (error) {
      // Thrown synchronously when the extension was reloaded/updated while
      // this Gmail tab stayed open ("Extension context invalidated").
      reject(new AxiosError(error.message, 'ERR_EXTENSION_CONTEXT', config))
    }
  })
}

export const api = axios.create({
  baseURL: API_BASE_URL,
  timeout: REQUEST_TIMEOUT_MS,
  adapter: backgroundAdapter,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// The API localises validation messages based on this header.
api.interceptors.request.use((config) => {
  config.headers.set('Accept-Language', locale.value)
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const apiError = toApiError(error)
    notifyApiError(apiError)
    return Promise.reject(apiError)
  },
)
