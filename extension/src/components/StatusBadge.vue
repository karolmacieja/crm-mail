<template>
  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="meta.classes">
    {{ meta.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { statusMeta } from '@/lib/format.js'

const props = defineProps({ status: { type: String, required: true } })
const meta = computed(() => statusMeta(props.status))
</script>
