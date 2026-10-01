<template>
  <span class="inline-block rounded border px-2 py-0.5 text-xs font-bold" :class="LICENSE_STATE_CLASSES[state] ?? LICENSE_STATE_CLASSES.missing">
    {{ t(`admin.licenseStates.${state}`) }}
  </span>
</template>

<script setup>
import { t } from '@/shared/lib/i18n.js'
import { LICENSE_STATE_CLASSES } from '@/admin/lib/badges.js'

defineProps({ state: { type: String, default: 'missing' } })
</script>
