<template>
  <section>
    <div class="mb-6 flex items-center justify-between">
      <h3 class="text-sm font-bold uppercase tracking-wide text-gray-800">
        <Icon icon="clock-rotate-left" class="mr-2" />{{ t('crm.timeline.title') }}
      </h3>
      <div class="flex items-center gap-2">
      <button
        v-if="google.enabled"
        type="button"
        class="rounded border border-gray-200 px-2 py-1 text-xs text-gray-500 hover:text-primary disabled:opacity-60"
        :title="historyTitle"
        :disabled="historyState?.status === 'running'"
        @click="client.importHistory()"
      >
        <Icon :icon="historyState?.status === 'running' ? 'rotate' : 'cloud-arrow-down'" :class="{ 'animate-spin': historyState?.status === 'running' }" class="mr-1" />
        {{ historyState?.status === 'running' ? historyProgress : t('crm.history.import') }}
      </button>
      <label class="flex items-center rounded border border-gray-200 px-2 py-1 text-xs text-gray-500 hover:text-primary">
        <Icon icon="filter" class="mr-1" />
        <select class="bg-transparent focus:outline-none" :value="timeline.type" :aria-label="t('crm.timeline.filter')" @change="client.loadTimeline({ type: $event.target.value })">
          <option value="">{{ t('crm.timeline.all') }}</option>
          <option value="email">{{ t('crm.timeline.emails') }}</option>
          <option value="note">{{ t('crm.timeline.notes') }}</option>
          <option value="system">{{ t('crm.timeline.system') }}</option>
        </select>
      </label>
      </div>
    </div>
    <p v-if="historyState?.status === 'done' && historyState.result" class="-mt-4 mb-4 text-xs text-gray-500">
      {{ t('crm.history.done', { count: historyState.result.imported + historyState.result.updated }) }}
    </p>
    <p v-else-if="historyState?.status === 'error'" class="-mt-4 mb-4 text-xs text-red-600">{{ t('crm.history.error') }}: {{ historyState.error }}</p>

    <div v-if="timeline.items.length" class="relative ml-3 space-y-6 border-l-2 border-gray-200">
      <div v-for="item in timeline.items" :key="item.id" class="group relative pl-6">
        <div class="absolute -left-[7px] top-1 h-3 w-3 rounded-full ring-4 ring-white" :class="dotClass(item)" />
        <p class="mb-0.5 text-xs text-gray-500">
          {{ formatCalendar(item.occurred_at) }}
          <span class="ml-2 font-bold" :class="labelClass(item)">{{ label(item) }}</span>
          <button
            v-if="item.type === 'note' && item.author?.id === auth.user?.id"
            type="button"
            class="ml-2 hidden text-gray-300 hover:text-red-600 group-hover:inline"
            :aria-label="t('common.delete')"
            @click="removeNote(item)"
          >
            <Icon icon="trash" />
          </button>
        </p>
        <div class="rounded-lg border p-3 text-sm shadow-sm" :class="item.type === 'note' ? 'border-yellow-100 bg-yellow-50' : 'border-gray-200 bg-white'">
          <template v-if="item.type === 'email'">
            <p class="mb-1 font-bold text-gray-800">
              <Icon :icon="item.meta?.direction === 'out' ? 'reply' : ['far', 'envelope']" class="mr-1 text-gray-400" />
              {{ item.title || t('crm.timeline.noSubject') }}
            </p>
            <p v-if="item.body" class="text-gray-600">{{ item.body }}</p>
          </template>
          <p v-else-if="item.type === 'note'" class="whitespace-pre-wrap text-yellow-800">{{ item.body }}</p>
          <p v-else class="text-gray-700">{{ systemText(item) }}</p>
        </div>
      </div>
    </div>

    <p v-else-if="!timeline.loading" class="text-xs text-gray-500">{{ t('crm.timeline.empty') }}</p>
    <p v-if="timeline.error" class="mt-2 text-xs text-red-600">{{ timeline.error.message }}</p>

    <div class="mt-4 text-center">
      <Spinner v-if="timeline.loading" class="inline text-gray-400" />
      <button v-else-if="client.hasMoreTimeline" type="button" class="gcrm-btn-secondary text-xs" @click="client.loadTimeline()">
        {{ t('crm.timeline.more') }}
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import Spinner from '@/shared/components/Spinner.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar, formatReservation } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { useAuthStore } from '@/crm/stores/auth.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useGoogleStore } from '@/crm/stores/google.js'

const client = useClientStore()
const auth = useAuthStore()
const timeline = client.timeline

const dotClass = (item) => ({ note: 'bg-yellow-400', email: 'bg-primary', system: 'bg-gray-400' })[item.type]
const labelClass = (item) => ({ note: 'text-yellow-600', email: 'text-primary', system: 'text-gray-500' })[item.type]

function label(item) {
  if (item.type === 'note') {
    return item.author?.id === auth.user?.id ? t('crm.timeline.yourNote') : t('crm.timeline.noteBy', { name: item.author?.name ?? '—' })
  }
  if (item.type === 'email') return item.meta?.direction === 'out' ? t('crm.timeline.emailOut') : t('crm.timeline.emailIn')
  return t('crm.timeline.systemLabel')
}

function systemText(item) {
  const meta = item.meta ?? {}
  switch (item.event) {
    case 'reservation.created':
      return t('crm.timeline.events.reservationCreated', { when: formatReservation(meta.date, meta.time), count: meta.guests })
    case 'reservation.status_changed':
      return t('crm.timeline.events.reservationStatus', {
        when: formatReservation(meta.date, meta.time),
        status: t(`crm.reservations.statuses.${meta.to}`),
      })
    case 'task.completed':
      return t('crm.timeline.events.taskCompleted', { title: meta.title ?? '' })
    default:
      return item.event ?? ''
  }
}

async function removeNote(item) {
  if (window.confirm(t('crm.timeline.confirmDeleteNote'))) await client.deleteNote(item.id)
}

const google = useGoogleStore()
const historyState = computed(() => google.history[client.contact?.id] ?? null)
const historyProgress = computed(() => {
  const p = historyState.value?.progress
  return p?.phase === 'fetch' ? t('crm.history.fetching', { done: p.done, total: p.total }) : t('crm.history.saving')
})
const historyTitle = computed(() =>
  client.contact?.email_history_synced_at
    ? t('crm.history.lastSync', { when: formatCalendar(client.contact.email_history_synced_at) })
    : t('crm.history.never'),
)
</script>
