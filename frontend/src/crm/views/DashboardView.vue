<template>
  <div>
    <AlertMessage v-if="dashboard.error" class="mb-6" :message="dashboard.error.message" retryable @retry="dashboard.fetch()" />

    <!-- KPI row -->
    <div class="mb-8 grid grid-cols-2 gap-6 md:grid-cols-4">
      <div class="flex flex-col justify-center rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-500">{{ t('crm.dashboard.contacts') }}</p>
        <p class="text-3xl font-bold text-gray-900">{{ summary?.contacts.total ?? '–' }}</p>
      </div>
      <div class="flex flex-col justify-center rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <p class="mb-1 text-xs font-bold uppercase tracking-wider text-gray-500">{{ t('crm.dashboard.clients') }}</p>
        <p class="text-3xl font-bold text-gray-900">{{ summary?.contacts.clients ?? '–' }}</p>
      </div>
      <div class="col-span-2 flex flex-col justify-center rounded-xl bg-gradient-to-r from-primary to-blue-500 p-5 text-white shadow-sm">
        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-white/80">{{ t('crm.dashboard.today') }}</p>
        <div class="flex flex-wrap items-center gap-6">
          <div><span class="text-3xl font-bold">{{ summary?.emails.today ?? 0 }}</span> <span class="text-sm opacity-90">{{ t('crm.dashboard.newEmails', { count: summary?.emails.today ?? 0 }) }}</span></div>
          <div class="h-8 border-r border-white/30" />
          <div><span class="text-3xl font-bold">{{ summary?.tasks.due_today ?? 0 }}</span> <span class="text-sm opacity-90">{{ t('crm.dashboard.tasksCount', { count: summary?.tasks.due_today ?? 0 }) }}</span></div>
          <div class="h-8 border-r border-white/30" />
          <div><span class="text-3xl font-bold">{{ summary?.reminders.due_today ?? 0 }}</span> <span class="text-sm opacity-90">{{ t('crm.dashboard.remindersCount', { count: summary?.reminders.due_today ?? 0 }) }}</span></div>
        </div>
      </div>
    </div>

    <div class="mb-8 grid grid-cols-1 gap-8 lg:grid-cols-2">
      <!-- Reminders -->
      <div class="flex h-[350px] flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between rounded-t-xl border-b border-gray-200 bg-gray-50/50 px-5 py-4">
          <h3 class="font-bold text-gray-800">{{ t('nav.reminders') }}</h3>
          <button type="button" class="text-sm font-medium text-primary hover:underline" @click="showReminderModal = true"><Icon icon="plus" /> {{ t('common.add') }}</button>
        </div>
        <div class="flex min-h-0 flex-1 flex-col p-5">
          <SegmentedTabs v-model="dashboard.tabs.reminders" class="mb-4" :tabs="windowTabs(summary?.reminders)" />
          <div class="space-y-3 overflow-y-auto pr-2">
            <div
              v-for="reminder in reminderList"
              :key="reminder.id"
              class="flex items-start rounded-lg border p-3"
              :class="reminder.time_status === 'overdue' ? 'border-red-100 bg-red-50' : 'border-gray-200'"
            >
              <Icon :icon="reminder.type === 'reservation' ? 'cake-candles' : ['far', 'envelope']" class="mr-3 mt-1" :class="reminder.time_status === 'overdue' ? 'text-red-400' : 'text-gray-400'" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-gray-900">{{ reminder.title }}</p>
                <p class="mt-0.5 truncate text-xs text-gray-600">
                  <template v-if="reminder.source_email_subject">{{ t('crm.reminders.fromEmail', { subject: reminder.source_email_subject }) }}</template>
                  <template v-else-if="reminder.contact">{{ reminder.contact.name || reminder.contact.email }}</template>
                  · {{ formatCalendar(reminder.remind_at) }}
                </p>
              </div>
              <button type="button" class="ml-2 text-gray-300 hover:text-green-600" :title="t('crm.reminders.markDone')" @click="reminders.markDone(reminder)"><Icon icon="check" /></button>
            </div>
            <EmptyState v-if="summary && !reminderList.length" :icon="['far', 'bell']" :text="t(`crm.dashboard.emptyReminders.${dashboard.tabs.reminders}`)" />
          </div>
        </div>
      </div>

      <!-- Tasks -->
      <div class="flex h-[350px] flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between rounded-t-xl border-b border-gray-200 bg-gray-50/50 px-5 py-4">
          <h3 class="font-bold text-gray-800">{{ t('nav.tasks') }}</h3>
          <button type="button" class="text-sm font-medium text-primary hover:underline" @click="showTaskModal = true"><Icon icon="plus" /> {{ t('common.add') }}</button>
        </div>
        <div class="flex min-h-0 flex-1 flex-col p-5">
          <SegmentedTabs v-model="dashboard.tabs.tasks" class="mb-4" :tabs="windowTabs(summary?.tasks)" />
          <div class="space-y-3 overflow-y-auto pr-2">
            <TaskItem v-for="task in taskList" :key="task.id" :task="task" @open-contact="openProfile" />
            <EmptyState v-if="summary && !taskList.length" icon="list-check" :text="t(`crm.dashboard.emptyTasks.${dashboard.tabs.tasks}`)" />
          </div>
        </div>
      </div>
    </div>

    <!-- Recent emails (Gmail inbox) -->
    <div class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
      <div class="flex items-center justify-between rounded-t-xl border-b border-gray-200 bg-gray-50/50 px-5 py-4">
        <h3 class="font-bold text-gray-800">{{ t('crm.dashboard.recentEmails') }}</h3>
      </div>
      <div class="flex-1 divide-y divide-gray-100">
        <div
          v-for="thread in inbox.recent.slice(0, 10)"
          :key="thread.threadId"
          class="cursor-pointer border-l-4 p-4 transition-colors"
          :class="isActive(thread) ? 'border-primary bg-primary/5 hover:bg-primary/10' : 'border-transparent hover:bg-gray-50'"
          role="button"
          tabindex="0"
          @click="openEmail(thread)"
          @keydown.enter="openEmail(thread)"
        >
          <div class="mb-1 flex items-start justify-between gap-3">
            <p class="truncate text-gray-900" :class="thread.unread ? 'font-bold' : 'font-medium'">
              {{ thread.name || thread.email }}
              <CategoryBadge class="ml-2" :category="inbox.senderContact(thread.email)?.category" />
            </p>
            <span class="shrink-0 text-xs font-medium text-gray-500">{{ thread.date }}</span>
          </div>
          <p class="truncate text-sm text-gray-600">
            <Icon :icon="thread.replied ? 'reply' : ['far', 'envelope']" class="mr-1 text-gray-400" /> {{ thread.subject || t('crm.timeline.noSubject') }}
          </p>
        </div>
        <EmptyState v-if="!inbox.recent.length" :icon="['far', 'envelope']" :text="t('crm.dashboard.inboxEmpty')">
          <button v-if="gmail?.goInbox" type="button" class="gcrm-btn-secondary text-xs" @click="gmail.goInbox()">{{ t('crm.layout.inbox') }}</button>
        </EmptyState>
      </div>
    </div>

    <TaskFormModal v-if="showTaskModal" @close="showTaskModal = false" />
    <ReminderFormModal v-if="showReminderModal" @close="showReminderModal = false" />
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import CategoryBadge from '@/shared/components/CategoryBadge.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import SegmentedTabs from '@/shared/components/SegmentedTabs.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import ReminderFormModal from '@/crm/components/ReminderFormModal.vue'
import TaskFormModal from '@/crm/components/TaskFormModal.vue'
import TaskItem from '@/crm/components/TaskItem.vue'
import { useClientStore } from '@/crm/stores/client.js'
import { useDashboardStore } from '@/crm/stores/dashboard.js'
import { useInboxStore } from '@/crm/stores/inbox.js'
import { useRemindersStore } from '@/crm/stores/reminders.js'

const dashboard = useDashboardStore()
const inbox = useInboxStore()
const client = useClientStore()
const reminders = useRemindersStore()
const router = useRouter()
const gmail = inject('gmail', null)

const showTaskModal = ref(false)
const showReminderModal = ref(false)
const summary = computed(() => dashboard.summary)

onMounted(() => dashboard.fetch())

const windowTabs = (section) => [
  { key: 'overdue', label: t('crm.windows.overdue'), count: section?.overdue ?? 0, tone: 'danger' },
  { key: 'today', label: t('crm.windows.today'), count: section?.due_today ?? 0, tone: 'primary' },
  { key: 'upcoming', label: t('crm.windows.upcoming'), count: section?.upcoming_week ?? 0 },
]

const taskList = computed(() => summary.value?.tasks.lists[dashboard.tabs.tasks] ?? [])
const reminderList = computed(() => summary.value?.reminders.lists[dashboard.tabs.reminders] ?? [])

const isActive = (thread) => client.isSidebarOpen && client.context?.threadId === thread.threadId

function openEmail(thread) {
  client.openEmail({
    email: thread.email,
    name: thread.name,
    subject: thread.subject,
    threadId: thread.threadId,
    // Rows only expose the thread id; it identifies the email on the timeline.
    messageId: `thread:${thread.threadId}`,
  })
}

function openProfile(contact) {
  router.push({ name: 'crm.clients.show', params: { id: contact.id } })
}
</script>
