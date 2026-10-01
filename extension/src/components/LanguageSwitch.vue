<template>
  <div class="inline-flex rounded-md border border-gray-200 bg-white p-0.5 text-xs" role="group" :aria-label="t('common.language')">
    <button
      v-for="option in SUPPORTED_LOCALES"
      :key="option.code"
      type="button"
      class="rounded px-2 py-0.5 font-medium transition"
      :class="locale === option.code ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
      :title="option.name"
      :aria-pressed="locale === option.code"
      @click="setLocale(option.code)"
    >
      {{ option.label }}
    </button>
  </div>
</template>

<script setup>
import { SUPPORTED_LOCALES, locale, setLocale, t } from '@/lib/i18n.js'
</script>
