import { GOOGLE_CLIENT_ID, GOOGLE_MESSAGE_TYPE, GOOGLE_SCOPES } from '@/shared/lib/config.js'

const GOOGLE_API = 'https://www.googleapis.com/'
const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth'
const PROFILE_URL = 'https://www.googleapis.com/gmail/v1/users/me/profile'
/** Access tokens (memory only, survive service-worker restarts, cleared with the browser session). */
const TOKENS_KEY = 'gcrm:google-tokens'
/** Accounts that granted access, so the token can be renewed silently after a browser restart. */
const ACCOUNTS_KEY = 'gcrm:google-accounts'
/** Renew a little before Google's expiry. */
const EXPIRY_MARGIN_MS = 60_000

const normalize = (email) => String(email ?? '').trim().toLowerCase()

class AuthError extends Error {
  constructor(code, message) {
    super(message ?? code)
    this.code = code
  }
}

/**
 * OAuth 2.0 (token flow) for one specific Google account. login_hint makes
 * Google use the account of the Gmail tab instead of Chrome's profile account;
 * prompt=none renews silently from the browser's Google session.
 */
export function buildAuthUrl({ clientId, redirectUri, account, interactive }) {
  const params = new URLSearchParams({
    client_id: clientId,
    response_type: 'token',
    redirect_uri: redirectUri,
    scope: GOOGLE_SCOPES.join(' '),
    include_granted_scopes: 'true',
  })
  if (account) params.set('login_hint', account)
  if (!interactive) params.set('prompt', 'none')
  return `${AUTH_URL}?${params}`
}

/** Parse the redirect (https://<id>.chromiumapp.org/#access_token=…) into a token or an AuthError. */
export function parseAuthRedirect(redirectUrl, now = Date.now()) {
  const fragment = new URL(redirectUrl).hash.replace(/^#/, '')
  const params = new URLSearchParams(fragment)
  const error = params.get('error')
  if (error) throw new AuthError(error === 'access_denied' ? 'access_denied' : 'not_connected', error)

  const token = params.get('access_token')
  if (!token) throw new AuthError('not_connected', 'No access token in the Google response.')

  const granted = (params.get('scope') ?? '').split(' ')
  const missing = GOOGLE_SCOPES.filter((scope) => !granted.includes(scope))
  if (missing.length) throw new AuthError('missing_scopes', `Missing Google permissions: ${missing.join(', ')}`)

  return { token, expiresAt: now + Number(params.get('expires_in') ?? 3600) * 1000 }
}

const storage = {
  async tokens() {
    return (await chrome.storage.session.get(TOKENS_KEY))[TOKENS_KEY] ?? {}
  },
  async setToken(account, entry) {
    const tokens = await storage.tokens()
    if (entry) tokens[account] = entry
    else delete tokens[account]
    await chrome.storage.session.set({ [TOKENS_KEY]: tokens })
  },
  async accounts() {
    return (await chrome.storage.local.get(ACCOUNTS_KEY))[ACCOUNTS_KEY] ?? []
  },
  async setAccount(account, connected) {
    const accounts = (await storage.accounts()).filter((a) => a !== account)
    if (connected) accounts.push(account)
    await chrome.storage.local.set({ [ACCOUNTS_KEY]: accounts })
  },
}

function launchAuthFlow(url, interactive) {
  return new Promise((resolve, reject) => {
    chrome.identity.launchWebAuthFlow({ url, interactive }, (redirectUrl) => {
      const error = chrome.runtime.lastError
      if (error || !redirectUrl) reject(new AuthError('not_connected', error?.message ?? 'Google authorization was cancelled.'))
      else resolve(redirectUrl)
    })
  })
}

async function revoke(token) {
  await fetch(`https://oauth2.googleapis.com/revoke?token=${encodeURIComponent(token)}`, { method: 'POST' }).catch(() => {})
}

/** Make sure Google really authorised the Gmail tab's account (e.g. not another account picked on the consent screen). */
async function assertAccount(token, account) {
  if (!account) return
  const response = await fetch(PROFILE_URL, { headers: { Authorization: `Bearer ${token}` } })
  const profile = response.ok ? await response.json() : null
  if (normalize(profile?.emailAddress) !== account) {
    await revoke(token)
    throw new AuthError('wrong_account', `Google access was granted for ${profile?.emailAddress ?? 'another account'} instead of ${account}.`)
  }
}

async function requestToken(account, interactive) {
  const url = buildAuthUrl({ clientId: GOOGLE_CLIENT_ID, redirectUri: chrome.identity.getRedirectURL(), account, interactive })
  const entry = parseAuthRedirect(await launchAuthFlow(url, interactive))
  await assertAccount(entry.token, account)
  if (account) {
    await storage.setToken(account, entry)
    await storage.setAccount(account, true)
  }
  return entry.token
}

/**
 * Token for the account: cached → silent renewal (if the account connected
 * before) → consent screen (interactive only).
 */
async function getToken(account, { interactive = false } = {}) {
  if (account) {
    const cached = (await storage.tokens())[account]
    if (cached && cached.expiresAt - EXPIRY_MARGIN_MS > Date.now()) return cached.token
    if ((await storage.accounts()).includes(account)) {
      try {
        return await requestToken(account, false)
      } catch (error) {
        if (!interactive) throw error
      }
    }
  }
  if (!interactive) throw new AuthError('not_connected', 'Google account is not connected.')
  return requestToken(account, true)
}

async function forgetToken(account) {
  if (account) await storage.setToken(account, null)
}

/**
 * Google API call on behalf of the Gmail tab's account. The token never
 * leaves the service worker; only googleapis.com URLs are allowed.
 *
 * request: { account, action: 'status' | 'connect' | 'disconnect' } or
 *          { account, method, url, body }
 */
async function handleGoogleRequest(request) {
  if (!GOOGLE_CLIENT_ID || !chrome.identity?.launchWebAuthFlow) {
    return { error: { code: 'google_disabled', message: 'Google integration is not configured.' } }
  }
  const account = normalize(request.account) || null

  if (request.action === 'status' || request.action === 'connect') {
    try {
      await getToken(account, { interactive: request.action === 'connect' })
      return { connected: true, account }
    } catch (error) {
      return { connected: false, error: { code: error.code ?? 'not_connected', message: error.message } }
    }
  }

  if (request.action === 'disconnect') {
    const cached = account ? (await storage.tokens())[account] : null
    if (cached) await revoke(cached.token)
    await forgetToken(account)
    if (account) await storage.setAccount(account, false)
    return { connected: false }
  }

  const url = String(request.url ?? '')
  if (!url.startsWith(GOOGLE_API)) return { error: { code: 'forbidden', message: 'Request blocked by the extension.' } }

  for (let attempt = 0; attempt < 2; attempt++) {
    let token
    try {
      token = await getToken(account)
    } catch (error) {
      return { error: { code: error.code ?? 'not_connected', message: error.message } }
    }

    const response = await fetch(url, {
      method: request.method ?? 'GET',
      headers: { Authorization: `Bearer ${token}`, ...(request.body ? { 'Content-Type': 'application/json' } : {}) },
      body: request.body ? JSON.stringify(request.body) : undefined,
    })

    // Expired/revoked token: forget it and renew once.
    if (response.status === 401 && attempt === 0) {
      await forgetToken(account)
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
