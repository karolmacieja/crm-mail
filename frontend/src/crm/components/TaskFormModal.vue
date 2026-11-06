<template>
  <Modal open :title="task ? t('crm.tasks.edit') : t('crm.tasks.new')" @close="$emit('close')">
    <form id="task-form" class="space-y-3" @submit.prevent="submit">
      <label class="block"><span class="gcrm-label">{{ t('crm.form.title') }}</span><input v-model.trim="form.title" class="gcrm-input" required maxlength="255" /></label>
      <div class="grid grid-cols-2 gap-3">
        <label>
          <span class="gcrm-label">{{ t('crm.form.type') }}</span>
          <select v-model="form.type" class="gcrm-input">
            <option v-for="category in settings.taskCategories" :key="category.key" :value="category.key">{{ category.name }}</option>
          </select>
        </label>
        <label>
          <span class="gcrm-label">{{ t('crm.form.priority') }}</span>
          <select v-model="form.priority" class="gcrm-input">
            <option value="normal">{{ t('crm.priorities.normal') }}</option>
            <option value="high">{{ t('crm.priorities.high') }}</option>
          </select>
        </label>
      </div>
      <label class="block"><span class="gcrm-label">{{ t('tasks.dueDate') }}</span><input v-model="form.due" type="datetime-local" class="gcrm-input" /></label>
      <div>
        <span class="gcrm-label">{{ t('crm.form.contact') }}</span>
        <ContactPicker v-model="form.contact_id" :initial="contact ?? task?.contact ?? null" />
      </div>
      <label class="block"><span class="gcrm-label">{{ t('crm.form.notes') }}</span><textarea v-model="form.description" rows="2" class="gcrm-input resize-none" maxlength="5000" /></label>
      <label class="block">
        <span class="gcrm-label">{{ t('crm.form.assignee') }}</span>
        <select v-model="form.assigned_to" class="gcrm-input">
          <option :value="null">—</option>
          <option v-for="member in team.members" :key="member.id" :value="member.id">{{ member.name }}{{ member.is_me ? ` (${t('crm.form.me')})` : '' }}</option>
        </select>
      </label>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
    </form>
    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="submit" form="task-form" class="gcrm-btn-primary" :disabled="saving || !form.title">{{ t('common.save') }}</button>
    </template>
  </Modal>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { defaultDueInputValue, fromLocalInputValue, toLocalInputValue } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import ContactPicker from '@/crm/components/ContactPicker.vue'
import { useClientStore } from '@/crm/stores/client.js'
import { useSettingsStore } from '@/crm/stores/settings.js'
import { useTasksStore } from '@/crm/stores/tasks.js'
import { useTeamStore } from '@/crm/stores/team.js'

const props = defineProps({
  /** Pre-selected contact (client card). */
  contact: { type: Object, default: null },
  /** Task to edit (otherwise a new one is created). */
  task: { type: Object, default: null },
})
const emit = defineEmits(['close', 'saved'])

const tasks = useTasksStore()
const team = useTeamStore()
const client = useClientStore()
const settings = useSettingsStore()
const editing = props.task
const form = reactive({
  title: editing?.title ?? '',
  type: editing?.type ?? settings.taskCategories[0]?.key ?? 'follow_up',
  priority: editing?.priority ?? settings.preferences?.default_task_priority ?? 'normal',
  due: editing ? toLocalInputValue(editing.due_date) : defaultDueInputValue(settings.preferences, 'task'),
  contact_id: editing ? editing.contact_id ?? null : props.contact?.id ?? null,
  assigned_to: editing ? editing.assignee?.id ?? null : null,
  description: editing?.description ?? '',
})
const saving = ref(false)
const errors = ref([])

onMounted(() => {
  team.load().catch(() => {})
  settings.load().then(() => {
    form.type = settings.taskCategories.some((c) => c.key === form.type) ? form.type : settings.taskCategories[0]?.key
  })
})

async function submit() {
  saving.value = true
  errors.value = []
  const fromFocusedEmail = !editing && props.contact && client.context?.messageId && client.contact?.id === props.contact.id
  const fields = {
    title: form.title,
    type: form.type,
    priority: form.priority,
    due_date: fromLocalInputValue(form.due),
    contact_id: form.contact_id,
    assigned_to: form.assigned_to,
    description: form.description || null,
  }
  try {
    const task = editing ? await tasks.update(editing, fields) : await tasks.create({
      ...fields,
      ...(fromFocusedEmail
        ? { source_email_id: client.context.messageId, source_thread_id: client.context.threadId ?? null, source_email_subject: client.context.subject ?? null }
        : {}),
    })
    emit('saved', task)
    emit('close')
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
