<template>
  <div class="gcrm-card p-4">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ label }}</p>
    <div v-if="loading" class="mt-2 h-7 w-12 animate-pulse rounded bg-gray-100" />
    <p v-else class="mt-1 text-2xl font-semibold" :class="toneClass">{{ value ?? 0 }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: Number, default: null },
  loading: { type: Boolean, default: false },
  tone: { type: String, default: 'default' },
})

const toneClass = computed(() => ({ danger: 'text-red-600', warning: 'text-amber-600' })[props.tone] ?? 'text-gray-900')
</script>
