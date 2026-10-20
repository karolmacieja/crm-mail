import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/crm/api.js', async () => {
  const errors = await import('@/shared/api/errors.js')
  return { api: { get: vi.fn(), post: vi.fn(() => Promise.resolve({ data: { data: [] } })) }, ApiError: errors.ApiError, toApiError: errors.toApiError, onApiError: errors.onApiError }
})

const { forwardedSender, headerSender, internalMatcher, resolveParticipants } = await import('@/crm/lib/senders.js')
const { useInboxStore } = await import('@/crm/stores/inbox.js')
const { useSettingsStore } = await import('@/crm/stores/settings.js')

const ME = 'karol@firma.pl'

describe('internal addresses', () => {
  it('my address, my company domain and listed aliases are internal', () => {
    const isInternal = internalMatcher(ME, { internal_addresses: ['biuro@restauracja.com', '@firma.com.pl'] })
    expect(isInternal('karol@firma.pl')).toBe(true)
    expect(isInternal('INFO@firma.pl')).toBe(true)
    expect(isInternal('biuro@restauracja.com')).toBe(true)
    expect(isInternal('anna@firma.com.pl')).toBe(true)
    expect(isInternal('jan@klient.pl')).toBe(false)
  })

  it('a public provider domain is never treated as my company', () => {
    const isInternal = internalMatcher('karol@gmail.com', {})
    expect(isInternal('jan@gmail.com')).toBe(false)
    expect(internalMatcher(ME, { ignore_own_domain: false })('info@firma.pl')).toBe(false)
  })
})

describe('finding the real sender', () => {
  it('reads the forwarded header block (Gmail PL and EN)', () => {
    expect(forwardedSender('Zobacz poniżej\n---------- Wiadomość przekazana ---------\nOd: Jan Kowalski <jan@klient.pl>\nDate: wt., 14 paź 2026\nSubject: Wesele')).toEqual({ name: 'Jan Kowalski', email: 'jan@klient.pl' })
    expect(forwardedSender('---------- Forwarded message ---------\nFrom: <anna@xyz.com>\n')).toEqual({ name: '', email: 'anna@xyz.com' })
    expect(forwardedSender('Dzień dobry, od: wtorku jestem dostępny')).toBeNull()
  })

  it('reads Reply-To / X-Original-From set by groups and forwarding services', () => {
    expect(headerSender([{ name: 'Reply-To', value: '"Jan" <jan@klient.pl>' }])).toEqual({ name: 'Jan', email: 'jan@klient.pl' })
    expect(headerSender([{ name: 'X-Original-From', value: 'Ewa <ewa@abc.pl>' }, { name: 'Reply-To', value: 'info@firma.pl' }]).email).toBe('ewa@abc.pl')
    expect(headerSender([])).toBeNull()
  })

  it('replaces info@ with the original sender and never proposes internal addresses', async () => {
    const isInternal = internalMatcher(ME, { internal_addresses: ['info@firma.pl'] })
    const lookupHeaders = vi.fn(async (id) => (id === 'm2' ? { email: 'ewa@abc.pl', name: 'Ewa' } : null))
    const participants = await resolveParticipants(
      [
        { email: 'karol@firma.pl', name: 'Karol', messageId: 'm4' }, // my reply
        { email: 'info@firma.pl', name: 'Info', messageId: 'm3', forwarded: { email: 'jan@klient.pl', name: 'Jan Kowalski' } },
        { email: 'info@firma.pl', name: 'Info', messageId: 'm2' }, // group rewrite: Reply-To
        { email: 'info@firma.pl', name: 'Info', messageId: 'm1' }, // nothing to go on: skipped
      ],
      { me: ME, isInternal, lookupHeaders },
    )
    expect(participants).toEqual([
      { email: 'jan@klient.pl', name: 'Jan Kowalski', messageId: 'm3', snippet: null, via: 'info@firma.pl' },
      { email: 'ewa@abc.pl', name: 'Ewa', messageId: 'm2', snippet: null, via: 'info@firma.pl' },
    ])
    expect(lookupHeaders).not.toHaveBeenCalledWith('m4')
  })

  it('outgoing-only threads fall back to external recipients', async () => {
    const isInternal = internalMatcher(ME, {})
    const participants = await resolveParticipants(
      [{ email: ME, messageId: 'm1', recipients: [{ email: 'info@firma.pl' }, { email: 'jan@klient.pl', name: 'Jan' }] }],
      { me: ME, isInternal },
    )
    expect(participants).toEqual([{ email: 'jan@klient.pl', name: 'Jan', messageId: 'm1', direction: 'out' }])
  })
})

describe('inbox list on the dashboard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the external person, and marks rows with only internal senders', () => {
    const inbox = useInboxStore()
    useSettingsStore().preferences = { mail: { internal_addresses: [] } }
    inbox.addThreads([
      { threadId: 't1', myEmail: ME, contacts: [{ email: 'info@firma.pl', name: 'Info' }, { email: 'jan@klient.pl', name: 'Jan' }], subject: 'Wesele' },
      { threadId: 't2', myEmail: ME, contacts: [{ email: 'info@firma.pl', name: 'Info' }], subject: 'Fwd: Rezerwacja' },
    ])
    const [first, second] = inbox.recent
    expect(first).toMatchObject({ email: 'jan@klient.pl', name: 'Jan', viaInternal: false })
    expect(second).toMatchObject({ email: null, name: 'Info', viaInternal: true })
  })
})
