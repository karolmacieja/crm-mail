<template>
  <!-- The client's open tasks and pending reminders, with quick add. -->
  <section class="space-y-4">
    <div>
      <div class="mb-2 flex items-center justify-between">
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-800"><Icon icon="list-check" class="mr-2" />{{ t('nav.tasks') }}</h3>
        <button type="button" class="text-xs font-medium text-primary hover:underline" @click="showTaskModal = true"><Icon icon="plus" /> {{ t('common.add') }}</button>
      </div>
      <div class="space-y-2">
        <TaskItem v-for="task in client.openTasks" :key="task.id" :task="task" hide-contact />
        <p v-if="!client.openTasks.length" class="text-xs text-gray-500">{{ t('crm.tasks.noneForClient') }}</p>
      </div>
    </div>

    <div>
      <div class="mb-2 flex items-center justify-between">
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-800"><Icon :icon="['far', 'bell']" class="mr-2" />{{ t('nav.reminders') }}</h3>
        <button type="button" class="text-xs font-medium text-primary hover:underline" @click="showReminderModal = true"><Icon icon="plus" /> {{ t('common.add') }}</button>
      </div>
      <div v-if="client.pendingReminders.length" class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white">
        <ReminderItem v-for="reminder in client.pendingReminders" :key="reminder.id" :reminder="reminder" compact />
      </div>
      <p v-else class="text-xs text-gray-500">{{ t('crm.reminders.noneForClient') }}</p>
    </div>

    <TaskFormModal v-if="showTaskModal" :contact="client.contact" @close="showTaskModal = false" />
    <ReminderFormModal v-if="showReminderModal" :contact="client.contact" @close="showReminderModal = false" />
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import ReminderFormModal from '@/crm/components/ReminderFormModal.vue'
import ReminderItem from '@/crm/components/ReminderItem.vue'
import TaskFormModal from '@/crm/components/TaskFormModal.vue'
import TaskItem from '@/crm/components/TaskItem.vue'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const showTaskModal = ref(false)
const showReminderModal = ref(false)
</script>
