/**
 * Who a Gmail message is really from.
 *
 * Mail that reaches the mailbox through a company alias (info@firma.pl) often
 * carries the alias in "From": a manual forward, a Google Group / collaborative
 * inbox, or a forwarding rule that rewrites the sender. Such internal addresses
 * must never become clients; the real sender is taken from the forwarded
 * message's header block or from the Reply-To / X-Original-* headers.
 */

/** Public mail providers: their domain is never "my company". */
export const FREE_DOMAINS = new Set([
  'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'yahoo.com', 'icloud.com', 'me.com',
  'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'gmx.net', 'wp.pl', 'o2.pl', 'onet.pl', 'onet.eu', 'op.pl',
  'interia.pl', 'interia.eu', 'poczta.fm', 'tlen.pl', 'gazeta.pl', 'vp.pl', 'go2.pl', 'int.pl', 'autograf.pl', 'buziaczek.pl',
])

export const normalizeEmail = (email) => String(email ?? '').trim().toLowerCase()
export const domainOf = (email) => normalizeEmail(email).split('@')[1] ?? ''

/** "Jan Kowalski <jan@xyz.pl>" / "jan@xyz.pl" -> { name, email } */
export function parseAddress(value) {
  const text = String(value ?? '').trim()
  const bracket = text.match(/^(.*?)<\s*([^<>\s]+@[^<>\s]+)\s*>/)
  if (bracket) return { name: bracket[1].replace(/["']/g, '').trim(), email: normalizeEmail(bracket[2]) }
  const bare = text.match(/[^\s<>()"',;:[\]]+@[^\s<>()"',;:[\]]+\.[a-z]{2,}/i)
  return bare ? { name: '', email: normalizeEmail(bare[0]) } : null
}

/**
 * Internal = my own address, an address/domain from Preferences → Poczta,
 * or (unless switched off) any address of my company domain.
 */
export function internalMatcher(me, mailPrefs = {}) {
  const myEmail = normalizeEmail(me)
  const myDomain = domainOf(myEmail)
  const ownDomain = mailPrefs.ignore_own_domain !== false && myDomain && !FREE_DOMAINS.has(myDomain) ? myDomain : null
  const listed = (mailPrefs.internal_addresses ?? []).map(normalizeEmail)
  const addresses = new Set(listed.filter((a) => !a.startsWith('@')))
  const domains = new Set(listed.filter((a) => a.startsWith('@')).map((a) => a.slice(1)))

  return (email) => {
    const address = normalizeEmail(email)
    if (!address) return true
    const domain = domainOf(address)
    return address === myEmail || addresses.has(address) || domains.has(domain) || (ownDomain !== null && domain === ownDomain)
  }
}

const FORWARD_MARKERS = /(forwarded message|wiadomość przekazana|przekazana wiadomość|wiadomość oryginalna|oryginalna wiadomość|original message|message transféré|weitergeleitete nachricht|mensaje reenviado)/i
const FROM_LINE = /^\s*\*?(from|od|de|von|van|da)\*?\s*:\s*(.+)$/im

/**
 * The sender named in a forwarded message's header block
 * ("---------- Forwarded message ---------\nFrom: Jan <jan@xyz.pl>"), or null.
 */
export function forwardedSender(bodyText) {
  const text = String(bodyText ?? '')
  const marker = text.search(FORWARD_MARKERS)
  if (marker === -1) return null
  const from = text.slice(marker).match(FROM_LINE)
  return from ? parseAddress(from[2]) : null
}

/** Original sender from Gmail API headers: Reply-To, X-Original-From, X-Original-Sender. */
export function headerSender(headers = []) {
  const value = (name) => headers.find((h) => h.name?.toLowerCase() === name)?.value
  for (const name of ['x-original-from', 'reply-to', 'x-original-sender']) {
    const parsed = parseAddress(value(name))
    if (parsed) return parsed
  }
  return null
}

/**
 * Counterparties of a thread, newest first. `messages` come from the content
 * script ([{ email, name, messageId, snippet, forwarded, recipients }]);
 * `lookupHeaders(messageId)` may ask the Gmail API for the original sender.
 *
 * @returns {Promise<Array<{ email, name, messageId, snippet, via?, direction? }>>}
 */
export async function resolveParticipants(messages, { me, isInternal, lookupHeaders = async () => null }) {
  const myEmail = normalizeEmail(me)
  const seen = new Set()
  const participants = []

  for (const message of messages) {
    let { email, name } = message
    if (normalizeEmail(email) === myEmail) continue // my own replies
    let via = null
    if (isInternal(email)) {
      const original = message.forwarded ?? (await lookupHeaders(message.messageId).catch(() => null))
      if (!original || isInternal(original.email)) continue
      via = normalizeEmail(email)
      ;({ email, name } = original)
    }
    email = normalizeEmail(email)
    if (!email || seen.has(email)) continue
    seen.add(email)
    participants.push({ email, name: name && normalizeEmail(name) !== email ? name : '', messageId: message.messageId, snippet: message.snippet ?? null, ...(via ? { via } : {}) })
  }

  // Outgoing-only thread: the people I wrote to.
  if (!participants.length && messages.length) {
    const latest = messages[0]
    for (const recipient of latest.recipients ?? []) {
      const email = normalizeEmail(recipient.email)
      if (!email || isInternal(email) || seen.has(email)) continue
      seen.add(email)
      participants.push({ email, name: recipient.name ?? '', messageId: latest.messageId, direction: 'out' })
    }
  }

  return participants
}

/** First external contact of an inbox row, or null when only internal addresses are shown. */
export function pickExternal(contacts, isInternal) {
  return contacts.find((contact) => !isInternal(contact.email)) ?? null
}
