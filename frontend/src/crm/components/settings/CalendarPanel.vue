<template>
  <div class="space-y-6">
    <!-- 1. Private calendar feed (works with any calendar app, no Google setup needed) -->
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h3 class="font-bold text-gray-800"><Icon icon="calendar-check" class="mr-2 text-primary" />{{ t('settings.calendar.feedTitle') }}</h3>
      <p class="mb-4 mt-1 text-xs text-gray-500">{{ t('settings.calendar.feedDescription') }}</p>

      <template v-if="feedUrl">
        <div class="flex gap-2">
          <input :value="feedUrl" readonly class="gcrm-input font-mono text-xs" :aria-label="t('settings.calendar.feedUrl')" @focus="$event.target.select()" />
          <button type="button" class="gcrm-btn-secondary shrink-0" @click="copy"><Icon icon="copy" /> {{ copied ? t('settings.calendar.copied') : t('settings.calendar.copy') }}</button>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
          <a :href="googleSubscribeUrl" target="_blank" rel="noopener" class="gcrm-btn-primary"><Icon icon="calendar-plus" /> {{ t('settings.calendar.addGoogle') }}</a>
          <a :href="outlookSubscribeUrl" target="_blank" rel="noopener" class="gcrm-btn-secondary">{{ t('settings.calendar.addOutlook') }}</a>
          <a :href="webcalUrl" class="gcrm-btn-secondary">{{ t('settings.calendar.addApple') }}</a>
        </div>
        <p class="mt-3 text-xs text-amber-700">{{ t('settings.calendar.refreshNote') }}</p>

        <div class="mt-4 grid max-w-xl gap-4 sm:grid-cols-2">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" class="accent-primary" :checked="prefs.calendar?.include_reservations" @change="save({ calendar: { include_reservations: $event.target.checked } })" />
            {{ t('settings.calendar.includeReservations') }}
          </label>
          <label>
            <span class="gcrm-label">{{ t('settings.calendar.alarm') }}</span>
            <select class="gcrm-input" :value="prefs.calendar?.alarm_minutes" @change="save({ calendar: { alarm_minutes: Number($event.target.value) } })">
              <option v-for="minutes in [0, 5, 10, 15, 30, 60, 1440]" :key="minutes" :value="minutes">{{ t('settings.calendar.alarmOption', { count: minutes }) }}</option>
            </select>
          </label>
        </div>

        <div class="mt-4 flex gap-2 border-t border-gray-100 pt-4 text-xs">
          <button type="button" class="gcrm-btn-ghost text-xs" @click="rotate"><Icon icon="rotate" /> {{ t('settings.calendar.rotate') }}</button>
          <button type="button" class="gcrm-btn-danger text-xs" @click="disable">{{ t('settings.calendar.disable') }}</button>
        </div>
      </template>
      <button v-else type="button" class="gcrm-btn-primary" @click="rotate"><Icon icon="link" /> {{ t('settings.calendar.create') }}</button>
    </section>

    <!-- 2. Google account: live Google Calendar sync + Gmail history -->
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
      <h3 class="font-bold text-gray-800">{{ t('settings.google.title') }}</h3>
      <p class="mb-4 mt-1 text-xs text-gray-500">{{ t('settings.google.description') }}</p>

      <p v-if="!google.enabled" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">{{ t('settings.google.notConfigured') }}</p>

      <template v-else>
        <div class="mb-4 flex items-center gap-3">
          <span class="rounded border px-2 py-0.5 text-xs font-bold" :class="google.connected ? 'border-green-200 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-50 text-gray-500'">
            {{ google.connected ? t('settings.google.connected') : t('settings.google.notConnected') }}
          </span>
          <button v-if="!google.connected" type="button" class="gcrm-btn-primary" @click="connect">{{ t('settings.google.connect') }}</button>
          <button v-else type="button" class="gcrm-btn-ghost text-xs" @click="google.disconnect()">{{ t('settings.google.disconnect') }}</button>
        </div>
        <label class="mb-2 flex items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" class="mt-0.5 accent-primary" :checked="prefs.google?.calendar_sync" @change="save({ google: { calendar_sync: $event.target.checked } })" />
          <span>{{ t('settings.google.calendarSync') }}<span class="block text-xs text-gray-500">{{ t('settings.google.calendarSyncHint') }}</span></span>
        </label>
        <label class="flex items-start gap-2 text-sm text-gray-700">
          <input type="checkbox" class="mt-0.5 accent-primary" :checked="prefs.google?.email_history" @change="save({ google: { email_history: $event.target.checked } })" />
          <span>{{ t('settings.google.emailHistory') }}<span class="block text-xs text-gray-500">{{ t('settings.google.emailHistoryHint') }}</span></span>
        </label>
        <p v-if="google.lastError" class="mt-3 text-xs text-red-600">{{ google.lastError }}</p>
      </template>
    </section>
    <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useGoogleStore } from '@/crm/stores/google.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const settings = useSettingsStore()
const google = useGoogleStore()
const prefs = computed(() => settings.preferences ?? {})
const feedUrl = computed(() => prefs.value.calendar_feed_url)
const webcalUrl = computed(() => feedUrl.value?.replace(/^https?:\/\//, 'webcal://'))
const googleSubscribeUrl = computed(() => `https://calendar.google.com/calendar/render?cid=${encodeURIComponent(webcalUrl.value)}`)
const outlookSubscribeUrl = computed(
  () => `https://outlook.live.com/calendar/0/addfromweb?url=${encodeURIComponent(feedUrl.value)}&name=${encodeURIComponent('GastroFlowx')}`,
)
const copied = ref(false)
const error = ref('')

onMounted(() => google.checkStatus())

async function run(action) {
  error.value = ''
  try {
    await action()
  } catch (e) {
    error.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
  }
}

const save = (patch) => run(() => settings.savePreferences(patch))
const rotate = () => (feedUrl.value && !window.confirm(t('settings.calendar.confirmRotate')) ? null : run(() => settings.rotateCalendarFeed()))
const disable = () => window.confirm(t('settings.calendar.confirmDisable')) && run(() => settings.disableCalendarFeed())
const connect = () => run(() => google.connect())

async function copy() {
  try {
    await navigator.clipboard.writeText(feedUrl.value)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    window.prompt(t('settings.calendar.copy'), feedUrl.value)
  }
}
</script>
