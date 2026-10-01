import { api, toApiError } from '@/crm/api.js'
import { googleApi } from './client.js'

const GMAIL = 'https://www.googleapis.com/gmail/v1/users/me/messages'
export const MAX_MESSAGES = 500
const CHUNK = 100
const CONCURRENCY = 5

const header = (message, name) => message.payload?.headers?.find((h) => h.name.toLowerCase() === name.toLowerCase())?.value ?? null

/** "Jan Kowalski <jan@xyz.pl>" -> "jan@xyz.pl" */
export const addressOf = (value) => (String(value ?? '').match(/<([^>]+)>/)?.[1] ?? String(value ?? '')).trim().toLowerCase()

/** Gmail snippets contain HTML entities (&#39; &amp; …). */
function decodeEntities(text) {
  if (!text) return null
  const area = document.createElement('textarea')
  area.innerHTML = text
  return area.value
}

/** Gmail search date (YYYY/MM/DD), one day early so nothing on the boundary is missed. */
function afterQuery(since) {
  if (!since) return ''
  const date = new Date(since)
  date.setDate(date.getDate() - 1)
  return ` after:${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}`
}

async function mapLimit(items, limit, fn) {
  const results = new Array(items.length)
  let next = 0
  const workers = Array.from({ length: Math.min(limit, items.length) }, async () => {
    while (next < items.length) {
      const index = next++
      results[index] = await fn(items[index])
    }
  })
  await Promise.all(workers)
  return results
}

/**
 * Import a contact's past Gmail correspondence (sent and received) into the
 * CRM timeline via the Gmail API. Incremental when the contact was synced before.
 *
 * @returns {Promise<{found: number, imported: number, updated: number, skipped: number, synced_at: string}>}
 */
export async function importEmailHistory(contact, { onProgress } = {}) {
  const email = contact.email.toLowerCase()
  const query = `(from:${email} OR to:${email} OR cc:${email})${afterQuery(contact.email_history_synced_at)}`

  const ids = []
  let pageToken = null
  do {
    const params = new URLSearchParams({ q: query, maxResults: '100', ...(pageToken ? { pageToken } : {}) })
    const page = await googleApi('GET', `${GMAIL}?${params}`)
    ids.push(...(page?.messages ?? []).map((m) => m.id))
    pageToken = page?.nextPageToken ?? null
  } while (pageToken && ids.length < MAX_MESSAGES)

  const selected = ids.slice(0, MAX_MESSAGES)
  let fetched = 0
  const messages = await mapLimit(selected, CONCURRENCY, async (id) => {
    const message = await googleApi('GET', `${GMAIL}/${id}?format=metadata&metadataHeaders=Subject&metadataHeaders=From&metadataHeaders=Date`)
    onProgress?.({ phase: 'fetch', done: ++fetched, total: selected.length })
    return message
  })

  const emails = messages.filter(Boolean).map((message) => ({
    message_id: message.id,
    thread_id: message.threadId,
    subject: header(message, 'Subject')?.slice(0, 255) ?? null,
    snippet: decodeEntities(message.snippet)?.slice(0, 2000) ?? null,
    from: header(message, 'From')?.slice(0, 255) ?? null,
    direction: addressOf(header(message, 'From')) === email ? 'in' : 'out',
    sent_at: message.internalDate ? new Date(Number(message.internalDate)).toISOString() : null,
  }))

  const totals = { found: emails.length, imported: 0, updated: 0, skipped: 0, synced_at: null }
  const chunks = emails.length ? Array.from({ length: Math.ceil(emails.length / CHUNK) }, (_, i) => emails.slice(i * CHUNK, (i + 1) * CHUNK)) : [[]]

  for (const [index, chunk] of chunks.entries()) {
    try {
      const { data } = await api.post(`/contacts/${contact.id}/emails/import`, { emails: chunk, complete: index === chunks.length - 1 })
      totals.imported += data.data.imported
      totals.updated += data.data.updated
      totals.skipped += data.data.skipped
      totals.synced_at = data.data.email_history_synced_at ?? totals.synced_at
    } catch (error) {
      throw toApiError(error)
    }
    onProgress?.({ phase: 'save', done: index + 1, total: chunks.length })
  }

  return totals
}
