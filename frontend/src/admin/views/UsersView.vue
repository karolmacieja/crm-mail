<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('admin.users.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover" @click="openForm(null)">
        <Icon icon="plus" class="mr-1" /> {{ t('admin.users.new') }}
      </button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative min-w-[250px] flex-1">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('admin.users.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
      <select v-model="groupId" class="w-56 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary">
        <option value="">{{ t('admin.users.allGroups') }}</option>
        <option v-for="g in groups.options" :key="g.id" :value="String(g.id)">{{ g.name }}</option>
      </select>
      <select v-model="role" class="w-44 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary">
        <option value="">{{ t('admin.users.allRoles') }}</option>
        <option v-for="r in ['staff', 'manager', 'master_admin']" :key="r" :value="r">{{ t(`admin.roles.${r}`) }}</option>
      </select>
    </div>

    <AlertMessage v-if="list.error" class="mb-6" :message="list.error.message" retryable @retry="users.load()" />

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50/50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3 font-semibold">{{ t('contact.name') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('admin.users.group') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('admin.users.role') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('admin.users.seat') }}</th>
            <th class="px-6 py-3 text-right font-semibold">{{ t('crm.clients.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="user in list.items" :key="user.id" class="hover:bg-gray-50">
            <td class="px-6 py-4">
              <p class="font-bold text-gray-900">{{ user.name }}</p>
              <p class="text-xs text-gray-500">{{ user.email }}</p>
            </td>
            <td class="px-6 py-4 text-gray-700">
              <RouterLink v-if="user.group" :to="{ name: 'admin.groups.show', params: { id: user.group.id } }" class="hover:text-primary hover:underline">{{ user.group.name }}</RouterLink>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-6 py-4 text-gray-700">{{ t(`admin.roles.${user.role}`) }}</td>
            <td class="px-6 py-4">
              <span v-if="user.role === 'master_admin'" class="text-xs text-gray-400">{{ t('admin.users.notNeeded') }}</span>
              <LicenseBadge v-else :state="user.license.status" />
            </td>
            <td class="whitespace-nowrap px-6 py-4 text-right text-gray-400">
              <button type="button" class="mx-1 p-1 hover:text-primary" :title="t('common.edit')" @click="openForm(user)"><Icon icon="pen" /></button>
              <button type="button" class="mx-1 p-1 hover:text-amber-600" :title="t('admin.users.revokeTokens')" @click="revoke(user)"><Icon icon="right-from-bracket" /></button>
              <button v-if="user.id !== auth.user?.id" type="button" class="mx-1 p-1 hover:text-red-600" :title="t('common.delete')" @click="remove(user)"><Icon icon="trash" /></button>
            </td>
          </tr>
        </tbody>
      </table>
      <EmptyState v-if="list.loaded && !list.items.length" icon="user-tie" :text="t('admin.users.empty')" />
      <footer v-if="list.meta.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-6 py-3 text-xs text-gray-600">
        <span>{{ t('dashboard.page', { page: list.meta.current_page, pages: list.meta.last_page }) }}</span>
        <div class="flex gap-2">
          <button type="button" class="gcrm-btn-secondary px-2 py-1 text-xs" :disabled="list.meta.current_page <= 1" @click="users.load({ page: list.meta.current_page - 1 })">{{ t('dashboard.previous') }}</button>
          <button type="button" class="gcrm-btn-secondary px-2 py-1 text-xs" :disabled="list.meta.current_page >= list.meta.last_page" @click="users.load({ page: list.meta.current_page + 1 })">{{ t('dashboard.next') }}</button>
        </div>
      </footer>
    </div>

    <p v-if="notice" class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ notice }}</p>
    <UserFormModal v-if="formOpen" :user="editing" :default-group-id="groupId ? Number(groupId) : null" @close="formOpen = false" />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import LicenseBadge from '@/admin/components/LicenseBadge.vue'
import UserFormModal from '@/admin/components/UserFormModal.vue'
import { useAdminAuthStore } from '@/admin/stores/auth.js'
import { useGroupsStore } from '@/admin/stores/groups.js'
import { useUsersStore } from '@/admin/stores/users.js'

const users = useUsersStore()
const groups = useGroupsStore()
const auth = useAdminAuthStore()
const route = useRoute()
const router = useRouter()
const list = users.list

const search = ref(list.filters.search)
const groupId = ref(route.query.group_id ? String(route.query.group_id) : list.filters.group_id)
const role = ref(list.filters.role)
const formOpen = ref(Boolean(route.query.create))
const editing = ref(null)
const notice = ref('')

onMounted(() => {
  groups.loadOptions()
  // ?create=1 (from a group's page) opens the form once; don't reopen it on refresh.
  if (route.query.create) router.replace({ query: { ...route.query, create: undefined } })
  users.load({ filters: { group_id: groupId.value } })
})

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => users.load({ filters: { search: value.trim() } }), 300)
})
watch(groupId, (value) => users.load({ filters: { group_id: value } }))
watch(role, (value) => users.load({ filters: { role: value } }))

function openForm(user) {
  editing.value = user
  formOpen.value = true
}

async function revoke(user) {
  if (!window.confirm(t('admin.users.confirmRevoke', { name: user.name }))) return
  const count = await users.revokeTokens(user)
  notice.value = t('admin.users.revoked', { count })
}

async function remove(user) {
  if (!window.confirm(t('admin.users.confirmDelete', { name: user.name }))) return
  try {
    await users.remove(user)
  } catch (e) {
    window.alert(e.message)
  }
}
</script>
