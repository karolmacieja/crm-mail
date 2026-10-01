<template>
  <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <h3 class="font-bold text-gray-800">{{ t('settings.preferences.title') }}</h3>
    <p class="mb-4 text-xs text-gray-500">{{ t('settings.preferences.description') }}</p>

    <div class="grid max-w-xl gap-4 sm:grid-cols-2">
      <label>
        <span class="gcrm-label">{{ t('settings.preferences.reminderTime') }}</span>
        <input type="time" class="gcrm-input" :value="prefs.default_reminder_time" @change="save({ default_reminder_time: $event.target.value })" />
      </label>
      <label>
        <span class="gcrm-label">{{ t('settings.preferences.dueDays') }}</span>
        <select class="gcrm-input" :value="prefs.default_task_due_days" @change="save({ default_task_due_days: Number($event.target.value) })">
          <option v-for="days in [0, 1, 2, 3, 7, 14]" :key="days" :value="days">{{ days === 0 ? t('time.today') : t('settings.preferences.dueDaysOption', { count: days }) }}</option>
        </select>
      </label>
      <div>
        <span class="gcrm-label">{{ t('common.language') }}</span>
        <LanguageSwitch />
      </div>
    </div>
    <p v-if="saved" class="mt-4 text-xs text-green-700">{{ t('settings.saved') }}</p>
    <p v-if="error" class="mt-4 text-xs text-red-600">{{ error }}</p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import { t } from '@/shared/lib/i18n.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const settings = useSettingsStore()
const prefs = computed(() => settings.preferences ?? {})
const saved = ref(false)
const error = ref('')

async function save(patch) {
  saved.value = false
  error.value = ''
  try {
    await settings.savePreferences(patch)
    saved.value = true
  } catch (e) {
    error.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
  }
}
</script>
