<template>
  <Modal open :title="user ? t('admin.users.edit') : t('admin.users.new')" @close="$emit('close')">
    <form id="user-form" class="space-y-3" autocomplete="off" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-3">
        <label class="col-span-2"><span class="gcrm-label">{{ t('contact.name') }} *</span><input v-model.trim="form.name" class="gcrm-input" required /></label>
        <label class="col-span-2"><span class="gcrm-label">{{ t('contact.email') }} *</span><input v-model.trim="form.email" type="email" class="gcrm-input" required /></label>
        <label class="col-span-2">
          <span class="gcrm-label">{{ user ? t('admin.users.newPassword') : t('login.password') + ' *' }}</span>
          <input v-model="form.password" type="password" autocomplete="new-password" class="gcrm-input" :required="!user" minlength="8" />
        </label>
        <label>
          <span class="gcrm-label">{{ t('admin.users.role') }}</span>
          <select v-model="form.role" class="gcrm-input">
            <option v-for="role in ['staff', 'manager', 'master_admin']" :key="role" :value="role">{{ t(`admin.roles.${role}`) }}</option>
          </select>
        </label>
        <label v-if="form.role !== 'master_admin'">
          <span class="gcrm-label">{{ t('admin.users.group') }} *</span>
          <select v-model="form.group_id" class="gcrm-input" required>
            <option :value="null" disabled>—</option>
            <option v-for="g in groups.options" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </label>
      </div>
      <label v-if="!user && form.role !== 'master_admin' && groupLicenses.length" class="block">
        <span class="gcrm-label">{{ t('admin.users.seat') }}</span>
        <select v-model="form.license_id" class="gcrm-input">
          <option :value="null">{{ t('admin.users.noSeat') }}</option>
          <option v-for="l in groupLicenses" :key="l.id" :value="l.id" :disabled="l.seats_used >= l.seats">
            {{ l.key }} ({{ l.seats_used }}/{{ l.seats }})
          </option>
        </select>
      </label>
      <p v-if="user && form.group_id !== user.group?.id && user.group" class="rounded bg-amber-50 p-2 text-xs text-amber-800">{{ t('admin.users.groupChangeWarning') }}</p>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="user-form" class="gcrm-btn-primary" :disabled="saving">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { t } from '@/shared/lib/i18n.js'
import { adminApi } from '@/admin/api.js'
import { useGroupsStore } from '@/admin/stores/groups.js'
import { useUsersStore } from '@/admin/stores/users.js'

const props = defineProps({
  user: { type: Object, default: null },
  defaultGroupId: { type: Number, default: null },
})
const emit = defineEmits(['close', 'saved'])

const groups = useGroupsStore()
const users = useUsersStore()
const form = reactive({
  name: props.user?.name ?? '',
  email: props.user?.email ?? '',
  password: '',
  role: props.user?.role ?? 'staff',
  group_id: props.user?.group?.id ?? props.defaultGroupId,
  license_id: null,
})
const groupLicenses = ref([])
const saving = ref(false)
const errors = ref([])

onMounted(() => groups.loadOptions())

watch(
  () => form.group_id,
  async (groupId) => {
    form.license_id = null
    groupLicenses.value = []
    if (!groupId || props.user) return
    const { data } = await adminApi.get('/admin/licenses', { params: { group_id: groupId, state: 'active' } })
    groupLicenses.value = data.data
    form.license_id = data.data.find((l) => l.seats_used < l.seats)?.id ?? null
  },
  { immediate: true },
)

async function submit() {
  saving.value = true
  errors.value = []
  const payload = { name: form.name, email: form.email, role: form.role, group_id: form.role === 'master_admin' ? null : form.group_id }
  if (form.password) payload.password = form.password
  try {
    const saved = props.user ? await users.update(props.user.id, payload) : await users.create({ ...payload, license_id: form.license_id })
    emit('saved', saved)
    emit('close')
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
