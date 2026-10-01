<template>
  <!-- Contact status as configured in Settings → Statuses (falls back to the raw key). -->
  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="tone(meta?.color).badge">
    {{ meta?.name ?? status }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { tone } from '@/shared/lib/colors.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const props = defineProps({ status: { type: String, required: true } })
const settings = useSettingsStore()
const meta = computed(() => settings.statusByKey(props.status))
</script>
