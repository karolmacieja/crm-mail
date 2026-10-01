<template>
  <form class="space-y-2" novalidate @submit.prevent="submit">
    <input
      v-model.trim="title"
      class="gcrm-input"
      :class="{ 'gcrm-input-error': fieldErrors.title }"
      :placeholder="t('tasks.placeholder')"
      maxlength="255"
      :disabled="saving"
    />
    <p v-if="fieldErrors.title" class="text-xs text-red-600">{{ fieldErrors.title }}</p>

    <div class="flex gap-2">
      <input
        v-model="due"
        type="datetime-local"
        class="gcrm-input min-w-0 flex-1"
        :class="{ 'gcrm-input-error': fieldErrors.due_date }"
        :disabled="saving"
        :aria-label="t('tasks.dueDate')"
      />
      <button type="submit" class="gcrm-btn-primary shrink-0" :disabled="saving || !title">
        <Spinner v-if="saving" size="xs" />
        {{ t('common.add') }}
      </button>
    </div>
    <p v-if="fieldErrors.due_date" class="text-xs text-red-600">{{ fieldErrors.due_date }}</p>
    <p v-if="error && !Object.keys(fieldErrors).length" class="text-xs text-red-600">{{ error }}</p>
  </form>
</template>

<script setup>
import { ref } from 'vue'
import { defaultDueInputValue, fromLocalInputValue } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import Spinner from '@/shared/components/Spinner.vue'

const props = defineProps({
  /** Async ({ title, due_date }) => void. Throws ApiError on failure. */
  onSubmit: { type: Function, required: true },
})

const title = ref('')
const due = ref(defaultDueInputValue())
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})

async function submit() {
  saving.value = true
  error.value = ''
  fieldErrors.value = {}

  try {
    await props.onSubmit({ title: title.value, due_date: fromLocalInputValue(due.value) })
    title.value = ''
    due.value = defaultDueInputValue()
  } catch (apiError) {
    error.value = apiError.message
    fieldErrors.value = apiError.fieldErrors ?? {}
  } finally {
    saving.value = false
  }
}
</script>
