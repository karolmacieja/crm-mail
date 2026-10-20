import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/crm/api.js'
import { internalMatcher, pickExternal } from '@/crm/lib/senders.js'
import { useGoogleStore } from './google.js'
import { useSettingsStore } from './settings.js'

const MAX_THREADS = 30

/**
 * "Ostatnie maile (Skrzynka odbiorcza)" on the dashboard.
 *
 * Gmail has no inbox-listing API for extensions without OAuth, so the content
 * script reports the thread rows Gmail renders (InboxSDK ThreadRowView) via
 * addThreads(). Senders are matched against the CRM in one batch request to
 * show B2B / VIP badges.
 */
export const useInboxStore = defineStore('inbox', () => {
  /** threadId -> { threadId, subject, contacts: [{ email, name }], myEmail, date, unread, receivedOrder } */
  const threads = ref(new Map())
  const myEmail = ref(null)
  /** Internal addresses (me, my company domain, Preferences → Poczta) are never "the sender". */
  const isInternal = computed(() => internalMatcher(myEmail.value, useSettingsStore().preferences?.mail ?? {}))
  /** threadId -> { email, name } | null: real senders of rows that only show internal addresses. */
  const resolved = ref(new Map())
  const resolving = new Set()
  /** email -> { id, name, category } for senders already in the CRM */
  const knownSenders = ref(new Map())
  let order = 0
  let lookupTimer = null
  const pendingLookups = new Set()

  /**
   * Rows with their external sender. A row that only shows internal addresses
   * (e.g. mail forwarded by info@) has email = null: opening it in Gmail lets
   * the thread panel find the real sender.
   */
  const recent = computed(() =>
    [...threads.value.values()]
      .sort((a, b) => a.receivedOrder - b.receivedOrder)
      .slice(0, MAX_THREADS)
      .map((thread) => {
        const direct = pickExternal(thread.contacts ?? [], isInternal.value)
        const sender = direct ?? resolved.value.get(thread.threadId) ?? null
        const fallback = thread.contacts?.[0]
        return {
          ...thread,
          email: sender?.email ?? null,
          name: sender?.name || (sender ? '' : fallback?.name || fallback?.email || ''),
          // Came through an internal address (e.g. a Google Group "X via info@…").
          via: direct ? null : fallback?.email ?? null,
          viaInternal: !sender,
        }
      }),
  )

  /**
   * Rows whose only sender is internal: ask the Gmail API (when connected) who
   * really wrote, from Reply-To / X-Original-From of the thread's messages.
   */
  async function resolveForwarded() {
    const google = useGoogleStore()
    const pending = recent.value.filter((row) => row.via && !resolved.value.has(row.threadId) && !resolving.has(row.threadId))
    for (const row of pending) {
      resolving.add(row.threadId)
      const sender = await google.threadOriginalSender(row.threadId, isInternal.value).catch(() => null)
      resolving.delete(row.threadId)
      if (sender === undefined) return // Google not connected (yet)
      const next = new Map(resolved.value)
      next.set(row.threadId, sender)
      resolved.value = next
      if (sender && !knownSenders.value.has(sender.email)) {
        pendingLookups.add(sender.email)
        scheduleLookup()
      }
    }
  }

  /** @param {Array<{threadId, subject, contacts, myEmail, date, unread}>} rows in Gmail's display order */
  function addThreads(rows) {
    const next = new Map(threads.value)
    for (const row of rows) {
      if (!row?.threadId) continue
      // Older callers pass a single sender.
      const contacts = row.contacts ?? (row.email ? [{ email: String(row.email).toLowerCase(), name: row.name ?? '' }] : [])
      if (row.myEmail) myEmail.value = row.myEmail
      next.set(row.threadId, { ...next.get(row.threadId), ...row, contacts, receivedOrder: next.get(row.threadId)?.receivedOrder ?? order++ })
      for (const { email } of contacts) if (email && !knownSenders.value.has(email)) pendingLookups.add(email)
    }
    // Keep memory bounded: drop the oldest beyond 3x what we show.
    threads.value = new Map([...next.entries()].sort((a, b) => a[1].receivedOrder - b[1].receivedOrder).slice(0, MAX_THREADS * 3))
    scheduleLookup()
    resolveForwarded()
  }

  function scheduleLookup() {
    clearTimeout(lookupTimer)
    lookupTimer = setTimeout(lookupSenders, 300)
  }

  async function lookupSenders() {
    const emails = [...pendingLookups].slice(0, 100)
    if (!emails.length) return
    emails.forEach((email) => pendingLookups.delete(email))

    try {
      const { data } = await api.post('/contacts/lookup-many', { emails })
      const next = new Map(knownSenders.value)
      for (const contact of data.data) next.set(contact.email, contact)
      // Remember unknown senders too, so they aren't looked up again.
      for (const email of emails) if (!next.has(email)) next.set(email, null)
      knownSenders.value = next
    } catch {
      // Badges are cosmetic; try again on the next batch.
      emails.forEach((email) => pendingLookups.add(email))
    }
    if (pendingLookups.size) scheduleLookup()
  }

  function senderContact(email) {
    return knownSenders.value.get(String(email).toLowerCase()) ?? null
  }

  /** A sender was added to / removed from the CRM elsewhere. */
  function rememberSender(contact, { removed = false, email } = {}) {
    const next = new Map(knownSenders.value)
    next.set(email ?? contact.email, removed ? null : contact)
    knownSenders.value = next
  }

  /** Account switch: the Gmail rows stay, but CRM matches belong to the old account. */
  function clear() {
    knownSenders.value = new Map()
    resolved.value = new Map()
    pendingLookups.clear()
    for (const thread of threads.value.values()) for (const { email } of thread.contacts ?? []) if (email) pendingLookups.add(email)
    if (pendingLookups.size) scheduleLookup()
  }

  return { recent, addThreads, senderContact, rememberSender, resolveForwarded, clear }
})
