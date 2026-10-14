import { googleApi } from './client.js'

/**
 * Mailbox numbers of the Gmail tab's account, read with the Gmail API
 * (gmail.readonly) – nothing is stored on the GastroFlowx server.
 */
const GMAIL = 'https://www.googleapis.com/gmail/v1/users/me'
const PAGE = 500
/** Per query; beyond that the number is shown as "2000+". */
export const MAX_COUNT = 2000

/** Unread conversations in the inbox – the number Gmail shows next to "Odebrane". */
export async function unreadInboxCount() {
  const label = await googleApi('GET', `${GMAIL}/labels/INBOX`)
  return Number(label?.threadsUnread ?? 0)
}

async function listMessages(query) {
  const messages = []
  let pageToken = null
  do {
    const params = new URLSearchParams({ q: query, maxResults: String(PAGE), ...(pageToken ? { pageToken } : {}) })
    const page = await googleApi('GET', `${GMAIL}/messages?${params}`)
    messages.push(...(page?.messages ?? []))
    pageToken = page?.nextPageToken ?? null
  } while (pageToken && messages.length < MAX_COUNT)
  return { messages: messages.slice(0, MAX_COUNT), capped: Boolean(pageToken) }
}

/** Gmail search accepts Unix timestamps, so local midnight is exact. */
const since = (date) => `after:${Math.floor(date.getTime() / 1000)}`

/**
 * Received, unread, opened without reply, replied and sent messages since `from`.
 * "Replied" = a received message whose conversation has a message I sent in
 * the period; spam, trash and chats are left out.
 */
export async function periodStats(from) {
  const after = since(from)
  const base = '-in:spam -in:trash -in:chats'
  const [received, unread, sent] = await Promise.all([
    listMessages(`${after} ${base} -from:me -in:sent -in:drafts`),
    listMessages(`${after} ${base} -from:me -in:sent -in:drafts is:unread`),
    listMessages(`${after} in:sent`),
  ])

  const unreadIds = new Set(unread.messages.map((m) => m.id))
  const repliedThreads = new Set(sent.messages.map((m) => m.threadId))
  const replied = received.messages.filter((m) => repliedThreads.has(m.threadId))
  const repliedRead = replied.filter((m) => !unreadIds.has(m.id)).length

  return {
    received: received.messages.length,
    unread: unread.messages.length,
    replied: replied.length,
    opened_no_reply: Math.max(0, received.messages.length - unread.messages.length - repliedRead),
    sent: sent.messages.length,
    capped: received.capped || unread.capped || sent.capped,
  }
}

/** Today and this month (local time of the computer). */
export async function mailStats(now = new Date()) {
  const startOfDay = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1)
  const [today, month] = await Promise.all([periodStats(startOfDay), periodStats(startOfMonth)])
  return { today, month, at: now.toISOString() }
}

/** Real send time of a Gmail message (internalDate), or null. */
export async function messageDate(messageId) {
  if (!messageId || String(messageId).startsWith('thread:')) return null
  const message = await googleApi('GET', `${GMAIL}/messages/${encodeURIComponent(messageId)}?format=minimal`)
  return message?.internalDate ? new Date(Number(message.internalDate)).toISOString() : null
}
