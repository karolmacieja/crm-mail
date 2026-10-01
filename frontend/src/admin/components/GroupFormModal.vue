<template>
  <Modal open :title="group ? t('admin.groups.edit') : t('admin.groups.new')" @close="$emit('close')">
    <form id="group-form" class="space-y-3" @submit.prevent="submit">
      <label class="block"><span class="gcrm-label">{{ t('admin.groups.name') }} *</span><input v-model.trim="form.name" class="gcrm-input" required /></label>
      <div class="grid grid-cols-2 gap-3">
        <label><span class="gcrm-label">{{ t('admin.groups.email') }}</span><input v-model.trim="form.contact_email" type="email" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('contact.phone') }}</span><input v-model.trim="form.phone" class="gcrm-input" /></label>
      </div>
      <label class="block">
        <span class="gcrm-label">{{ t('admin.groups.timezone') }}</span>
        <select v-model="form.timezone" class="gcrm-input">
          <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
        </select>
      </label>
      <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.is_active" type="checkbox" class="accent-primary" /> {{ t('admin.groups.active') }}</label>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="group-form" class="gcrm-btn-primary" :disabled="saving || !form.name">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { reactive, ref } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { t } from '@/shared/lib/i18n.js'
import { useGroupsStore } from '@/admin/stores/groups.js'

const props = defineProps({ group: { type: Object, default: null } })
const emit = defineEmits(['close', 'saved'])

const groups = useGroupsStore()
const timezones = Intl.supportedValuesOf?.('timeZone') ?? ['Europe/Warsaw', 'UTC']
const form = reactive({
  name: props.group?.name ?? '',
  contact_email: props.group?.contact_email ?? '',
  phone: props.group?.phone ?? '',
  timezone: props.group?.timezone ?? 'Europe/Warsaw',
  is_active: props.group?.is_active ?? true,
})
const saving = ref(false)
const errors = ref([])

async function submit() {
  saving.value = true
  errors.value = []
  const payload = { ...form, contact_email: form.contact_email || null, phone: form.phone || null }
  try {
    const saved = props.group ? await groups.update(props.group.id, payload) : await groups.create(payload)
    emit('saved', saved)
    emit('close')
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
