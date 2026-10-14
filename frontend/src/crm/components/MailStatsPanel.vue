<template>
  <!-- Mailbox numbers of the Gmail account (Gmail API, computed in the browser). -->
  <div class="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between rounded-t-xl border-b border-gray-200 bg-gray-50/50 px-5 py-4">
      <h3 class="font-bold text-gray-800">{{ t('crm.mailStats.title') }}</h3>
      <button v-if="google.connected" type="button" class="text-xs text-gray-500 hover:text-primary disabled:opacity-60" :disabled="mail.loading" @click="google.refreshMail({ force: true })">
        <Icon icon="rotate" :class="{ 'animate-spin': mail.loading }" class="mr-1" />{{ t('crm.mailStats.refresh') }}
      </button>
    </div>

    <div class="flex-1 overflow-y-auto p-5">
      <div v-if="!google.enabled || google.connected === false" class="flex h-full flex-col items-center justify-center gap-3 text-center text-sm text-gray-500">
        <Icon :icon="['far', 'envelope']" class="text-2xl text-gray-300" />
        <p>{{ google.enabled ? t('crm.mailStats.connectHint') : t('crm.mailStats.unavailable') }}</p>
        <RouterLink v-if="google.enabled" :to="{ name: 'crm.settings', query: { tab: 'calendar' } }" class="gcrm-btn-secondary text-xs">{{ t('settings.google.connect') }}</RouterLink>
      </div>

      <div v-else-if="!mail.stats" class="space-y-2" aria-busy="true">
        <div v-for="i in 5" :key="i" class="h-7 animate-pulse rounded bg-gray-100" />
        <p v-if="mail.error" class="text-xs text-red-600">{{ mail.error }}</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="text-xs uppercase text-gray-500">
            <th class="pb-2 text-left font-semibold" />
            <th class="pb-2 text-right font-semibold">{{ t('crm.mailStats.today') }}</th>
            <th class="pb-2 text-right font-semibold">{{ t('crm.mailStats.month') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="row in rows" :key="row.key">
            <td class="py-2 text-gray-700"><span class="mr-2 inline-block h-2 w-2 rounded-full" :class="row.dot" />{{ t(`crm.mailStats.rows.${row.key}`) }}</td>
            <td class="py-2 text-right font-bold text-gray-900">{{ value(mail.stats.today, row.key) }}</td>
            <td class="py-2 text-right font-bold text-gray-900">{{ value(mail.stats.month, row.key) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="mail.stats" class="mt-3 text-[11px] text-gray-400">{{ t('crm.mailStats.note') }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { MAX_COUNT } from '@/crm/google/mail.js'
import { useGoogleStore } from '@/crm/stores/google.js'

const google = useGoogleStore()
const mail = computed(() => google.mail)

const rows = [
  { key: 'received', dot: 'bg-primary' },
  { key: 'unread', dot: 'bg-red-500' },
  { key: 'opened_no_reply', dot: 'bg-amber-400' },
  { key: 'replied', dot: 'bg-green-500' },
  { key: 'sent', dot: 'bg-sky-400' },
]

const value = (period, key) => (period?.capped && period[key] >= MAX_COUNT ? `${MAX_COUNT}+` : period?.[key] ?? '–')
</script>
