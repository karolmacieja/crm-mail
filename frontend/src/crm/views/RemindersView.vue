<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('crm.reminders.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover" @click="showCreate = true">
        <Icon icon="plus" class="mr-1" /> {{ t('crm.reminders.add') }}
      </button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative min-w-[250px] flex-1">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('crm.reminders.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
      <select v-model="type" class="w-56 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        <option value="">{{ t('crm.reminders.allTypes') }}</option>
        <option value="email">{{ t('crm.reminders.typeFilters.email') }}</option>
        <option value="reservation">{{ t('crm.reminders.typeFilters.reservation') }}</option>
        <option value="general">{{ t('crm.reminders.typeFilters.general') }}</option>
      </select>
      <select v-model="timeWindow" class="w-40 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        <option value="open">{{ t('crm.windows.open') }}</option>
        <option value="overdue">{{ t('crm.windows.overdue') }}</option>
        <option value="today">{{ t('crm.windows.today') }}</option>
        <option value="upcoming">{{ t('crm.windows.upcoming') }}</option>
        <option value="done">{{ t('crm.windows.done') }}</option>
      </select>
    </div>

    <AlertMessage v-if="reminders.list.error" class="mb-6" :message="reminders.list.error.message" retryable @retry="reminders.load()" />

    <div class="space-y-6">
      <div v-for="group in groups" :key="group.key">
        <h3 class="mb-3 flex items-center px-1 text-sm font-bold uppercase tracking-wider text-gray-500"><Icon :icon="group.icon" class="mr-2" />{{ t(group.label) }}</h3>
        <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white shadow-sm">
          <ReminderItem v-for="reminder in reminders.grouped[group.key]" :key="reminder.id" :reminder="reminder" @open-contact="openProfile" />
        </div>
      </div>
      <EmptyState v-if="reminders.list.loaded && !groups.length" :icon="['far', 'bell']" :text="t('crm.reminders.empty')" />
    </div>

    <ReminderFormModal v-if="showCreate" @close="showCreate = false" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import ReminderFormModal from '@/crm/components/ReminderFormModal.vue'
import ReminderItem from '@/crm/components/ReminderItem.vue'
import { useRemindersStore } from '@/crm/stores/reminders.js'

const reminders = useRemindersStore()
const router = useRouter()
const search = ref(reminders.list.filters.search)
const type = ref(reminders.list.filters.type)
const timeWindow = ref(reminders.list.filters.window)
const showCreate = ref(false)

const GROUPS = [
  { key: 'email', label: 'crm.reminders.groups.email', icon: ['far', 'envelope'] },
  { key: 'reservation', label: 'crm.reminders.groups.reservation', icon: 'calendar-check' },
  { key: 'general', label: 'crm.reminders.groups.general', icon: ['far', 'bell'] },
]
const groups = computed(() => GROUPS.filter((g) => reminders.grouped[g.key].length))

onMounted(() => reminders.load())

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => reminders.load({ filters: { search: value.trim() } }), 300)
})
watch(type, (value) => reminders.load({ filters: { type: value } }))
watch(timeWindow, (value) => reminders.load({ filters: { window: value } }))

function openProfile(contact) {
  router.push({ name: 'crm.clients.show', params: { id: contact.id } })
}
</script>
