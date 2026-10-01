<template>
  <span v-if="category" class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="tone">
    {{ category.name }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ category: { type: Object, default: null } })

// Static class names so Tailwind keeps them (category.color comes from the API).
const TONES = {
  blue: 'bg-blue-100 text-blue-700',
  purple: 'bg-purple-100 text-purple-700',
  green: 'bg-green-100 text-green-700',
  orange: 'bg-orange-100 text-orange-700',
  red: 'bg-red-100 text-red-700',
  yellow: 'bg-yellow-100 text-yellow-800',
  gray: 'bg-gray-100 text-gray-700',
}
const tone = computed(() => TONES[props.category?.color] ?? TONES.gray)
</script>
