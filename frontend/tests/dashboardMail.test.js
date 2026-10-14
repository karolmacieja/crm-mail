import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/crm/google/client.js', () => ({ googleApi: vi.fn() }))

const { googleApi } = await import('@/crm/google/client.js')
const { periodStats, unreadInboxCount, messageDate } = await import('@/crm/google/mail.js')
const { defaultDueInputValue } = await import('@/shared/lib/format.js')
const { belongsToMe, eventBody } = await import('@/crm/google/calendarSync.js')

/** Fake Gmail search: answers messages.list by the query's distinctive part. */
function gmail(results) {
  googleApi.mockImplementation(async (method, url) => {
    if (url.includes('/labels/INBOX')) return { threadsUnread: 7 }
    if (url.includes('format=minimal')) return { internalDate: String(Date.UTC(2026, 9, 10, 8)) }
    const q = new URL(url).searchParams.get('q')
    const key = q.includes('is:unread') ? 'unread' : q.includes('in:sent') && !q.includes('-in:sent') ? 'sent' : 'received'
    return { messages: results[key] }
  })
}

beforeEach(() => vi.resetAllMocks())

describe('mailbox numbers from the Gmail API', () => {
  it('splits received mail into unread, replied and opened without reply', async () => {
    gmail({
      received: [
        { id: 'a', threadId: 't1' }, // read, replied
        { id: 'b', threadId: 't2' }, // unread
        { id: 'c', threadId: 't3' }, // read, no reply
        { id: 'd', threadId: 't4' }, // read, no reply
      ],
      unread: [{ id: 'b', threadId: 't2' }],
      sent: [{ id: 's1', threadId: 't1' }, { id: 's2', threadId: 't9' }],
    })

    const stats = await periodStats(new Date('2026-10-14T00:00:00'))
    expect(stats).toEqual({ received: 4, unread: 1, replied: 1, opened_no_reply: 2, sent: 2, capped: false })
    const query = new URL(googleApi.mock.calls[0][1]).searchParams.get('q')
    expect(query).toMatch(/after:\d{10}/)
    expect(query).toContain('-in:spam')
  })

  it('reads the inbox unread counter and a message date', async () => {
    gmail({})
    expect(await unreadInboxCount()).toBe(7)
    expect(await messageDate('18c2a')).toBe('2026-10-10T08:00:00.000Z')
    expect(await messageDate('thread:18c2a')).toBeNull()
  })
})

describe('personal defaults', () => {
  it('tasks and reminders use their own day and hour', () => {
    const prefs = { default_task_due_days: 0, default_task_time: '08:30', default_reminder_days: 2, default_reminder_time: '10:15' }
    const task = new Date(defaultDueInputValue(prefs, 'task'))
    const reminder = new Date(defaultDueInputValue(prefs, 'reminder'))
    expect([task.getHours(), task.getMinutes(), task.getDate()]).toEqual([8, 30, new Date().getDate()])
    expect([reminder.getHours(), reminder.getMinutes()]).toEqual([10, 15])
    expect(Math.round((reminder - task) / 86_400_000)).toBe(2)
  })
})

describe('reservations in Google Calendar', () => {
  const booking = { id: 4, status: 'confirmed', user_id: 1, starts_at: '2026-10-20T17:00:00Z', guests_count: 6, contact: { name: 'Jan', email: 'jan@xyz.pl' }, occasion: 'Urodziny' }

  it('only my open bookings, lasting as long as set in preferences', () => {
    expect(belongsToMe('reservation', booking, 1)).toBe(true)
    expect(belongsToMe('reservation', booking, 2)).toBe(false)
    expect(belongsToMe('reservation', { ...booking, status: 'cancelled' }, 1)).toBe(false)

    const body = eventBody('reservation', booking, { reservations: { duration_minutes: 90 }, calendar: { alarm_minutes: 30 } })
    expect(body.summary).toBe('🍽 Jan · 6 os.')
    expect(body.end.dateTime).toBe('2026-10-20T18:30:00.000Z')
    expect(body.reminders.overrides[0].minutes).toBe(30)
  })
})
