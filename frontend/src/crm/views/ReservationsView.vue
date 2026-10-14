<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('crm.reservations.title') }}</h2>
      <button type="button" class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600" @click="openForm(null)">
        <Icon icon="plus" class="mr-1" /> {{ t('crm.reservations.new') }}
      </button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative min-w-[250px] flex-1">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('crm.reservations.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
      <SegmentedTabs v-model="timeWindow" :tabs="tabs" />
    </div>

    <AlertMessage v-if="list.error" class="mb-6" :message="list.error.message" retryable @retry="reservations.load()" />

    <div v-if="list.loading && !list.loaded" class="space-y-3">
      <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-gray-100" />
    </div>
    <div v-else class="space-y-6">
      <section v-for="group in grouped" :key="group.date">
        <h3 class="mb-2 px-1 text-sm font-bold uppercase tracking-wider text-gray-500">
          {{ group.label }}
          <span class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-700">{{ t('crm.reservations.guestsCount', { count: group.guests }) }}</span>
        </h3>
        <div class="grid gap-3 lg:grid-cols-2">
          <ReservationItem v-for="item in group.items" :key="item.id" :reservation="item" @open-contact="openProfile" @edit="openForm" />
        </div>
      </section>
      <EmptyState v-if="list.loaded && !list.items.length" icon="calendar-check" :text="t(`crm.reservations.empty.${timeWindow}`)" />
    </div>

    <ReservationFormModal v-if="form.open" :reservation="form.reservation" @close="form.open = false" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import SegmentedTabs from '@/shared/components/SegmentedTabs.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import ReservationFormModal from '@/crm/components/ReservationFormModal.vue'
import ReservationItem from '@/crm/components/ReservationItem.vue'
import { useDashboardStore } from '@/crm/stores/dashboard.js'
import { useReservationsStore } from '@/crm/stores/reservations.js'

const reservations = useReservationsStore()
const dashboard = useDashboardStore()
const router = useRouter()
const list = reservations.list
const search = ref(list.filters.search)
const timeWindow = ref(list.filters.window)
const form = reactive({ open: false, reservation: null })

const counts = computed(() => dashboard.summary?.reservations ?? {})
const tabs = computed(() => [
  { key: 'overdue', label: t('crm.windows.overdue'), count: counts.value.overdue ?? 0, tone: 'danger' },
  { key: 'today', label: t('crm.windows.today'), count: counts.value.due_today ?? 0, tone: 'primary' },
  { key: 'upcoming', label: t('crm.windows.upcoming'), count: counts.value.upcoming_week ?? 0 },
  { key: 'open', label: t('crm.reservations.allUpcoming') },
  { key: 'done', label: t('crm.reservations.closed') },
])

/** Grouped by day, with the number of guests ("Dziś · 18 gości"). */
const grouped = computed(() => {
  const groups = new Map()
  for (const item of list.items) {
    const group = groups.get(item.reservation_date) ?? { date: item.reservation_date, label: formatCalendar(item.starts_at, { withTime: false }), guests: 0, items: [] }
    group.items.push(item)
    group.guests += item.guests_count
    groups.set(item.reservation_date, group)
  }
  return [...groups.values()]
})

onMounted(() => {
  reservations.load()
  if (!dashboard.summary) dashboard.fetch()
})

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => reservations.load({ filters: { search: value.trim() } }), 300)
})
watch(timeWindow, (value) => reservations.load({ filters: { window: value } }))

function openForm(reservation) {
  Object.assign(form, { open: true, reservation })
}

function openProfile(contact) {
  router.push({ name: 'crm.clients.show', params: { id: contact.id } })
}
</script>
