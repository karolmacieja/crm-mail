import { AUTH_STORAGE_KEY } from './config.js'

/**
 * Persisted auth session in chrome.storage.local:
 * { token: string, expires_at: string|null, user: object }
 *
 * chrome.storage is shared by the popup, the service worker and every Gmail
 * tab, so logging in/out anywhere is propagated everywhere via onChanged.
 */
export async function getAuth() {
  const result = await chrome.storage.local.get(AUTH_STORAGE_KEY)
  const auth = result[AUTH_STORAGE_KEY] ?? null

  if (auth?.expires_at && Date.parse(auth.expires_at) <= Date.now()) {
    await clearAuth()
    return null
  }

  return auth
}

export function setAuth(auth) {
  return chrome.storage.local.set({ [AUTH_STORAGE_KEY]: auth })
}

export function clearAuth() {
  return chrome.storage.local.remove(AUTH_STORAGE_KEY)
}

/** @returns {() => void} unsubscribe */
export function onAuthChanged(callback) {
  const listener = (changes, areaName) => {
    if (areaName === 'local' && AUTH_STORAGE_KEY in changes) {
      callback(changes[AUTH_STORAGE_KEY].newValue ?? null)
    }
  }
  chrome.storage.onChanged.addListener(listener)
  return () => chrome.storage.onChanged.removeListener(listener)
}
