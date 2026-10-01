import { api } from '@/crm/api.js'
import { googleApi } from './client.js'

const EVENTS = 'https://www.googleapis.com/calendar/v3/calendars/primary/events'

/** Belongs in my calendar: open, scheduled, and assigned to me (or mine and unassigned). */
export function belongsToMe(kind, item, meId) {
  if (kind === 'task') {
    if (item.is_completed || !item.due_date) return false
    return item.assignee ? item.assignee.id === meId : item.user_id === meId
  }
  return !item.is_done && item.user_id === meId
}

export function eventBody(kind, item, preferences) {
  const start = new Date(kind === 'task' ? item.due_date : item.remind_at)
  const end = new Date(start.getTime() + (kind === 'task' ? 30 : 15) * 60_000)
  const contact = item.contact ? `${item.contact.name ?? ''} <${item.contact.email}>`.trim() : null
  const alarm = Number(preferences?.calendar?.alarm_minutes ?? 15)

  return {
    summary: `${kind === 'task' ? '✔' : '🔔'} ${item.title}`,
    description: [contact, item.source_email_subject, item.description ?? item.notes].filter(Boolean).join('\n') || undefined,
    start: { dateTime: start.toISOString() },
    end: { dateTime: end.toISOString() },
    reminders: alarm > 0 ? { useDefault: false, overrides: [{ method: 'popup', minutes: alarm }] } : { useDefault: true },
    extendedProperties: { private: { gastroflowx: `${kind}-${item.id}` } },
  }
}

/**
 * Create, update or delete the Google Calendar event of a task/reminder in
 * the signed-in person's own calendar. Returns the event id (or null).
 */
export async function syncCalendarItem(kind, item, { meId, preferences, removed = false }) {
  const existing = item.calendar_event_id ?? null

  if (removed || !belongsToMe(kind, item, meId)) {
    if (existing) {
      await googleApi('DELETE', `${EVENTS}/${encodeURIComponent(existing)}`).catch((e) => {
        if (![404, 410].includes(e.status)) throw e
      })
      if (!removed) await api.delete(`/calendar-events/${kind}/${item.id}`)
    }
    return null
  }

  const body = eventBody(kind, item, preferences)
  let event = null
  if (existing) {
    event = await googleApi('PATCH', `${EVENTS}/${encodeURIComponent(existing)}`, body).catch((e) => {
      if ([404, 410].includes(e.status)) return null // deleted in Google Calendar → create again
      throw e
    })
  }
  event ??= await googleApi('POST', EVENTS, body)

  if (event.id !== existing) await api.put(`/calendar-events/${kind}/${item.id}`, { external_id: event.id })
  return event.id
}

/** "Add to Google Calendar" link for a single item (works without any integration). */
export function googleCalendarTemplateUrl(kind, item) {
  const body = eventBody(kind, item, null)
  const fmt = (iso) => iso.replace(/[-:]/g, '').replace(/\.\d{3}/, '')
  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: body.summary,
    dates: `${fmt(body.start.dateTime)}/${fmt(body.end.dateTime)}`,
    ...(body.description ? { details: body.description } : {}),
  })
  return `https://calendar.google.com/calendar/render?${params}`
}
