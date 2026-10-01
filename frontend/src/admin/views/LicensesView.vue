<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('admin.licenses.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover" @click="openForm(null)">
        <Icon icon="plus" class="mr-1" /> {{ t('admin.licenses.new') }}
      </button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <select v-model="groupId" class="w-64 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary">
        <option value="">{{ t('admin.users.allGroups') }}</option>
        <option v-for="g in groups.options" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
      </select>
      <select v-model="state" class="w-56 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary">
        <option value="">{{ t('admin.licenses.allStates') }}</option>
        <option value="active">{{ t('admin.licenseStates.active') }}</option>
        <option value="expiring">{{ t('admin.licenses.expiring') }}</option>
        <option value="expired">{{ t('admin.licenseStates.expired') }}</option>
      </select>
    </div>

    <AlertMessage v-if="list.error" class="mb-6" :message="list.error.message" retryable @retry="licenses.load()" />

    <div class="space-y-3">
      <div v-for="license in list.items" :key="license.id" class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-4 p-4">
          <button type="button" class="flex min-w-0 flex-1 items-center gap-3 text-left" @click="toggle(license)">
            <Icon icon="chevron-right" class="text-gray-400 transition-transform" :class="{ 'rotate-90': expanded === license.id }" />
            <div class="min-w-0">
              <p class="font-bold text-gray-900">{{ license.group?.name }} <span class="ml-2 text-xs font-normal text-gray-500">{{ license.plan }}</span></p>
              <p class="font-mono text-xs text-gray-500">{{ license.key }}</p>
            </div>
          </button>

          <div class="w-40">
            <div class="mb-1 flex justify-between text-xs text-gray-500">
              <span>{{ t('admin.licenses.seats') }}</span><span>{{ license.seats_used }}/{{ license.seats }}</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
              <div class="h-full rounded-full" :class="license.seats_used >= license.seats ? 'bg-amber-500' : 'bg-primary'" :style="{ width: `${Math.min(100, (license.seats_used / license.seats) * 100)}%` }" />
            </div>
          </div>

          <div class="w-36 text-right">
            <LicenseBadge :state="license.state" />
            <p class="mt-1 text-xs text-gray-500">{{ t('admin.licenses.until', { date: formatCalendar(license.expires_at, { withTime: false }) }) }}</p>
          </div>

          <div class="flex items-center gap-1 text-gray-400">
            <button type="button" class="rounded px-2 py-1 text-xs font-medium hover:bg-gray-100 hover:text-primary" @click="extend(license, 30)">+30 {{ t('admin.licenses.days') }}</button>
            <button type="button" class="rounded px-2 py-1 text-xs font-medium hover:bg-gray-100 hover:text-primary" @click="extend(license, 365)">+1 {{ t('admin.licenses.year') }}</button>
            <button type="button" class="p-1 hover:text-primary" :title="t('common.edit')" @click="openForm(license)"><Icon icon="pen" /></button>
            <button type="button" class="p-1 hover:text-red-600" :title="t('common.delete')" @click="remove(license)"><Icon icon="trash" /></button>
          </div>
        </div>

        <!-- Assignments ("Przydziały") -->
        <div v-if="expanded === license.id" class="border-t border-gray-100 bg-gray-50/50 p-4">
          <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">{{ t('admin.licenses.assignments') }}</h4>
          <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
            <li v-for="user in license.users ?? []" :key="user.id" class="flex items-center justify-between px-3 py-2 text-sm">
              <span><span class="font-medium text-gray-900">{{ user.name }}</span> <span class="text-xs text-gray-500">{{ user.email }}</span></span>
              <button type="button" class="text-xs text-gray-400 hover:text-red-600" @click="unassign(license, user)">{{ t('admin.licenses.unassign') }}</button>
            </li>
            <li v-if="!(license.users ?? []).length" class="px-3 py-2 text-xs text-gray-500">{{ t('admin.licenses.noAssignments') }}</li>
          </ul>
          <form v-if="license.seats_used < license.seats" class="mt-3 flex gap-2" @submit.prevent="assign(license)">
            <select v-model="assignUserId" class="gcrm-input max-w-xs">
              <option :value="null" disabled>{{ t('admin.licenses.pickUser') }}</option>
              <option v-for="user in candidates" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
            </select>
            <button type="submit" class="gcrm-btn-primary" :disabled="!assignUserId">{{ t('admin.licenses.assign') }}</button>
          </form>
          <p v-else class="mt-2 text-xs text-amber-700">{{ t('admin.licenses.full') }}</p>
          <p v-if="actionError" class="mt-2 text-xs text-red-600">{{ actionError }}</p>
        </div>
      </div>
      <EmptyState v-if="list.loaded && !list.items.length" icon="key" :text="t('admin.licenses.empty')" />
    </div>

    <LicenseFormModal v-if="formOpen" :license="editing" :default-group-id="groupId ? Number(groupId) : null" @close="formOpen = false" />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { adminApi } from '@/admin/api.js'
import LicenseBadge from '@/admin/components/LicenseBadge.vue'
import LicenseFormModal from '@/admin/components/LicenseFormModal.vue'
import { useGroupsStore } from '@/admin/stores/groups.js'
import { useLicensesStore } from '@/admin/stores/licenses.js'

const licenses = useLicensesStore()
const groups = useGroupsStore()
const route = useRoute()
const router = useRouter()
const list = licenses.list

const groupId = ref(route.query.group_id ? String(route.query.group_id) : list.filters.group_id)
const state = ref(list.filters.state)
const formOpen = ref(Boolean(route.query.create))
const editing = ref(null)
const expanded = ref(null)
const candidates = ref([])
const assignUserId = ref(null)
const actionError = ref('')

onMounted(() => {
  groups.loadOptions()
  // ?create=1 (from a group's page) opens the form once; don't reopen it on refresh.
  if (route.query.create) router.replace({ query: { ...route.query, create: undefined } })
  licenses.load({ filters: { group_id: groupId.value } })
})
watch(groupId, (value) => licenses.load({ filters: { group_id: value } }))
watch(state, (value) => licenses.load({ filters: { state: value } }))

function openForm(license) {
  editing.value = license
  formOpen.value = true
}

/** Expand a license: load its seat holders and the group's users without a seat. */
async function toggle(license) {
  actionError.value = ''
  if (expanded.value === license.id) {
    expanded.value = null
    return
  }
  expanded.value = license.id
  assignUserId.value = null
  const [details, users] = await Promise.all([
    adminApi.get(`/admin/licenses/${license.id}`),
    adminApi.get('/admin/users', { params: { group_id: license.group_id, per_page: 100 } }),
  ])
  Object.assign(license, details.data.data)
  candidates.value = users.data.data.filter((u) => u.role !== 'master_admin' && u.license.status === 'missing')
}

async function run(action) {
  actionError.value = ''
  try {
    return await action()
  } catch (e) {
    actionError.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
    window.alert(actionError.value)
  }
}

const extend = (license, days) => run(() => licenses.extend(license.id, days))

async function assign(license) {
  await run(() => licenses.assign(license.id, assignUserId.value))
  candidates.value = candidates.value.filter((u) => u.id !== assignUserId.value)
  assignUserId.value = null
}

const unassign = (license, user) => run(() => licenses.unassign(license.id, user.id))

async function remove(license) {
  if (window.confirm(t('admin.licenses.confirmDelete', { key: license.key }))) await run(() => licenses.remove(license))
}
</script>
