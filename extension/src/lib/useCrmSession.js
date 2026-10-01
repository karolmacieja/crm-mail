import { onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import { useCrmStore } from '@/stores/crm.js'

/**
 * Shared bootstrap for every root app (sidebar, dashboard, popup):
 * loads the persisted session and wipes cached CRM data when the
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
    auth.init()
  })

  return { auth, crm }
}
