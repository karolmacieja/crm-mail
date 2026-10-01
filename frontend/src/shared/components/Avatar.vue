<template>
  <div
    class="flex h-10 w-10 shrink-0 select-none items-center justify-center rounded-full text-sm font-semibold"
    :class="muted ? 'bg-gray-100 text-gray-500' : 'bg-indigo-100 text-indigo-700'"
    aria-hidden="true"
  >
    {{ initials(name, email) }}
  </div>
</template>

<script setup>
import { initials } from '@/shared/lib/format.js'

defineProps({
  name: { type: String, default: '' },
  email: { type: String, default: '' },
  muted: { type: Boolean, default: false },
})
</script>
