/** Laravel API base URL including the /api prefix, without trailing slash. */
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL.replace(/\/+$/, '')

export const INBOXSDK_APP_ID = import.meta.env.VITE_INBOXSDK_APP_ID

/** chrome.storage.local key holding { token, expires_at, user }. */
export const AUTH_STORAGE_KEY = 'gcrm.auth'

/** chrome.storage.local key holding the UI language ('pl' | 'en'). */
export const LOCALE_STORAGE_KEY = 'gcrm.locale'

/** InboxSDK custom route id for the full-page dashboard. */
export const DASHBOARD_ROUTE_ID = 'gastroflowx'

export const REQUEST_TIMEOUT_MS = 20_000

/** chrome.runtime message type used to proxy API calls through the service worker. */
export const API_MESSAGE_TYPE = 'gcrm:api-request'

/** Labels live in the locale files under `status.<value>`. */
export const CONTACT_STATUSES = [
  { value: 'lead', classes: 'bg-sky-100 text-sky-800 ring-sky-200' },
  { value: 'prospect', classes: 'bg-amber-100 text-amber-800 ring-amber-200' },
  { value: 'customer', classes: 'bg-emerald-100 text-emerald-800 ring-emerald-200' },
  { value: 'inactive', classes: 'bg-gray-100 text-gray-700 ring-gray-200' },
]
