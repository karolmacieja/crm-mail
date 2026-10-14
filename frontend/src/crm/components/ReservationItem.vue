<template>
  <!-- One booking: when, who, how many; quick status actions for its author / the client's owner. -->
  <div class="flex items-start gap-3 rounded-lg border p-3" :class="reservation.time_status === 'overdue' ? 'border-red-100 bg-red-50/50' : 'border-orange-100 bg-white'">
    <div class="w-16 shrink-0 rounded-lg bg-orange-50 py-1 text-center">
      <p class="text-[10px] font-bold uppercase text-orange-600">{{ day }}</p>
      <p class="text-sm font-bold text-gray-900">{{ reservation.reservation_time }}</p>
    </div>
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-bold text-gray-900">
        <button v-if="reservation.contact" type="button" class="hover:text-primary hover:underline" @click="$emit('open-contact', reservation.contact)">
          {{ reservation.contact.name || reservation.contact.email }}
        </button>
        <template v-else>—</template>
      </p>
      <p class="truncate text-xs text-gray-500">
        {{ t('crm.reservations.guestsCount', { count: reservation.guests_count }) }}
        <template v-if="reservation.table_label"> · {{ reservation.table_label }}</template>
        <template v-if="reservation.occasion"> · {{ reservation.occasion }}</template>
      </p>
      <span class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="statusClass">{{ t(`crm.reservations.statuses.${reservation.status}`) }}</span>
    </div>
    <div v-if="reservation.can_edit !== false" class="flex shrink-0 flex-col items-end gap-1 text-xs">
      <button v-if="reservation.status === 'pending' && !isPast" type="button" class="font-medium text-green-700 hover:underline" :disabled="busy" @click="set('confirmed')">{{ t('crm.reservations.confirm') }}</button>
      <template v-if="isPast && open">
        <button type="button" class="font-medium text-green-700 hover:underline" :disabled="busy" @click="set('completed')">{{ t('crm.reservations.markCompleted') }}</button>
        <button type="button" class="text-gray-500 hover:text-red-600" :disabled="busy" @click="set('no_show')">{{ t('crm.reservations.markNoShow') }}</button>
      </template>
      <button v-if="open && !isPast" type="button" class="text-gray-400 hover:text-red-600" :disabled="busy" @click="set('cancelled')">{{ t('crm.reservations.cancel') }}</button>
      <button type="button" class="text-gray-400 hover:text-primary" :title="t('common.edit')" @click="$emit('edit', reservation)"><Icon icon="pen" /></button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { useReservationsStore } from '@/crm/stores/reservations.js'

const props = defineProps({ reservation: { type: Object, required: true } })
defineEmits(['open-contact', 'edit'])

const reservations = useReservationsStore()
const busy = ref(false)
const open = computed(() => ['pending', 'confirmed'].includes(props.reservation.status))
const isPast = computed(() => new Date(props.reservation.starts_at) < new Date())
const day = computed(() => formatCalendar(props.reservation.starts_at, { withTime: false }))
const statusClass = computed(() => ({
  pending: 'bg-orange-100 text-orange-700',
  confirmed: 'bg-green-100 text-green-700',
  completed: 'bg-gray-100 text-gray-600',
  no_show: 'bg-red-100 text-red-700',
  cancelled: 'bg-gray-100 text-gray-400 line-through',
})[props.reservation.status])

async function set(status) {
  if (status === 'cancelled' && !window.confirm(t('crm.reservations.confirmCancel'))) return
  busy.value = true
  try {
    await reservations.setStatus(props.reservation, status)
  } catch (e) {
    window.alert(e.message)
  } finally {
    busy.value = false
  }
}
</script>
