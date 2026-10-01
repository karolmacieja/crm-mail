<template>
  <div class="group flex items-center justify-between gap-3 transition-colors hover:bg-gray-50" :class="compact ? 'p-3' : 'p-4'">
    <div class="flex min-w-0 items-start">
      <div class="mr-4 rounded-lg p-2" :class="iconBox">
        <Icon :icon="icon" />
      </div>
      <div class="min-w-0">
        <h4 class="font-bold text-gray-900 group-hover:text-primary" :class="compact ? 'text-sm' : 'truncate'">{{ reminder.title }}</h4>
        <p class="mt-1 truncate text-xs text-gray-500">
          <template v-if="reminder.source_email_subject">{{ t('crm.reminders.fromEmail', { subject: reminder.source_email_subject }) }}</template>
          <template v-else-if="reminder.contact && !compact">
            <button type="button" class="hover:text-primary hover:underline" @click="$emit('open-contact', reminder.contact)">
              {{ t('crm.reminders.client', { name: reminder.contact.name || reminder.contact.email }) }}
            </button>
          </template>
          <template v-else-if="reminder.notes">{{ reminder.notes }}</template>
        </p>
        <!-- Narrow panels: the date badge goes under the title instead of squeezing it. -->
        <span v-if="compact" class="mt-1 inline-block rounded border px-1.5 py-0.5 text-[11px] font-bold" :class="badge">{{ when }}</span>
      </div>
    </div>
    <div class="flex shrink-0 items-center gap-2 text-right">
      <span v-if="!compact" class="inline-block rounded border px-2 py-1 text-xs font-bold" :class="badge">{{ when }}</span>
      <CalendarLink kind="reminder" :item="reminder" />
      <button type="button" class="rounded p-1 text-gray-300 hover:bg-green-50 hover:text-green-600" :title="t('crm.reminders.markDone')" :disabled="busy" @click="done">
        <Icon icon="check" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import CalendarLink from '@/crm/components/CalendarLink.vue'
import { useRemindersStore } from '@/crm/stores/reminders.js'

const props = defineProps({
  reminder: { type: Object, required: true },
  compact: { type: Boolean, default: false },
})
defineEmits(['open-contact'])

const reminders = useRemindersStore()
const busy = ref(false)
const overdue = computed(() => props.reminder.time_status === 'overdue')

const icon = computed(() => {
  if (overdue.value) return ['far', 'clock']
  if (props.reminder.type === 'reservation') return 'cake-candles'
  if (props.reminder.type === 'email') return ['far', 'envelope']
  return ['far', 'bell']
})
const iconBox = computed(() => (overdue.value ? 'bg-red-100 text-red-600' : props.reminder.type === 'reservation' ? 'bg-orange-100 text-orange-600' : 'bg-blue-100 text-blue-600'))
const badge = computed(() => (overdue.value ? 'border-red-200 bg-red-50 text-red-600' : 'border-blue-200 bg-blue-50 text-blue-600'))
const when = computed(() => {
  const label = formatCalendar(props.reminder.remind_at)
  return overdue.value ? t('crm.reminders.overdueAt', { when: label }) : label
})

async function done() {
  busy.value = true
  try {
    await reminders.markDone(props.reminder)
  } catch (e) {
    window.alert(e.message)
  } finally {
    busy.value = false
  }
}
</script>
