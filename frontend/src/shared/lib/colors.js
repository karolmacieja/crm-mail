/**
 * Colours selectable in the settings panel (same list as the API's
 * DictionaryController::COLORS). Literal class names so Tailwind keeps them.
 */
export const PALETTE = {
  gray: { badge: 'bg-gray-100 text-gray-700 ring-gray-200', header: 'bg-gray-100/50 text-gray-700', count: 'bg-gray-200 text-gray-700', swatch: 'bg-gray-400' },
  red: { badge: 'bg-red-100 text-red-700 ring-red-200', header: 'bg-red-50/50 text-red-700', count: 'bg-red-100 text-red-700', swatch: 'bg-red-500' },
  orange: { badge: 'bg-orange-100 text-orange-700 ring-orange-200', header: 'bg-orange-50/50 text-orange-700', count: 'bg-orange-100 text-orange-700', swatch: 'bg-orange-500' },
  amber: { badge: 'bg-amber-100 text-amber-800 ring-amber-200', header: 'bg-amber-50/50 text-amber-800', count: 'bg-amber-100 text-amber-800', swatch: 'bg-amber-500' },
  yellow: { badge: 'bg-yellow-100 text-yellow-800 ring-yellow-200', header: 'bg-yellow-50/50 text-yellow-800', count: 'bg-yellow-100 text-yellow-800', swatch: 'bg-yellow-400' },
  green: { badge: 'bg-green-100 text-green-700 ring-green-200', header: 'bg-green-50/50 text-green-700', count: 'bg-green-100 text-green-700', swatch: 'bg-green-500' },
  emerald: { badge: 'bg-emerald-100 text-emerald-800 ring-emerald-200', header: 'bg-emerald-50/50 text-emerald-800', count: 'bg-emerald-100 text-emerald-800', swatch: 'bg-emerald-500' },
  sky: { badge: 'bg-sky-100 text-sky-800 ring-sky-200', header: 'bg-sky-50/50 text-sky-800', count: 'bg-sky-100 text-sky-800', swatch: 'bg-sky-500' },
  blue: { badge: 'bg-blue-100 text-blue-700 ring-blue-200', header: 'bg-blue-50/50 text-blue-700', count: 'bg-blue-100 text-blue-700', swatch: 'bg-blue-500' },
  indigo: { badge: 'bg-indigo-100 text-indigo-700 ring-indigo-200', header: 'bg-indigo-50/50 text-indigo-700', count: 'bg-indigo-100 text-indigo-700', swatch: 'bg-indigo-500' },
  purple: { badge: 'bg-purple-100 text-purple-700 ring-purple-200', header: 'bg-purple-50/50 text-purple-700', count: 'bg-purple-100 text-purple-700', swatch: 'bg-purple-500' },
  pink: { badge: 'bg-pink-100 text-pink-700 ring-pink-200', header: 'bg-pink-50/50 text-pink-700', count: 'bg-pink-100 text-pink-700', swatch: 'bg-pink-500' },
}

export const COLOR_NAMES = Object.keys(PALETTE)

export const tone = (color) => PALETTE[color] ?? PALETTE.gray

/** Icons offered for categories (all registered in shared/icons.js). */
export const DICTIONARY_ICONS = [
  'briefcase', 'star', 'user', 'users', 'building', 'utensils', 'cake-candles', 'phone-volume', 'file-invoice',
  'calendar-check', 'envelope', 'fire', 'list-check', 'key', 'address-book',
]
