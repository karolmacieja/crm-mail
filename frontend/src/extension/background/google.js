import { GOOGLE_MESSAGE_TYPE } from '@/shared/lib/config.js'

const GOOGLE_API = 'https://www.googleapis.com/'

function getToken(interactive) {
  return new Promise((resolve, reject) => {
    chrome.identity.getAuthToken({ interactive }, (result) => {
      const error = chrome.runtime.lastError
      const token = typeof result === 'string' ? result : result?.token
      if (error || !token) reject(new Error(error?.message ?? 'not_connected'))
      else resolve(token)
    })
  })
}

const removeCachedToken = (token) => new Promise((resolve) => chrome.identity.removeCachedAuthToken({ token }, resolve))

/**
 * Google API call on behalf of the signed-in Chrome user. The token never
 * leaves the service worker; only googleapis.com URLs are allowed.
 *
 * request: { action: 'status' | 'connect' | 'disconnect' } or
 *          { method, url, body, interactive }
 */
async function handleGoogleRequest(request) {
  if (!chrome.identity) return { error: { code: 'google_disabled', message: 'Google integration is not configured.' } }

  if (request.action === 'status' || request.action === 'connect') {
    try {
      await getToken(request.action === 'connect')
      return { connected: true }
    } catch (error) {
      return { connected: false, error: { code: 'not_connected', message: error.message } }
    }
  }

  if (request.action === 'disconnect') {
    try {
      const token = await getToken(false)
      await fetch(`https://oauth2.googleapis.com/revoke?token=${encodeURIComponent(token)}`, { method: 'POST' }).catch(() => {})
      await removeCachedToken(token)
    } catch {
      // Already disconnected.
    }
    return { connected: false }
  }

  const url = String(request.url ?? '')
  if (!url.startsWith(GOOGLE_API)) return { error: { code: 'forbidden', message: 'Request blocked by the extension.' } }

  for (let attempt = 0; attempt < 2; attempt++) {
    let token
    try {
      token = await getToken(Boolean(request.interactive))
    } catch (error) {
      return { error: { code: 'not_connected', message: error.message } }
    }

    const response = await fetch(url, {
      method: request.method ?? 'GET',
      headers: { Authorization: `Bearer ${token}`, ...(request.body ? { 'Content-Type': 'application/json' } : {}) },
      body: request.body ? JSON.stringify(request.body) : undefined,
    })

    // Expired/revoked token: drop it from Chrome's cache and retry once.
    if (response.status === 401 && attempt === 0) {
      await removeCachedToken(token)
      continue
    }

    const text = await response.text()
    let data = null
    try {
      data = text ? JSON.parse(text) : null
    } catch {
      data = text
    }
    return { status: response.status, data }
  }

  return { error: { code: 'not_connected', message: 'Google authorization failed.' } }
}

export function registerGoogleHandler() {
  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.type !== GOOGLE_MESSAGE_TYPE || sender.id !== chrome.runtime.id) return false

    handleGoogleRequest(message.request ?? {})
      .then(sendResponse)
      .catch((error) => sendResponse({ error: { code: 'network_error', message: error?.message ?? 'Network error' } }))

    return true
  })
}
