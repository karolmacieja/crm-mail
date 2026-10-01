<template>
  <Modal open :title="t('crm.clients.new')" @close="$emit('close')">
    <form id="new-client" class="space-y-3" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-3">
        <label class="col-span-2"><span class="gcrm-label">{{ t('contact.name') }}</span><input v-model.trim="form.name" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('contact.email') }} *</span><input v-model.trim="form.email" type="email" class="gcrm-input" required /></label>
        <label><span class="gcrm-label">{{ t('contact.phone') }}</span><input v-model.trim="form.phone" type="tel" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('crm.client.company') }}</span><input v-model.trim="form.company" class="gcrm-input" /></label>
        <label>
          <span class="gcrm-label">{{ t('crm.client.category') }}</span>
          <select v-model="form.category_id" class="gcrm-input">
            <option :value="null">—</option>
            <option v-for="c in contacts.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.is_client" type="checkbox" class="accent-primary" /> {{ t('crm.client.isClient') }}</label>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="new-client" class="gcrm-btn-primary" :disabled="saving || !form.email">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { api, toApiError } from '@/crm/api.js'
import Modal from '@/shared/components/Modal.vue'
import { t } from '@/shared/lib/i18n.js'
import { useContactsStore } from '@/crm/stores/contacts.js'
import { useDashboardStore } from '@/crm/stores/dashboard.js'
import { useInboxStore } from '@/crm/stores/inbox.js'

const props = defineProps({ isClient: { type: Boolean, default: true } })
const emit = defineEmits(['close', 'created'])

const contacts = useContactsStore()
const form = reactive({ name: '', email: '', phone: '', company: '', category_id: null, is_client: props.isClient })
const saving = ref(false)
const errors = ref([])

async function submit() {
  saving.value = true
  errors.value = []
  try {
    const { data } = await api.post('/contacts', { ...form, name: form.name || null, phone: form.phone || null, company: form.company || null })
    useInboxStore().rememberSender(data.data)
    useDashboardStore().invalidate()
    form.is_client ? contacts.loadClients() : contacts.loadAddressBook()
    emit('created', data.data)
    emit('close')
  } catch (e) {
    const error = toApiError(e)
    errors.value = Object.keys(error.fieldErrors).length ? Object.values(error.fieldErrors) : [error.message]
  } finally {
    saving.value = false
  }
}
</script>
