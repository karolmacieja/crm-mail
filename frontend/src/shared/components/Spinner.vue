<template>
  <svg class="animate-spin text-current" :class="sizeClass" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
  </svg>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ size: { type: String, default: 'sm' } })
const sizeClass = computed(() => ({ xs: 'h-3 w-3', sm: 'h-4 w-4', md: 'h-6 w-6', lg: 'h-8 w-8' })[props.size] ?? 'h-4 w-4')
</script>
