/** Static Tailwind classes for license states (kept literal so Tailwind sees them). */
export const LICENSE_STATE_CLASSES = {
  active: 'border-green-200 bg-green-50 text-green-700',
  scheduled: 'border-blue-200 bg-blue-50 text-blue-700',
  expired: 'border-red-200 bg-red-50 text-red-600',
  suspended: 'border-amber-200 bg-amber-50 text-amber-700',
  cancelled: 'border-gray-200 bg-gray-100 text-gray-600',
  missing: 'border-gray-200 bg-gray-50 text-gray-500',
}

/** The license currently in force among a group's licenses (newest active first). */
export function currentLicense(licenses = []) {
  return licenses.find((l) => l.state === 'active') ?? licenses[0] ?? null
}
