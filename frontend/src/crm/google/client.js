import { GOOGLE_MESSAGE_TYPE } from '@/shared/lib/config.js'
import { googleAccount } from './account.js'

export class GoogleError extends Error {
  constructor(message, { code = 'google_error', status = 0 } = {}) {
    super(message)
    this.name = 'GoogleError'
    this.code = code
    this.status = status
  }
}

/** Ask the extension's service worker to talk to Google (it holds the OAuth token). */
function send(request) {
  return new Promise((resolve, reject) => {
    try {
      chrome.runtime.sendMessage({ type: GOOGLE_MESSAGE_TYPE, request: { account: googleAccount(), ...request } }, (reply) => {
        const lastError = chrome.runtime.lastError
        if (lastError) reject(new GoogleError(lastError.message, { code: 'extension_error' }))
        else resolve(reply ?? {})
      })
    } catch (error) {
      reject(new GoogleError(error.message, { code: 'extension_reloaded' }))
    }
  })
}

export async function googleStatus() {
  return Boolean((await send({ action: 'status' })).connected)
}

/** Opens Google's consent screen when needed. */
export async function googleConnect() {
  const reply = await send({ action: 'connect' })
  if (!reply.connected) throw new GoogleError(reply.error?.message ?? 'not_connected', { code: reply.error?.code ?? 'not_connected' })
  return true
}

export async function googleDisconnect() {
  await send({ action: 'disconnect' })
}

/** @returns {Promise<any>} parsed JSON; throws GoogleError on HTTP >= 400 */
export async function googleApi(method, url, body) {
  const reply = await send({ method, url, body })
  if (reply.error) throw new GoogleError(reply.error.message, { code: reply.error.code })
  if (reply.status >= 400) {
    throw new GoogleError(reply.data?.error?.message ?? `Google API ${reply.status}`, { code: 'http_error', status: reply.status })
  }
  return reply.data
}
