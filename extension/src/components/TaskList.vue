<template>
  <ul v-if="tasks.length" class="divide-y divide-gray-100">
    <li v-for="task in tasks" :key="task.id" class="group flex items-start gap-2.5 py-2">
      <input
        type="checkbox"
        class="mt-0.5 h-4 w-4 shrink-0 cursor-pointer rounded border-gray-300 accent-indigo-600"
        :checked="task.is_completed"
        :disabled="busy[task.id]"
        :aria-label="`Mark “${task.title}” as ${task.is_completed ? 'open' : 'done'}`"
        @change="toggle(task)"
      />

      <div class="min-w-0 flex-1">
        <p class="break-words text-sm" :class="task.is_completed ? 'text-gray-400 line-through' : 'text-gray-900'">
          {{ task.title }}
        </p>
        <p v-if="showContact && task.contact" class="truncate text-xs text-gray-500">
          <button type="button" class="hover:text-indigo-600 hover:underline" @click="$emit('open-contact', task.contact)">
            {{ task.contact.name || task.contact.email }}
          </button>
        </p>
        <p
          v-if="task.due_date"
          class="text-xs"
          :class="task.is_overdue ? 'font-medium text-red-600' : 'text-gray-500'"
          :title="formatDateTime(task.due_date)"
        >
          {{ task.is_overdue ? 'Overdue · ' : '' }}{{ formatRelative(task.due_date) }}
        </p>
      </div>

      <button
        v-if="removable"
        type="button"
        class="shrink-0 rounded p-1 text-gray-400 opacity-0 transition hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover:opacity-100"
        :disabled="busy[task.id]"
        :aria-label="`Delete “${task.title}”`"
        @click="remove(task)"
      >
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path
            fill-rule="evenodd"
            d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4z"
            clip-rule="evenodd"
          />
        </svg>
      </button>
    </li>
  </ul>
  <p v-else class="py-2 text-xs text-gray-500">{{ emptyText }}</p>
  <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useCrmStore } from '@/stores/crm.js'
import { formatDateTime, formatRelative } from '@/lib/format.js'

defineProps({
  tasks: { type: Array, required: true },
  showContact: { type: Boolean, default: false },
  removable: { type: Boolean, default: true },
  emptyText: { type: String, default: 'No tasks yet.' },
})
defineEmits(['open-contact'])

const crm = useCrmStore()
const busy = reactive({})
const error = ref('')

async function run(task, action) {
  busy[task.id] = true
  error.value = ''
  try {
    await action()
  } catch (apiError) {
    error.value = apiError.message
  } finally {
    delete busy[task.id]
  }
}

const toggle = (task) => run(task, () => crm.updateTask(task, { is_completed: !task.is_completed }))

const remove = (task) => {
  if (!window.confirm(`Delete the task “${task.title}”?`)) return
  return run(task, () => crm.deleteTask(task))
}
</script>
