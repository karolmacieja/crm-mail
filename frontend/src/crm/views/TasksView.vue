<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('crm.tasks.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover" @click="showCreate = true">
        <Icon icon="plus" class="mr-1" /> {{ t('crm.tasks.create') }}
      </button>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative min-w-[250px] flex-1">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('crm.tasks.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
      <select v-model="filter" class="w-48 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        <option value="">{{ t('crm.tasks.allCategories') }}</option>
        <option value="urgent">{{ t('crm.tasks.filters.urgent') }}</option>
        <option v-for="category in settings.taskCategories" :key="category.key" :value="category.key">{{ category.name }}</option>
      </select>
      <select v-model="timeWindow" class="w-40 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
        <option value="open">{{ t('crm.windows.open') }}</option>
        <option value="overdue">{{ t('crm.windows.overdue') }}</option>
        <option value="today">{{ t('crm.windows.today') }}</option>
        <option value="upcoming">{{ t('crm.windows.upcoming') }}</option>
        <option value="done">{{ t('crm.windows.done') }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-gray-600"><input v-model="mine" type="checkbox" class="accent-primary" /> {{ t('crm.tasks.mine') }}</label>
    </div>

    <AlertMessage v-if="tasks.list.error" class="mb-6" :message="tasks.list.error.message" retryable @retry="tasks.load()" />

    <!-- Board -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
      <div v-for="column in visibleColumns" :key="column.key" class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between rounded-t-xl border-b border-gray-200 px-4 py-3" :class="tone(column.color).header">
          <h3 class="flex items-center font-bold"><Icon :icon="column.icon" class="mr-2" />{{ column.label }}</h3>
          <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="tone(column.color).count">{{ (tasks.board[column.key] ?? []).length }}</span>
        </div>
        <div class="space-y-3 p-4">
          <TaskItem v-for="task in tasks.board[column.key] ?? []" :key="task.id" :task="task" @open-contact="openProfile" />
          <p v-if="!(tasks.board[column.key] ?? []).length" class="py-4 text-center text-xs text-gray-400">{{ t('crm.tasks.columnEmpty') }}</p>
        </div>
      </div>
    </div>

    <TaskFormModal v-if="showCreate" @close="showCreate = false" />
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import TaskFormModal from '@/crm/components/TaskFormModal.vue'
import TaskItem from '@/crm/components/TaskItem.vue'
import { tone } from '@/shared/lib/colors.js'
import { useSettingsStore } from '@/crm/stores/settings.js'
import { useTasksStore } from '@/crm/stores/tasks.js'

const tasks = useTasksStore()
const settings = useSettingsStore()
const router = useRouter()

const search = ref(tasks.list.filters.search)
const filter = ref('')
const timeWindow = ref(tasks.list.filters.window)
const mine = ref(tasks.list.filters.assigned_to === 'me')
const showCreate = ref(false)

// "Pilne" is computed; the other columns are the task categories from Settings.
const COLUMNS = computed(() => [
  { key: 'urgent', label: t('crm.tasks.columns.urgent'), icon: 'fire', color: 'red' },
  ...settings.taskCategories.map((c) => ({ key: c.key, label: c.name, icon: c.icon || 'list-check', color: c.color })),
  // Tasks whose category was removed meanwhile (normally reassigned by the API).
  ...Object.keys(tasks.board)
    .filter((key) => key !== 'urgent' && !settings.taskCategoryByKey(key))
    .map((key) => ({ key, label: key, icon: 'list-check', color: 'gray' })),
])
const visibleColumns = computed(() => (filter.value ? COLUMNS.value.filter((c) => c.key === filter.value) : COLUMNS.value))

onMounted(() => {
  settings.load()
  tasks.load()
})

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => tasks.load({ filters: { search: value.trim() } }), 300)
})
watch(timeWindow, (value) => tasks.load({ filters: { window: value } }))
watch(mine, (value) => tasks.load({ filters: { assigned_to: value ? 'me' : '' } }))

function openProfile(contact) {
  router.push({ name: 'crm.clients.show', params: { id: contact.id } })
}
</script>
