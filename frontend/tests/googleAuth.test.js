import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { installChromeStub } from './setup-chrome.js'

const CLIENT_ID = 'client-123.apps.googleusercontent.com'
const SCOPES = 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/calendar.events'
const REDIRECT = 'https://extid.chromiumapp.org/'

let handler
let authFlow
let profileEmail

async function load() {
  vi.resetModules()
  vi.stubEnv('VITE_GOOGLE_OAUTH_CLIENT_ID', CLIENT_ID)
  const module = await import('@/extension/background/google.js')
  module.registerGoogleHandler()
  return module
}

/** Send a request to the service-worker handler and wait for its reply. */
const send = (request) =>
  new Promise((resolve) => handler({ type: 'gcrm:google-request', request }, { id: 'test' }, resolve))

const redirectWith = (params) => `${REDIRECT}#${new URLSearchParams(params)}`

beforeEach(() => {
  installChromeStub()
  const session = {}
  chrome.storage.session = {
    get: vi.fn(async (key) => (key in session ? { [key]: session[key] } : {})),
    set: vi.fn(async (items) => Object.assign(session, items)),
  }
  chrome.runtime.onMessage = { addListener: (fn) => (handler = fn) }
  authFlow = vi.fn((details, callback) => callback(redirectWith({ access_token: 'tok-1', expires_in: '3600', scope: SCOPES })))
  chrome.identity = { launchWebAuthFlow: authFlow, getRedirectURL: () => REDIRECT }
  profileEmail = 'kelner@roma.pl'
  globalThis.fetch = vi.fn(async (url) => {
    if (String(url).includes('/users/me/profile')) return new Response(JSON.stringify({ emailAddress: profileEmail }), { status: 200 })
    if (String(url).includes('oauth2.googleapis.com/revoke')) return new Response('', { status: 200 })
    return new Response(JSON.stringify({ ok: true }), { status: 200 })
  })
})

afterEach(() => vi.unstubAllEnvs())

describe('Google auth for the Gmail tab account', () => {
  it('builds the consent URL with the Gmail account as login_hint', async () => {
    const { buildAuthUrl } = await load()
    const url = new URL(buildAuthUrl({ clientId: CLIENT_ID, redirectUri: REDIRECT, account: 'kelner@roma.pl', interactive: true }))
    expect(url.searchParams.get('login_hint')).toBe('kelner@roma.pl')
    expect(url.searchParams.get('response_type')).toBe('token')
    expect(url.searchParams.get('redirect_uri')).toBe(REDIRECT)
    expect(url.searchParams.has('prompt')).toBe(false)

    const silent = new URL(buildAuthUrl({ clientId: CLIENT_ID, redirectUri: REDIRECT, account: 'kelner@roma.pl', interactive: false }))
    expect(silent.searchParams.get('prompt')).toBe('none')
  })

  it('connects the Gmail tab account and reuses the token for API calls', async () => {
    await load()
    expect(await send({ action: 'connect', account: 'Kelner@Roma.pl' })).toMatchObject({ connected: true, account: 'kelner@roma.pl' })
    expect(authFlow.mock.calls[0][0].interactive).toBe(true)
    expect(new URL(authFlow.mock.calls[0][0].url).searchParams.get('login_hint')).toBe('kelner@roma.pl')

    const reply = await send({ account: 'kelner@roma.pl', method: 'GET', url: 'https://www.googleapis.com/gmail/v1/users/me/messages' })
    expect(reply).toEqual({ status: 200, data: { ok: true } })
    expect(authFlow).toHaveBeenCalledTimes(1)
    const apiCall = fetch.mock.calls.find(([url]) => String(url).endsWith('/users/me/messages'))
    expect(apiCall[1].headers.Authorization).toBe('Bearer tok-1')
  })

  it('rejects a token granted for a different account', async () => {
    profileEmail = 'prywatne@gmail.com'
    await load()
    const reply = await send({ action: 'connect', account: 'kelner@roma.pl' })
    expect(reply).toMatchObject({ connected: false, error: { code: 'wrong_account' } })
    expect(fetch.mock.calls.some(([url]) => String(url).includes('/revoke'))).toBe(true)
    expect(await send({ action: 'status', account: 'kelner@roma.pl' })).toMatchObject({ connected: false })
  })

  it('reports missing permissions when a scope was unticked', async () => {
    authFlow.mockImplementation((d, cb) => cb(redirectWith({ access_token: 't', expires_in: '3600', scope: 'https://www.googleapis.com/auth/calendar.events' })))
    await load()
    expect(await send({ action: 'connect', account: 'kelner@roma.pl' })).toMatchObject({ error: { code: 'missing_scopes' } })
  })

  it('does not open the consent screen for status checks of a new account', async () => {
    await load()
    expect(await send({ action: 'status', account: 'kelner@roma.pl' })).toMatchObject({ connected: false })
    expect(authFlow).not.toHaveBeenCalled()
  })

  it('renews silently after the token expired, and once more after a 401', async () => {
    await load()
    await send({ action: 'connect', account: 'kelner@roma.pl' })
    // Simulate a browser restart: session tokens are gone, the account stays connected.
    await chrome.storage.session.set({ 'gcrm:google-tokens': {} })
    authFlow.mockImplementation((d, cb) => cb(redirectWith({ access_token: 'tok-2', expires_in: '3600', scope: SCOPES })))

    expect(await send({ action: 'status', account: 'kelner@roma.pl' })).toMatchObject({ connected: true })
    expect(authFlow.mock.calls.at(-1)[0].interactive).toBe(false)
    expect(new URL(authFlow.mock.calls.at(-1)[0].url).searchParams.get('prompt')).toBe('none')

    let first = true
    fetch.mockImplementation(async (url) => {
      if (String(url).includes('/users/me/profile')) return new Response(JSON.stringify({ emailAddress: profileEmail }), { status: 200 })
      if (String(url).includes('/calendars/') && first) {
        first = false
        return new Response('{}', { status: 401 })
      }
      return new Response('{"id":"evt"}', { status: 200 })
    })
    const calls = authFlow.mock.calls.length
    const reply = await send({ account: 'kelner@roma.pl', method: 'POST', url: 'https://www.googleapis.com/calendar/v3/calendars/primary/events', body: {} })
    expect(reply).toEqual({ status: 200, data: { id: 'evt' } })
    expect(authFlow.mock.calls.length).toBe(calls + 1)
  })

  it('disconnect revokes the token and forgets the account', async () => {
    await load()
    await send({ action: 'connect', account: 'kelner@roma.pl' })
    await send({ action: 'disconnect', account: 'kelner@roma.pl' })
    expect(fetch.mock.calls.some(([url]) => String(url).includes('/revoke?token=tok-1'))).toBe(true)
    authFlow.mockClear()
    expect(await send({ action: 'status', account: 'kelner@roma.pl' })).toMatchObject({ connected: false })
    expect(authFlow).not.toHaveBeenCalled()
  })

  it('only proxies googleapis.com', async () => {
    await load()
    expect(await send({ account: 'kelner@roma.pl', url: 'https://evil.example/' })).toMatchObject({ error: { code: 'forbidden' } })
  })
})
