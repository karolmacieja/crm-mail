import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/crm/api.js'

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
  /** threadId -> { threadId, subject, email, name, date, unread, receivedOrder } */
  const threads = ref(new Map())
  /** email -> { id, name, category } for senders already in the CRM */
  const knownSenders = ref(new Map())
  let order = 0
  let lookupTimer = null
  const pendingLookups = new Set()

  const recent = computed(() =>
    [...threads.value.values()].sort((a, b) => a.receivedOrder - b.receivedOrder).slice(0, MAX_THREADS),
  )

  /** @param {Array<{threadId, subject, email, name, date, unread}>} rows in Gmail's display order */
  function addThreads(rows) {
    const next = new Map(threads.value)
    for (const row of rows) {
      if (!row?.threadId || !row.email) continue
      const email = String(row.email).toLowerCase()
      next.set(row.threadId, { ...next.get(row.threadId), ...row, email, receivedOrder: next.get(row.threadId)?.receivedOrder ?? order++ })
      if (!knownSenders.value.has(email)) pendingLookups.add(email)
    }
    // Keep memory bounded: drop the oldest beyond 3x what we show.
    threads.value = new Map([...next.entries()].sort((a, b) => a[1].receivedOrder - b[1].receivedOrder).slice(0, MAX_THREADS * 3))
    scheduleLookup()
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
    pendingLookups.clear()
    for (const thread of threads.value.values()) pendingLookups.add(thread.email)
    if (pendingLookups.size) scheduleLookup()
  }

  return { recent, addThreads, senderContact, rememberSender, clear }
})
