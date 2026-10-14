<template>
  <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <h3 class="font-bold text-gray-800">{{ t('settings.preferences.title') }}</h3>
    <p class="mb-5 text-xs text-gray-500">{{ t('settings.preferences.description') }}</p>

    <div class="grid gap-6 lg:grid-cols-3">
      <!-- Tasks -->
      <fieldset class="space-y-3">
        <legend class="mb-2 text-sm font-bold text-gray-800"><Icon icon="list-check" class="mr-1.5 text-primary" />{{ t('nav.tasks') }}</legend>
        <label class="block">
          <span class="gcrm-label">{{ t('settings.preferences.dueDays') }}</span>
          <select class="gcrm-input" :value="prefs.default_task_due_days" @change="save({ default_task_due_days: Number($event.target.value) })">
            <option v-for="days in DAYS" :key="days" :value="days">{{ dayLabel(days) }}</option>
          </select>
        </label>
        <label class="block">
          <span class="gcrm-label">{{ t('settings.preferences.taskTime') }}</span>
          <input type="time" class="gcrm-input" :value="prefs.default_task_time" @change="save({ default_task_time: $event.target.value })" />
        </label>
        <label class="block">
          <span class="gcrm-label">{{ t('settings.preferences.taskPriority') }}</span>
          <select class="gcrm-input" :value="prefs.default_task_priority" @change="save({ default_task_priority: $event.target.value })">
            <option v-for="priority in ['normal', 'high']" :key="priority" :value="priority">{{ t(`crm.priorities.${priority}`) }}</option>
          </select>
        </label>
      </fieldset>

      <!-- Reminders -->
      <fieldset class="space-y-3">
        <legend class="mb-2 text-sm font-bold text-gray-800"><Icon :icon="['far', 'bell']" class="mr-1.5 text-primary" />{{ t('nav.reminders') }}</legend>
        <label class="block">
          <span class="gcrm-label">{{ t('settings.preferences.reminderDays') }}</span>
          <select class="gcrm-input" :value="prefs.default_reminder_days" @change="save({ default_reminder_days: Number($event.target.value) })">
            <option v-for="days in DAYS" :key="days" :value="days">{{ dayLabel(days) }}</option>
          </select>
        </label>
        <label class="block">
          <span class="gcrm-label">{{ t('settings.preferences.reminderTime') }}</span>
          <input type="time" class="gcrm-input" :value="prefs.default_reminder_time" @change="save({ default_reminder_time: $event.target.value })" />
        </label>
      </fieldset>

      <!-- Reservations -->
      <fieldset class="space-y-3">
        <legend class="mb-2 text-sm font-bold text-gray-800"><Icon icon="calendar-check" class="mr-1.5 text-orange-500" />{{ t('nav.reservations') }}</legend>
        <div class="grid grid-cols-2 gap-3">
          <label class="block">
            <span class="gcrm-label">{{ t('settings.preferences.reservationTime') }}</span>
            <input type="time" class="gcrm-input" :value="res.default_time" @change="save({ reservations: { default_time: $event.target.value } })" />
          </label>
          <label class="block">
            <span class="gcrm-label">{{ t('settings.preferences.reservationGuests') }}</span>
            <input type="number" min="1" max="1000" class="gcrm-input" :value="res.default_guests" @change="save({ reservations: { default_guests: Number($event.target.value) } })" />
          </label>
        </div>
        <label class="block">
          <span class="gcrm-label">{{ t('settings.preferences.reservationDuration') }}</span>
          <select class="gcrm-input" :value="res.duration_minutes" @change="save({ reservations: { duration_minutes: Number($event.target.value) } })">
            <option v-for="minutes in [60, 90, 120, 150, 180, 240, 360]" :key="minutes" :value="minutes">{{ t('settings.preferences.hoursOption', { hours: minutes / 60 }) }}</option>
          </select>
        </label>
        <label class="flex items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" class="mt-0.5 accent-primary" :checked="res.auto_reminder" @change="save({ reservations: { auto_reminder: $event.target.checked } })" />
          <span>{{ t('settings.preferences.reservationReminder') }}<span class="block text-xs text-gray-500">{{ t('settings.preferences.reservationReminderHint') }}</span></span>
        </label>
        <label v-if="res.auto_reminder" class="block">
          <span class="gcrm-label">{{ t('settings.preferences.reservationReminderBefore') }}</span>
          <select class="gcrm-input" :value="res.reminder_hours_before" @change="save({ reservations: { reminder_hours_before: Number($event.target.value) } })">
            <option v-for="hours in [1, 2, 3, 6, 12, 24, 48, 72, 168]" :key="hours" :value="hours">{{ beforeLabel(hours) }}</option>
          </select>
        </label>
      </fieldset>
    </div>

    <div class="mt-6 max-w-xs border-t border-gray-100 pt-4">
      <span class="gcrm-label">{{ t('common.language') }}</span>
      <LanguageSwitch />
    </div>
    <p class="mt-4 text-xs text-gray-500">{{ t('settings.preferences.calendarPointer') }}</p>
    <p v-if="saved" class="mt-2 text-xs text-green-700">{{ t('settings.saved') }}</p>
    <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const DAYS = [0, 1, 2, 3, 7, 14]

const settings = useSettingsStore()
const prefs = computed(() => settings.preferences ?? {})
const res = computed(() => prefs.value.reservations ?? {})
const saved = ref(false)
const error = ref('')

const dayLabel = (days) => (days === 0 ? t('time.today') : t('settings.preferences.dueDaysOption', { count: days }))
const beforeLabel = (hours) => (hours % 24 === 0 ? t('settings.preferences.daysBefore', { count: hours / 24 }) : t('settings.preferences.hoursBefore', { count: hours }))

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
