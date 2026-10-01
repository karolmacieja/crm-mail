<template>
  <Modal open :title="t('crm.reminders.new')" @close="$emit('close')">
    <form id="reminder-form" class="space-y-3" @submit.prevent="submit">
      <label class="block"><span class="gcrm-label">{{ t('crm.form.title') }}</span><input v-model.trim="form.title" class="gcrm-input" required maxlength="255" /></label>
      <div class="grid grid-cols-2 gap-3">
        <label>
          <span class="gcrm-label">{{ t('crm.form.type') }}</span>
          <select v-model="form.type" class="gcrm-input">
            <option v-for="type in ['email', 'reservation', 'general']" :key="type" :value="type">{{ t(`crm.reminderTypes.${type}`) }}</option>
          </select>
        </label>
        <label><span class="gcrm-label">{{ t('crm.form.when') }}</span><input v-model="form.when" type="datetime-local" class="gcrm-input" required /></label>
      </div>
      <div>
        <span class="gcrm-label">{{ t('crm.form.contact') }}</span>
        <ContactPicker v-model="form.contact_id" :initial="contact" />
      </div>
      <label v-if="form.type === 'reservation' && reservations.length" class="block">
        <span class="gcrm-label">{{ t('crm.reservations.title') }}</span>
        <select v-model="form.reservation_id" class="gcrm-input">
          <option :value="null">—</option>
          <option v-for="r in reservations" :key="r.id" :value="r.id">{{ formatReservation(r.reservation_date, r.reservation_time) }} · {{ r.occasion || r.table_label || '' }}</option>
        </select>
      </label>
      <label class="block"><span class="gcrm-label">{{ t('crm.form.notes') }}</span><textarea v-model="form.notes" rows="2" class="gcrm-input resize-none" /></label>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="reminder-form" class="gcrm-btn-primary" :disabled="saving || !form.title">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { defaultDueInputValue, formatReservation, fromLocalInputValue } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import ContactPicker from '@/crm/components/ContactPicker.vue'
import { useClientStore } from '@/crm/stores/client.js'
import { useRemindersStore } from '@/crm/stores/reminders.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const props = defineProps({ contact: { type: Object, default: null } })
const emit = defineEmits(['close', 'saved'])

const reminders = useRemindersStore()
const client = useClientStore()
const fromFocusedEmail = Boolean(props.contact && client.context?.messageId && client.contact?.id === props.contact.id)

const form = reactive({
  title: '',
  type: fromFocusedEmail ? 'email' : 'general',
  when: defaultDueInputValue(useSettingsStore().preferences),
  contact_id: props.contact?.id ?? null,
  reservation_id: null,
  notes: '',
})
const saving = ref(false)
const errors = ref([])
const reservations = computed(() => (props.contact && client.contact?.id === props.contact.id ? client.contact.upcoming_reservations ?? [] : []))

async function submit() {
  saving.value = true
  errors.value = []
  try {
    const reminder = await reminders.create({
      title: form.title,
      type: form.type,
      remind_at: fromLocalInputValue(form.when),
      contact_id: form.contact_id,
      reservation_id: form.type === 'reservation' ? form.reservation_id : null,
      notes: form.notes || null,
      ...(fromFocusedEmail ? { source_email_id: client.context.messageId, source_email_subject: client.context.subject ?? null } : {}),
    })
    emit('saved', reminder)
    emit('close')
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
