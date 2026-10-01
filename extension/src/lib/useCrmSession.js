import { onMounted, watch } from 'vue'
import { initLocale } from '@/lib/i18n.js'
import { useAuthStore } from '@/stores/auth.js'
import { useCrmStore } from '@/stores/crm.js'

/**
 * Shared bootstrap for every root app (sidebar, dashboard, popup):
 * loads the persisted session and language, and wipes cached CRM data when the
 * signed-in account changes or logs out.
 */
export function useCrmSession() {
  const auth = useAuthStore()
  const crm = useCrmStore()

  watch(
    () => auth.user?.id ?? null,
    (id, previous) => {
      if (previous !== undefined && id !== previous) crm.$reset()
    },
  )

  onMounted(() => {
    initLocale()
    auth.init()
  })

  return { auth, crm }
}
