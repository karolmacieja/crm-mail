import { useGoogleStore } from '@/crm/stores/google.js'

/** Has the item (task / reminder) been created from an email? */
export const hasSourceEmail = (item) => Boolean(item?.source_thread_id || item?.source_email_id)

/**
 * The Gmail thread of the email a task / reminder was created from:
 * remembered thread → "thread:<id>" placeholder (dashboard) → Gmail API
 * lookup → the message id itself (Gmail opens a conversation by any of its
 * message ids, only "reply" needs the real thread id).
 */
export async function sourceThreadId(item) {
  if (item?.source_thread_id) return item.source_thread_id
  const messageId = item?.source_email_id
  if (!messageId) return null
  if (messageId.startsWith('thread:')) return messageId.slice('thread:'.length)
  return (await useGoogleStore().threadOf(messageId)) ?? messageId
}
