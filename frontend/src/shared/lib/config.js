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

/**
 * Google integration (Gmail history import + Google Calendar sync) is enabled
 * when the build has an OAuth client id (Google Cloud → Clients → "Web application"
 * with the redirect URI https://<extension-id>.chromiumapp.org/).
 */
export const GOOGLE_CLIENT_ID = import.meta.env.VITE_GOOGLE_OAUTH_CLIENT_ID ?? ''
export const GOOGLE_ENABLED = Boolean(GOOGLE_CLIENT_ID)
export const GOOGLE_SCOPES = ['https://www.googleapis.com/auth/gmail.readonly', 'https://www.googleapis.com/auth/calendar.events']

/** chrome.runtime message type for Google API calls made by the service worker. */
export const GOOGLE_MESSAGE_TYPE = 'gcrm:google-request'

/** chrome.runtime message type used to proxy API calls through the service worker. */
export const API_MESSAGE_TYPE = 'gcrm:api-request'
