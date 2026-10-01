import { CONTACT_STATUSES } from './config.js'
import { locale } from './i18n.js'

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
