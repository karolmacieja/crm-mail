<template>
  <Modal open :title="reservation ? t('crm.reservations.edit') : t('crm.reservations.new')" @close="$emit('close')">
    <form id="reservation-form" class="space-y-3" @submit.prevent="submit">
      <div>
        <span class="gcrm-label">{{ t('crm.form.contact') }}</span>
        <ContactPicker v-model="form.contact_id" :initial="contact ?? reservation?.contact ?? null" />
      </div>
      <div class="grid grid-cols-3 gap-3">
        <label><span class="gcrm-label">{{ t('crm.reservations.date') }}</span><input v-model="form.reservation_date" type="date" class="gcrm-input" required /></label>
        <label><span class="gcrm-label">{{ t('crm.reservations.time') }}</span><input v-model="form.reservation_time" type="time" class="gcrm-input" required /></label>
        <label><span class="gcrm-label">{{ t('crm.reservations.guests') }}</span><input v-model.number="form.guests_count" type="number" min="1" max="1000" class="gcrm-input" required /></label>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <input v-model.trim="form.table_label" class="gcrm-input" maxlength="50" :placeholder="t('crm.reservations.table')" />
        <input v-model.trim="form.occasion" class="gcrm-input" maxlength="100" :placeholder="t('crm.reservations.occasion')" />
      </div>
      <label v-if="reservation" class="block">
        <span class="gcrm-label">{{ t('contact.status') }}</span>
        <select v-model="form.status" class="gcrm-input">
          <option v-for="status in STATUSES" :key="status" :value="status">{{ t(`crm.reservations.statuses.${status}`) }}</option>
        </select>
      </label>
      <textarea v-model="form.notes" rows="2" class="gcrm-input resize-none" :placeholder="t('crm.reservations.notes')" />
      <p v-if="!reservation && prefs.reservations?.auto_reminder" class="text-xs text-gray-500">
        <Icon :icon="['far', 'bell']" class="mr-1" />{{ t('crm.reservations.autoReminderNote', { hours: prefs.reservations.reminder_hours_before }) }}
      </p>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="reservation-form" class="gcrm-btn bg-orange-500 text-white hover:bg-orange-600" :disabled="saving">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { Icon } from '@/shared/icons.js'
import { todayInputValue } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import ContactPicker from '@/crm/components/ContactPicker.vue'
import { useReservationsStore } from '@/crm/stores/reservations.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const STATUSES = ['pending', 'confirmed', 'completed', 'no_show', 'cancelled']

const props = defineProps({
  /** Preselected client (e.g. from the client card). */
  contact: { type: Object, default: null },
  /** Booking to edit. */
  reservation: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const reservations = useReservationsStore()
const prefs = computed(() => useSettingsStore().preferences ?? {})
const r = props.reservation
const form = reactive({
  contact_id: r?.contact_id ?? props.contact?.id ?? null,
  reservation_date: r?.reservation_date ?? todayInputValue(1),
  reservation_time: r?.reservation_time ?? prefs.value.reservations?.default_time ?? '19:00',
  guests_count: r?.guests_count ?? prefs.value.reservations?.default_guests ?? 2,
  table_label: r?.table_label ?? '',
  occasion: r?.occasion ?? '',
  notes: r?.notes ?? '',
  status: r?.status ?? 'pending',
})
const saving = ref(false)
const errors = ref([])

async function submit() {
  saving.value = true
  errors.value = []
  const payload = { ...form, table_label: form.table_label || null, occasion: form.occasion || null, notes: form.notes || null }
  if (!r) delete payload.status
  try {
    const saved = r ? await reservations.update(r, payload) : await reservations.create(payload)
    emit('saved', saved)
    emit('close')
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
