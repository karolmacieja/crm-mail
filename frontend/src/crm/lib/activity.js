import { t } from '@/shared/lib/i18n.js'

/** "Email przychodzący" / "Notatka" / ... for the "Ostatnia aktywność" column. */
export function activityLabel(activity) {
  if (!activity) return ''
  if (activity.type === 'email') return activity.direction === 'out' ? t('crm.activity.emailOut') : t('crm.activity.emailIn')
  if (activity.type === 'note') return t('crm.activity.note')
  return t('crm.activity.system')
}
