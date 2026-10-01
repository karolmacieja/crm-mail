import { watch } from 'vue'
import { useAuthStore } from '@/crm/stores/auth.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useContactsStore } from '@/crm/stores/contacts.js'
import { useDashboardStore } from '@/crm/stores/dashboard.js'
import { useInboxStore } from '@/crm/stores/inbox.js'
import { useRemindersStore } from '@/crm/stores/reminders.js'
import { useTasksStore } from '@/crm/stores/tasks.js'
import { useTeamStore } from '@/crm/stores/team.js'

/**
 * Wipe cached CRM data when the signed-in staff member changes or logs out,
 * so one waiter never sees another restaurant's data after switching accounts.
 */
export function watchCrmSession(pinia) {
  const auth = useAuthStore(pinia)

  return watch(
    () => auth.user?.id ?? null,
    (id, previous) => {
      if (id === previous) return
      for (const useStore of [useClientStore, useDashboardStore, useInboxStore, useTeamStore, useContactsStore, useTasksStore, useRemindersStore]) {
        useStore(pinia).clear()
      }
    },
  )
}
