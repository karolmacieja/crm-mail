import axios, { AxiosError, AxiosHeaders } from 'axios'
import { API_BASE_URL, API_MESSAGE_TYPE, REQUEST_TIMEOUT_MS } from './config.js'
import { locale, t } from './i18n.js'

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

/** Normalized error thrown by every API call. */
export class ApiError extends Error {
  constructor({ message, status = 0, code = 'unknown', fieldErrors = {}, cause = null }) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fieldErrors = fieldErrors
    this.cause = cause
  }

  get isLicenseError() {
    return this.status === 402
  }

  get isUnauthenticated() {
    return this.status === 401
  }

  get isValidationError() {
    return this.status === 422
  }
}

export function toApiError(error) {
  if (error instanceof ApiError) return error

  if (error?.code === 'ERR_EXTENSION_CONTEXT' || /context invalidated/i.test(error?.message ?? '')) {
    return new ApiError({ message: t('errors.extensionReloaded'), code: 'extension_reloaded', cause: error })
  }

  const response = error?.response
  if (!response) {
    const timedOut = error?.code === AxiosError.ECONNABORTED || error?.code === AxiosError.ETIMEDOUT
    return new ApiError({
      message: timedOut ? t('errors.timeout') : t('errors.network'),
      code: timedOut ? 'timeout' : 'network_error',
      cause: error,
    })
  }

  const { status, data, headers } = response
  const serverMessage = typeof data?.message === 'string' && data.message ? data.message : null

  switch (status) {
    case 401:
      return new ApiError({ status, code: 'unauthenticated', message: t('errors.unauthenticated'), cause: error })
    case 402:
      return new ApiError({ status, code: data?.code ?? 'license_invalid', message: t('errors.licenseInvalid'), cause: error })
    case 403:
      // The API explains 403s (wrong app, no restaurant, inactive restaurant) in the request language.
      return new ApiError({ status, code: data?.code ?? 'forbidden', message: serverMessage ?? t('errors.forbidden'), cause: error })
    case 404:
      return new ApiError({ status, code: 'not_found', message: t('errors.notFound'), cause: error })
    case 422:
      return new ApiError({
        status,
        code: 'validation_failed',
        message: t('errors.validation'),
        fieldErrors: Object.fromEntries(Object.entries(data?.errors ?? {}).map(([field, messages]) => [field, messages[0]])),
        cause: error,
      })
    case 429: {
      const retryAfter = Number(headers?.get?.('retry-after') ?? headers?.['retry-after'])
      return new ApiError({
        status,
        code: 'rate_limited',
        message: retryAfter ? t('errors.rateLimitedIn', { seconds: retryAfter }) : t('errors.rateLimited'),
        cause: error,
      })
    }
    default:
      return new ApiError({
        status,
        code: status >= 500 ? 'server_error' : 'request_failed',
        message: status >= 500 ? t('errors.server') : (serverMessage ?? t('errors.requestFailed', { status })),
        cause: error,
      })
  }
}

const listeners = new Set()

/** Subscribe to every failed API call (used by the auth store for 401/402). */
export function onApiError(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
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
    listeners.forEach((listener) => {
      try {
        listener(apiError)
      } catch (listenerError) {
        console.error('[Gmail CRM] API error listener failed', listenerError)
      }
    })
    return Promise.reject(apiError)
  },
)
