<template>
  <div>
    <button type="button" class="mb-4 text-sm font-medium text-gray-500 hover:text-primary" @click="router.push({ name: 'admin.groups' })">
      <Icon icon="arrow-left" class="mr-1" /> {{ t('admin.groups.back') }}
    </button>

    <AlertMessage v-if="groups.currentError" :message="groups.currentError.message" retryable @retry="groups.show(id)" />
    <div v-else-if="!group" class="h-40 animate-pulse rounded-xl bg-gray-100" />

    <template v-else>
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div>
          <h2 class="text-2xl font-bold text-gray-900">{{ group.name }}</h2>
          <p class="text-sm text-gray-500">{{ group.slug }} · {{ group.timezone }}<template v-if="group.contact_email"> · {{ group.contact_email }}</template></p>
          <p class="mt-2 text-sm text-gray-600">{{ t('admin.groups.summary', { users: group.users_count, contacts: group.contacts_count }) }}</p>
        </div>
        <div class="flex gap-2">
          <button type="button" class="gcrm-btn-secondary" @click="toggleActive">{{ group.is_active ? t('admin.groups.deactivate') : t('admin.groups.activate') }}</button>
          <button type="button" class="gcrm-btn-primary" @click="showEdit = true"><Icon icon="pen" /> {{ t('common.edit') }}</button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50/50 px-5 py-4">
            <h3 class="font-bold text-gray-800">{{ t('nav.users') }}</h3>
            <RouterLink :to="{ name: 'admin.users', query: { group_id: group.id, create: 1 } }" class="text-sm font-medium text-primary hover:underline"><Icon icon="plus" /> {{ t('common.add') }}</RouterLink>
          </div>
          <div class="divide-y divide-gray-100">
            <div v-for="user in group.users" :key="user.id" class="flex items-center justify-between px-5 py-3">
              <div>
                <p class="font-medium text-gray-900">{{ user.name }} <span class="ml-1 text-xs text-gray-400">{{ t(`admin.roles.${user.role}`) }}</span></p>
                <p class="text-xs text-gray-500">{{ user.email }}</p>
              </div>
              <LicenseBadge :state="user.license.status" />
            </div>
            <EmptyState v-if="!group.users.length" icon="user-tie" :text="t('admin.groups.noUsers')" />
          </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50/50 px-5 py-4">
            <h3 class="font-bold text-gray-800">{{ t('nav.licenses') }}</h3>
            <RouterLink :to="{ name: 'admin.licenses', query: { group_id: group.id, create: 1 } }" class="text-sm font-medium text-primary hover:underline"><Icon icon="plus" /> {{ t('common.add') }}</RouterLink>
          </div>
          <div class="divide-y divide-gray-100">
            <div v-for="license in group.licenses" :key="license.id" class="flex items-center justify-between px-5 py-3">
              <div>
                <p class="font-mono text-sm text-gray-900">{{ license.key }}</p>
                <p class="text-xs text-gray-500">{{ license.plan }} · {{ license.seats_used }}/{{ license.seats }} {{ t('admin.licenses.seatsShort') }} · {{ t('admin.licenses.until', { date: formatCalendar(license.expires_at, { withTime: false }) }) }}</p>
              </div>
              <LicenseBadge :state="license.state" />
            </div>
            <EmptyState v-if="!group.licenses.length" icon="key" :text="t('admin.groups.noLicenses')" />
          </div>
        </section>
      </div>

      <!-- Danger zone -->
      <section class="mt-8 rounded-xl border border-red-200 bg-red-50/40 p-6">
        <h3 class="font-bold text-red-700">{{ t('admin.groups.dangerTitle') }}</h3>
        <p class="mt-1 text-sm text-red-700/80">{{ t('admin.groups.dangerText') }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <input v-model.trim="confirmSlug" class="gcrm-input w-64" :placeholder="group.slug" :aria-label="t('admin.groups.confirmLabel')" />
          <button type="button" class="gcrm-btn bg-red-600 text-white hover:bg-red-700" :disabled="confirmSlug !== group.slug || deleting" @click="remove">
            <Icon icon="trash" /> {{ t('admin.groups.delete') }}
          </button>
        </div>
        <p v-if="deleteError" class="mt-2 text-xs text-red-700">{{ deleteError }}</p>
      </section>

      <GroupFormModal v-if="showEdit" :group="group" @close="showEdit = false" @saved="groups.show(id)" />
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import GroupFormModal from '@/admin/components/GroupFormModal.vue'
import LicenseBadge from '@/admin/components/LicenseBadge.vue'
import { useGroupsStore } from '@/admin/stores/groups.js'

const props = defineProps({ id: { type: Number, required: true } })
const groups = useGroupsStore()
const router = useRouter()
const group = computed(() => (groups.current?.id === props.id ? groups.current : null))
const showEdit = ref(false)
const confirmSlug = ref('')
const deleting = ref(false)
const deleteError = ref('')

watch(() => props.id, (id) => groups.show(id), { immediate: true })

async function toggleActive() {
  await groups.update(group.value.id, { is_active: !group.value.is_active })
  await groups.show(props.id)
}

async function remove() {
  deleting.value = true
  deleteError.value = ''
  try {
    await groups.remove(group.value, confirmSlug.value)
    router.replace({ name: 'admin.groups' })
  } catch (e) {
    deleteError.value = e.message
  } finally {
    deleting.value = false
  }
}
</script>
