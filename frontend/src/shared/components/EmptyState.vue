<template>
  <div class="flex flex-col items-center justify-center px-6 py-10 text-center text-gray-500">
    <Icon v-if="icon" :icon="icon" class="mb-3 text-3xl text-gray-300" />
    <p class="text-sm">{{ text }}</p>
    <div v-if="$slots.default" class="mt-3"><slot /></div>
  </div>
</template>

<script setup>
import { Icon } from '@/shared/icons.js'

defineProps({
  icon: { type: [String, Array], default: null },
  text: { type: String, required: true },
})
</script>
