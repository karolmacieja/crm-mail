<template>
  <section>
    <div class="mb-4 flex items-center justify-between">
      <h3 class="flex items-center text-sm font-bold uppercase tracking-wide text-gray-800">
        <Icon icon="calendar-check" class="mr-2 text-orange-500" /> {{ t('crm.reservations.title') }}
      </h3>
      <button type="button" class="rounded bg-orange-500 px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-orange-600" @click="adding = !adding">
        <Icon icon="plus" class="mr-1" />{{ t('common.add') }}
      </button>
    </div>

    <form v-if="adding" class="mb-3 space-y-2 rounded-lg border border-orange-200 bg-white p-3 shadow-sm" @submit.prevent="create">
      <div class="grid grid-cols-3 gap-2">
        <label class="col-span-1"><span class="gcrm-label">{{ t('crm.reservations.date') }}</span><input v-model="form.reservation_date" type="date" class="gcrm-input" :min="todayInputValue()" required /></label>
        <label><span class="gcrm-label">{{ t('crm.reservations.time') }}</span><input v-model="form.reservation_time" type="time" class="gcrm-input" required /></label>
        <label><span class="gcrm-label">{{ t('crm.reservations.guests') }}</span><input v-model.number="form.guests_count" type="number" min="1" max="1000" class="gcrm-input" required /></label>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <input v-model.trim="form.table_label" class="gcrm-input" :placeholder="t('crm.reservations.table')" />
        <input v-model.trim="form.occasion" class="gcrm-input" :placeholder="t('crm.reservations.occasion')" />
      </div>
      <textarea v-model="form.notes" rows="2" class="gcrm-input resize-none" :placeholder="t('crm.reservations.notes')" />
      <p v-for="message in errorMessages" :key="message" class="text-xs text-red-600">{{ message }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="gcrm-btn-ghost" @click="adding = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="gcrm-btn bg-orange-500 text-white hover:bg-orange-600" :disabled="saving">{{ t('common.save') }}</button>
      </div>
    </form>

    <div class="space-y-2">
      <div v-for="reservation in reservations" :key="reservation.id" class="rounded-lg border border-orange-200 bg-white p-3 shadow-sm">
        <div class="flex items-start justify-between gap-2">
          <div>
            <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase text-white" :class="reservation.status === 'confirmed' ? 'bg-green-600' : 'bg-orange-500'">
              {{ t(`crm.reservations.statuses.${reservation.status}`) }}
            </span>
            <p class="mt-1 font-bold text-gray-900">{{ formatReservation(reservation.reservation_date, reservation.reservation_time) }}</p>
            <p class="text-xs text-gray-500">
              {{ t('crm.reservations.guestsCount', { count: reservation.guests_count }) }}
              <template v-if="reservation.table_label"> · {{ reservation.table_label }}</template>
              <template v-if="reservation.occasion"> · {{ reservation.occasion }}</template>
            </p>
          </div>
          <div class="flex shrink-0 flex-col items-end gap-1 text-xs">
            <button v-if="reservation.status === 'pending'" type="button" class="font-medium text-green-700 hover:underline" @click="setStatus(reservation, 'confirmed')">
              {{ t('crm.reservations.confirm') }}
            </button>
            <button type="button" class="text-gray-400 hover:text-red-600" @click="setStatus(reservation, 'cancelled')">{{ t('crm.reservations.cancel') }}</button>
          </div>
        </div>
      </div>
      <p v-if="!reservations.length && !adding" class="text-xs text-gray-500">{{ t('crm.reservations.none') }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { formatReservation, todayInputValue } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const reservations = computed(() => client.contact?.upcoming_reservations ?? [])

const adding = ref(false)
const saving = ref(false)
const errorMessages = ref([])
const emptyForm = () => ({ reservation_date: todayInputValue(1), reservation_time: '19:00', guests_count: 2, table_label: '', occasion: '', notes: '' })
const form = reactive(emptyForm())

async function create() {
  saving.value = true
  errorMessages.value = []
  try {
    await client.addReservation({ ...form, table_label: form.table_label || null, occasion: form.occasion || null, notes: form.notes || null })
    Object.assign(form, emptyForm())
    adding.value = false
  } catch (e) {
    errorMessages.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}

async function setStatus(reservation, status) {
  if (status === 'cancelled' && !window.confirm(t('crm.reservations.confirmCancel'))) return
  try {
    await client.updateReservation(reservation.id, { status })
  } catch (e) {
    window.alert(e.message)
  }
}
</script>
