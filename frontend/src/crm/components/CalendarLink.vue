<template>
  <!-- Synced into my Google Calendar → green check; otherwise a one-click "add to Google Calendar" link. -->
  <span v-if="item.calendar_event_id" class="shrink-0 text-green-600" :title="t('crm.calendar.synced')">
    <Icon icon="calendar-check" />
  </span>
  <a
    v-else
    :href="googleCalendarTemplateUrl(kind, item)"
    target="_blank"
    rel="noopener"
    class="shrink-0 text-gray-300 hover:text-primary"
    :title="t('crm.calendar.addToGoogle')"
    @click.stop
  >
    <Icon icon="calendar-plus" />
  </a>
</template>

<script setup>
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { googleCalendarTemplateUrl } from '@/crm/google/calendarSync.js'

defineProps({
  kind: { type: String, required: true }, // 'task' | 'reminder'
  item: { type: Object, required: true },
})
</script>
