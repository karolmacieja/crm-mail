<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('admin.groups.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover" @click="showCreate = true">
        <Icon icon="plus" class="mr-1" /> {{ t('admin.groups.new') }}
      </button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative min-w-[250px] flex-1">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('admin.groups.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
      <select v-model="active" class="w-48 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary">
        <option value="">{{ t('admin.groups.allStatuses') }}</option>
        <option value="1">{{ t('admin.groups.activeOnly') }}</option>
        <option value="0">{{ t('admin.groups.inactiveOnly') }}</option>
      </select>
    </div>

    <AlertMessage v-if="list.error" class="mb-6" :message="list.error.message" retryable @retry="groups.load()" />

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50/50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3 font-semibold">{{ t('admin.groups.name') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('nav.users') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('admin.groups.contacts') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('admin.groups.license') }}</th>
            <th class="px-6 py-3 text-right font-semibold">{{ t('crm.clients.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="group in list.items" :key="group.id" class="group cursor-pointer hover:bg-gray-50" @click="open(group)">
            <td class="px-6 py-4">
              <p class="font-bold text-gray-900 group-hover:text-primary">
                {{ group.name }}
                <span v-if="!group.is_active" class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-gray-500">{{ t('admin.groups.inactive') }}</span>
              </p>
              <p class="text-xs text-gray-500">{{ group.slug }} · {{ group.timezone }}</p>
            </td>
            <td class="px-6 py-4 text-gray-700">{{ group.users_count }}</td>
            <td class="px-6 py-4 text-gray-700">{{ group.contacts_count }}</td>
            <td class="px-6 py-4">
              <template v-if="currentLicense(group.licenses)">
                <LicenseBadge :state="currentLicense(group.licenses).state" />
                <span class="ml-2 text-xs text-gray-500">{{ currentLicense(group.licenses).seats_used }}/{{ currentLicense(group.licenses).seats }} · {{ formatCalendar(currentLicense(group.licenses).expires_at, { withTime: false }) }}</span>
              </template>
              <LicenseBadge v-else state="missing" />
            </td>
            <td class="px-6 py-4 text-right text-gray-400"><Icon icon="chevron-right" /></td>
          </tr>
        </tbody>
      </table>
      <EmptyState v-if="list.loaded && !list.items.length" icon="building" :text="t('admin.groups.empty')" />
    </div>

    <GroupFormModal v-if="showCreate" @close="showCreate = false" @saved="open" />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import GroupFormModal from '@/admin/components/GroupFormModal.vue'
import LicenseBadge from '@/admin/components/LicenseBadge.vue'
import { currentLicense } from '@/admin/lib/badges.js'
import { useGroupsStore } from '@/admin/stores/groups.js'

const groups = useGroupsStore()
const router = useRouter()
const list = groups.list
const search = ref(list.filters.search)
const active = ref(list.filters.is_active)
const showCreate = ref(false)

onMounted(() => groups.load())

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => groups.load({ filters: { search: value.trim() } }), 300)
})
watch(active, (value) => groups.load({ filters: { is_active: value } }))

const open = (group) => router.push({ name: 'admin.groups.show', params: { id: group.id } })
</script>
