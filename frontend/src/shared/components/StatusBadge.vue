<template>
  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="meta.classes">
    {{ t(`status.${meta.value}`) }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { statusMeta } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'

const props = defineProps({ status: { type: String, required: true } })
const meta = computed(() => statusMeta(props.status))
</script>
