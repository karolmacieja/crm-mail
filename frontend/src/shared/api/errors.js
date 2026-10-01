import { AxiosError } from 'axios'
import { t } from '@/shared/lib/i18n.js'

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
    case 419:
      // Web panel: CSRF token / session expired.
      return new ApiError({ status, code: 'session_expired', message: t('errors.sessionExpired'), cause: error })
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

/** Subscribe to every failed API call (auth stores react to 401/402). */
export function onApiError(listener) {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

/** Notify subscribers; used by every client's response interceptor. */
export function notifyApiError(error) {
  listeners.forEach((listener) => {
    try {
      listener(error)
    } catch (listenerError) {
      console.error('[GastroFlowx] API error listener failed', listenerError)
    }
  })
}
