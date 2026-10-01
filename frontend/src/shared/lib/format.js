import { CONTACT_STATUSES } from '@/shared/lib/config.js'
import { locale, t } from '@/shared/lib/i18n.js'

// Formatters are cached per language; reading locale.value keeps templates reactive.
const formatters = new Map()

function formattersFor(code) {
  if (!formatters.has(code)) {
    formatters.set(code, {
      dateTime: new Intl.DateTimeFormat(code, { dateStyle: 'medium', timeStyle: 'short' }),
      relative: new Intl.RelativeTimeFormat(code, { numeric: 'auto' }),
    })
  }
  return formatters.get(code)
}

export function formatDateTime(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  return Number.isNaN(date.getTime()) ? '' : formattersFor(locale.value).dateTime.format(date)
}

/** "in 2 days", "3 hours ago", ... */
export function formatRelative(iso) {
  if (!iso) return ''
  const diffMs = Date.parse(iso) - Date.now()
  if (Number.isNaN(diffMs)) return ''
  const relativeFormatter = formattersFor(locale.value).relative

  const units = [
    ['day', 86_400_000],
    ['hour', 3_600_000],
    ['minute', 60_000],
  ]
  for (const [unit, ms] of units) {
    if (Math.abs(diffMs) >= ms || unit === 'minute') {
      return relativeFormatter.format(Math.round(diffMs / ms), unit)
    }
  }
  return ''
}

/** Value for <input type="datetime-local"> from an ISO string (local time). */
export function toLocalInputValue(iso) {
  if (!iso) return ''
  const date = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** ISO-8601 (UTC) from an <input type="datetime-local"> value, or null. */
export function fromLocalInputValue(value) {
  if (!value) return null
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

/** Default reminder: tomorrow at 09:00 local time. */
export function defaultDueInputValue() {
  const date = new Date()
  date.setDate(date.getDate() + 1)
  date.setHours(9, 0, 0, 0)
  return toLocalInputValue(date.toISOString())
}

export function statusMeta(value) {
  return CONTACT_STATUSES.find((s) => s.value === value) ?? CONTACT_STATUSES[0]
}

export function initials(name, email) {
  const source = (name || email || '?').trim()
  const parts = source.split(/[\s@._-]+/).filter(Boolean)
  return ((parts[0]?.[0] ?? '?') + (parts[1]?.[0] ?? '')).toUpperCase()
}

const dayKey = (date) => `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`

/** "Dzisiaj, 10:42" / "Wczoraj, 14:30" / "Jutro, 09:00" / "15.10.2026, 19:00" (as in the mockup). */
export function formatCalendar(iso, { withTime = true } = {}) {
  if (!iso) return ''
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return ''

  const code = locale.value
  const time = new Intl.DateTimeFormat(code, { hour: '2-digit', minute: '2-digit' }).format(date)
  const today = new Date()
  const offsetDay = (days) => {
    const d = new Date(today)
    d.setDate(d.getDate() + days)
    return dayKey(d)
  }

  const label = {
    [offsetDay(0)]: t('time.today'),
    [offsetDay(-1)]: t('time.yesterday'),
    [offsetDay(1)]: t('time.tomorrow'),
  }[dayKey(date)]

  const day = label ?? new Intl.DateTimeFormat(code, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date)
  return withTime ? `${day}, ${time}` : day
}

/** Reservation date ("2026-12-18") + time ("19:00") in the restaurant's local time. */
export function formatReservation(date, time) {
  if (!date) return ''
  const [y, m, d] = date.split('-').map(Number)
  const local = new Date(y, m - 1, d)
  const day = new Intl.DateTimeFormat(locale.value, { day: '2-digit', month: '2-digit', year: 'numeric' }).format(local)
  return time ? `${day}, ${time}` : day
}

/** Today as "YYYY-MM-DD" in local time (for <input type="date"> defaults). */
export function todayInputValue(offsetDays = 0) {
  const date = new Date()
  date.setDate(date.getDate() + offsetDays)
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
