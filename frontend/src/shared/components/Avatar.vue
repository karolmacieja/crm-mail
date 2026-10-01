<template>
  <div
    class="flex shrink-0 select-none items-center justify-center rounded-full font-bold"
    :class="[sizeClass, muted ? 'bg-gray-100 text-gray-500' : gradient ? 'bg-gradient-to-tr from-primary to-blue-400 text-white shadow-md' : 'bg-primary text-white']"
    aria-hidden="true"
  >
    {{ initials(name, email) }}
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { initials } from '@/shared/lib/format.js'

const props = defineProps({
  name: { type: String, default: '' },
  email: { type: String, default: '' },
  muted: { type: Boolean, default: false },
  gradient: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
})

const sizeClass = computed(() => ({ sm: 'h-8 w-8 text-xs', md: 'h-10 w-10 text-sm', lg: 'h-14 w-14 text-xl' })[props.size] ?? 'h-10 w-10 text-sm')
</script>
