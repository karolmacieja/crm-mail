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

    <!-- Mail: internal / forwarding addresses never become clients -->
    <fieldset class="mt-6 max-w-2xl space-y-3 border-t border-gray-100 pt-4">
      <legend class="mb-2 pt-4 text-sm font-bold text-gray-800"><Icon :icon="['far', 'envelope']" class="mr-1.5 text-primary" />{{ t('settings.preferences.mail.title') }}</legend>
      <p class="text-xs text-gray-500">{{ t('settings.preferences.mail.description') }}</p>
      <label v-if="ownDomain" class="flex items-start gap-2 text-sm text-gray-700">
        <input type="checkbox" class="mt-0.5 accent-primary" :checked="mail.ignore_own_domain !== false" @change="save({ mail: { ignore_own_domain: $event.target.checked } })" />
        <span>{{ t('settings.preferences.mail.ownDomain', { domain: ownDomain }) }}</span>
      </label>
      <div>
        <span class="gcrm-label">{{ t('settings.preferences.mail.addresses') }}</span>
        <div class="mb-2 flex flex-wrap gap-1.5">
          <span v-for="address in addresses" :key="address" class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-700">
            {{ address }}
            <button type="button" class="text-gray-400 hover:text-red-600" :aria-label="t('common.delete')" @click="removeAddress(address)"><Icon icon="xmark" /></button>
          </span>
          <span v-if="!addresses.length" class="text-xs text-gray-400">{{ t('settings.preferences.mail.none') }}</span>
        </div>
        <form class="flex gap-2" @submit.prevent="addAddress">
          <input v-model.trim="newAddress" class="gcrm-input" :placeholder="t('settings.preferences.mail.placeholder')" maxlength="255" />
          <button type="submit" class="gcrm-btn-secondary shrink-0" :disabled="!newAddress"><Icon icon="plus" /> {{ t('common.add') }}</button>
        </form>
      </div>
    </fieldset>

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
import { FREE_DOMAINS, domainOf } from '@/crm/lib/senders.js'
import { useAuthStore } from '@/crm/stores/auth.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const DAYS = [0, 1, 2, 3, 7, 14]

const settings = useSettingsStore()
const prefs = computed(() => settings.preferences ?? {})
const res = computed(() => prefs.value.reservations ?? {})
const mail = computed(() => prefs.value.mail ?? {})
const addresses = computed(() => mail.value.internal_addresses ?? [])
const ownDomain = computed(() => {
  const domain = domainOf(useAuthStore().user?.email)
  return domain && !FREE_DOMAINS.has(domain) ? domain : null
})
const newAddress = ref('')

async function addAddress() {
  const address = newAddress.value.toLowerCase()
  if (!address || addresses.value.includes(address)) return
  if (await save({ mail: { internal_addresses: [...addresses.value, address] } })) newAddress.value = ''
}

const removeAddress = (address) => save({ mail: { internal_addresses: addresses.value.filter((a) => a !== address) } })
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
    return true
  } catch (e) {
    error.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
    return false
  }
}
</script>
