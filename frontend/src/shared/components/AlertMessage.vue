<template>
  <div class="flex items-start gap-2 rounded-md px-3 py-2 text-sm" :class="tone" role="alert">
    <span class="flex-1">
      <slot>{{ message }}</slot>
    </span>
    <button v-if="retryable" type="button" class="shrink-0 font-medium underline underline-offset-2" @click="$emit('retry')">
      {{ t('common.retry') }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@/shared/lib/i18n.js'

const props = defineProps({
  message: { type: String, default: '' },
  variant: { type: String, default: 'error' },
  retryable: { type: Boolean, default: false },
})
defineEmits(['retry'])

const tone = computed(
  () =>
    ({
      error: 'bg-red-50 text-red-700',
      warning: 'bg-amber-50 text-amber-800',
      info: 'bg-indigo-50 text-indigo-700',
    })[props.variant],
)
</script>
