<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[2000] flex items-start justify-center overflow-y-auto bg-slate-900/30 p-4 backdrop-blur-sm sm:items-center"
    role="dialog"
    aria-modal="true"
    :aria-label="title"
    @mousedown.self="$emit('close')"
    @keydown.esc="$emit('close')"
  >
    <div class="w-full rounded-xl bg-white shadow-2xl" :class="sizeClass" tabindex="-1">
      <header class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
        <h2 class="text-base font-bold text-gray-900">{{ title }}</h2>
        <button type="button" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700" :aria-label="t('common.close')" @click="$emit('close')">
          <Icon icon="xmark" />
        </button>
      </header>
      <div class="px-5 py-4"><slot /></div>
      <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50/60 px-5 py-3">
        <slot name="footer" />
      </footer>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, required: true },
  size: { type: String, default: 'md' },
})
defineEmits(['close'])

const sizeClass = computed(() => ({ sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' })[props.size] ?? 'max-w-lg')
</script>
