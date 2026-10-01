<template>
  <Modal open :title="license ? t('admin.licenses.edit') : t('admin.licenses.new')" @close="$emit('close')">
    <form id="license-form" class="space-y-3" @submit.prevent="submit">
      <label v-if="!license" class="block">
        <span class="gcrm-label">{{ t('admin.users.group') }} *</span>
        <select v-model="form.group_id" class="gcrm-input" required>
          <option :value="null" disabled>—</option>
          <option v-for="g in groups.options" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
      </label>
      <div class="grid grid-cols-2 gap-3">
        <label><span class="gcrm-label">{{ t('admin.licenses.plan') }}</span><input v-model.trim="form.plan" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('admin.licenses.seats') }} *</span><input v-model.number="form.seats" type="number" min="1" max="1000" class="gcrm-input" required /></label>
        <label><span class="gcrm-label">{{ t('admin.licenses.startsAt') }}</span><input v-model="form.starts_at" type="date" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('admin.licenses.expiresAt') }} *</span><input v-model="form.expires_at" type="date" class="gcrm-input" required /></label>
      </div>
      <label v-if="license" class="block">
        <span class="gcrm-label">{{ t('admin.licenses.status') }}</span>
        <select v-model="form.status" class="gcrm-input">
          <option v-for="s in ['active', 'suspended', 'cancelled']" :key="s" :value="s">{{ t(`admin.licenseStates.${s}`) }}</option>
        </select>
      </label>
      <label class="block"><span class="gcrm-label">{{ t('crm.form.notes') }}</span><textarea v-model="form.notes" rows="2" class="gcrm-input resize-none" /></label>
      <label v-if="license" class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.regenerate_key" type="checkbox" class="accent-primary" /> {{ t('admin.licenses.regenerate') }}</label>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="license-form" class="gcrm-btn-primary" :disabled="saving">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { todayInputValue } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { useGroupsStore } from '@/admin/stores/groups.js'
import { useLicensesStore } from '@/admin/stores/licenses.js'

const props = defineProps({
  license: { type: Object, default: null },
  defaultGroupId: { type: Number, default: null },
})
const emit = defineEmits(['close', 'saved'])

const groups = useGroupsStore()
const licenses = useLicensesStore()
const dateOnly = (iso) => (iso ? iso.slice(0, 10) : '')

const form = reactive({
  group_id: props.license?.group_id ?? props.defaultGroupId,
  plan: props.license?.plan ?? 'standard',
  seats: props.license?.seats ?? 5,
  starts_at: dateOnly(props.license?.starts_at) || todayInputValue(),
  expires_at: dateOnly(props.license?.expires_at) || todayInputValue(365),
  status: props.license?.status ?? 'active',
  notes: props.license?.notes ?? '',
  regenerate_key: false,
})
const saving = ref(false)
const errors = ref([])

onMounted(() => groups.loadOptions())

async function submit() {
  saving.value = true
  errors.value = []
  const payload = { plan: form.plan, seats: form.seats, starts_at: form.starts_at || null, expires_at: form.expires_at, notes: form.notes || null }
  try {
    const saved = props.license
      ? await licenses.update(props.license.id, { ...payload, status: form.status, regenerate_key: form.regenerate_key })
      : await licenses.create({ ...payload, group_id: form.group_id })
    emit('saved', saved)
    emit('close')
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
