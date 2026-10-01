<template>
  <div>
    <AlertMessage v-if="stats.error" class="mb-6" :message="stats.error.message" retryable @retry="stats.fetch()" />
    <div class="mb-8 grid grid-cols-2 gap-6 md:grid-cols-4">
      <div v-for="card in cards" :key="card.label" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-500">{{ t(card.label) }}</p>
        <p class="text-3xl font-bold" :class="card.tone">{{ card.value ?? '–' }}</p>
        <p v-if="card.hint" class="mt-1 text-xs text-gray-400">{{ card.hint }}</p>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
      <div class="flex items-center justify-between rounded-t-xl border-b border-gray-200 bg-gray-50/50 px-5 py-4">
        <h3 class="font-bold text-gray-800">{{ t('admin.dashboard.expiring') }}</h3>
        <RouterLink :to="{ name: 'admin.licenses' }" class="text-sm font-medium text-primary hover:underline">{{ t('admin.dashboard.allLicenses') }}</RouterLink>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="license in expiring.items" :key="license.id" class="flex items-center justify-between px-5 py-3">
          <div>
            <p class="font-bold text-gray-900">{{ license.group?.name }}</p>
            <p class="font-mono text-xs text-gray-500">{{ license.key }} · {{ license.seats_used }}/{{ license.seats }} {{ t('admin.licenses.seatsShort') }}</p>
          </div>
          <span class="rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-bold text-amber-700">{{ formatCalendar(license.expires_at, { withTime: false }) }}</span>
        </div>
        <EmptyState v-if="expiring.loaded && !expiring.items.length" icon="key" :text="t('admin.dashboard.noneExpiring')" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { adminApi } from '@/admin/api.js'
import { useAdminStatsStore } from '@/admin/stores/stats.js'

const stats = useAdminStatsStore()
const expiringList = createPaginatedList((params) => adminApi.get('/admin/licenses', { params }), { state: 'expiring' })
const expiring = expiringList.state

onMounted(() => {
  stats.fetch()
  expiringList.load()
})

const cards = computed(() => {
  const d = stats.data
  return [
    { label: 'admin.dashboard.groups', value: d?.groups.active, hint: d ? t('admin.dashboard.ofTotal', { total: d.groups.total }) : null },
    { label: 'admin.dashboard.users', value: d?.users.total, hint: d?.users.without_seat ? t('admin.dashboard.withoutSeat', { count: d.users.without_seat }) : null },
    { label: 'admin.dashboard.seats', value: d ? `${d.licenses.seats_used}/${d.licenses.seats_total}` : null },
    { label: 'admin.dashboard.expiringCount', value: d?.licenses.expiring_30_days, tone: d?.licenses.expiring_30_days ? 'text-amber-600' : 'text-gray-900' },
  ].map((c) => ({ tone: 'text-gray-900', ...c }))
})
</script>
