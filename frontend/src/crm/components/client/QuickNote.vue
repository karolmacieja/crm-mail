<template>
  <section>
    <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-800">
      <Icon :icon="['far', 'pen-to-square']" class="mr-2" />{{ t('crm.client.addNote') }}
    </h3>
    <textarea
      v-model="body"
      :placeholder="t('crm.client.notePlaceholder')"
      class="h-20 w-full resize-none rounded-lg border border-gray-300 bg-white p-3 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
      maxlength="10000"
      @keydown.ctrl.enter="save"
      @keydown.meta.enter="save"
    />
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    <div class="mt-2 flex justify-end">
      <button
        type="button"
        class="rounded-lg bg-gray-800 px-4 py-1.5 text-sm font-medium text-white transition-colors hover:bg-gray-900 disabled:opacity-50"
        :disabled="!body.trim() || saving"
        @click="save"
      >
        {{ t('crm.client.saveToTimeline') }}
      </button>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const body = ref('')
const saving = ref(false)
const error = ref('')

async function save() {
  if (!body.value.trim() || saving.value) return
  saving.value = true
  error.value = ''
  try {
    await client.addNote(body.value.trim())
    body.value = ''
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
