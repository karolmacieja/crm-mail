<template>
  <label
    class="group flex cursor-pointer items-start rounded-lg border p-3 transition-colors"
    :class="urgent ? 'border-red-100 bg-red-50/30 hover:bg-red-50' : 'border-gray-200 hover:bg-gray-50'"
  >
    <input
      type="checkbox"
      class="mr-3 mt-1 h-4 w-4 shrink-0 rounded border-gray-300"
      :class="urgent ? 'accent-red-500' : 'accent-primary'"
      :checked="task.is_completed"
      :disabled="busy || task.can_edit === false"
      @change="toggle"
    />
    <div class="min-w-0 flex-1">
      <p class="text-sm font-bold transition-colors" :class="task.is_completed ? 'text-gray-400 line-through' : 'text-gray-900 group-hover:text-primary'">
        {{ task.title }}
      </p>
      <!-- Narrow board columns: wrap the date under the name instead of cutting the name off. -->
      <p class="mt-1 flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5 text-xs text-gray-500">
        <span class="min-w-0 max-w-full truncate">
          <template v-if="task.contact && !hideContact">
            <button type="button" class="hover:text-primary hover:underline" @click.prevent="$emit('open-contact', task.contact)">
              <Icon icon="user" class="mr-1 text-gray-400" />{{ task.contact.name || task.contact.email }}
            </button>
          </template>
          <template v-else-if="task.source_email_subject">
            <Icon :icon="['far', 'envelope']" class="mr-1 text-gray-400" />{{ t('crm.tasks.fromEmail', { subject: task.source_email_subject }) }}
          </template>
          <template v-else-if="task.assignee">
            <Icon icon="user" class="mr-1 text-gray-400" />{{ task.assignee.name }}
          </template>
        </span>
        <span v-if="task.due_date" class="shrink-0" :class="task.time_status === 'overdue' ? 'font-semibold text-red-600' : 'text-gray-400'">
          {{ formatCalendar(task.due_date, { withTime: task.time_status === 'today' }) }}
        </span>
      </p>
    </div>
    <CalendarLink v-if="task.due_date && !task.is_completed" kind="task" :item="task" class="ml-2" />
    <button v-if="task.can_edit !== false" type="button" class="ml-2 hidden text-gray-300 hover:text-red-600 group-hover:block" :aria-label="t('common.delete')" @click.prevent="remove">
      <Icon icon="trash" class="text-xs" />
    </button>
  </label>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import CalendarLink from '@/crm/components/CalendarLink.vue'
import { useTasksStore } from '@/crm/stores/tasks.js'

const props = defineProps({
  task: { type: Object, required: true },
  hideContact: { type: Boolean, default: false },
})
defineEmits(['open-contact'])

const tasks = useTasksStore()
const busy = ref(false)
const urgent = computed(() => !props.task.is_completed && (props.task.priority === 'high' || ['overdue', 'today'].includes(props.task.time_status)))

async function run(action) {
  busy.value = true
  try {
    await action()
  } catch (e) {
    window.alert(e.message)
  } finally {
    busy.value = false
  }
}

const toggle = () => run(() => tasks.toggle(props.task))
const remove = () => window.confirm(t('tasks.confirmDelete', { title: props.task.title })) && run(() => tasks.remove(props.task))
</script>
