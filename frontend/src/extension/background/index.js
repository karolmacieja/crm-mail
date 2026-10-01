// InboxSDK MV3 support: injects pageWorld.js into Gmail's MAIN world on request.
import '@inboxsdk/core/background.js'
import { API_BASE_URL, API_MESSAGE_TYPE } from '@/shared/lib/config.js'
import { clearAuth, getAuth } from '@/extension/storage.js'
import { registerGoogleHandler } from './google.js'

const ALLOWED_METHODS = new Set(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])
const FORWARDED_HEADERS = new Set(['accept', 'accept-language', 'content-type', 'x-requested-with'])

/**
 * Executes an API request on behalf of a content script / popup.
 * Only requests to our own API are allowed, and the bearer token is attached
 * here so it never has to travel through page-adjacent code.
 */
async function performApiRequest(request) {
  const url = String(request?.url ?? '')
  const method = String(request?.method ?? 'GET').toUpperCase()

  if (!url.startsWith(`${API_BASE_URL}/`) || !ALLOWED_METHODS.has(method)) {
    return { error: { code: 'ERR_FORBIDDEN_REQUEST', message: 'Request blocked by the extension.' } }
  }

  const headers = new Headers()
  for (const [name, value] of Object.entries(request.headers ?? {})) {
    if (FORWARDED_HEADERS.has(name.toLowerCase()) && value != null) headers.set(name, String(value))
  }
  headers.set('X-Client-Version', chrome.runtime.getManifest().version)

  const auth = await getAuth()
  if (auth?.token) headers.set('Authorization', `Bearer ${auth.token}`)

  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), Number(request.timeout) || 20_000)

  try {
    const response = await fetch(url, {
      method,
      headers,
      body: method === 'GET' ? undefined : (request.body ?? undefined),
      signal: controller.signal,
      credentials: 'omit',
      cache: 'no-store',
    })

    const text = await response.text()
    let data = text
    if (text && (response.headers.get('content-type') ?? '').includes('application/json')) {
      try {
        data = JSON.parse(text)
      } catch {
        data = text
      }
    }

    // Token revoked/expired server-side: drop it so every open UI logs out.
    if (response.status === 401 && auth?.token) {
      await clearAuth()
    }

    return {
      status: response.status,
      statusText: response.statusText,
      headers: Object.fromEntries(response.headers.entries()),
      data: data === '' ? null : data,
    }
  } catch (error) {
    const aborted = error?.name === 'AbortError'
    return {
      error: {
        code: aborted ? 'ECONNABORTED' : 'ERR_NETWORK',
        message: aborted ? 'Request timed out.' : (error?.message ?? 'Network error'),
      },
    }
  } finally {
    clearTimeout(timer)
  }
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (message?.type !== API_MESSAGE_TYPE || sender.id !== chrome.runtime.id) {
    return false
  }

  performApiRequest(message.request)
    .then(sendResponse)
    .catch((error) => sendResponse({ error: { code: 'ERR_INTERNAL', message: error?.message ?? 'Unexpected error' } }))

  return true // keep the message channel open for the async response
})

registerGoogleHandler()
