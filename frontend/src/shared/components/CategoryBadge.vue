<template>
  <span v-if="category" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="tone(category.color).badge">
    {{ category.name }}
  </span>
</template>

<script setup>
import { tone } from '@/shared/lib/colors.js'

defineProps({ category: { type: Object, default: null } })
</script>
