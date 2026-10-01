<template>
  <!-- Temporary route target (step 3). Step 4 replaces every route with the mockup's components. -->
  <section class="p-8">
    <h1 class="text-xl font-bold text-gray-800">{{ t($route.meta.title ?? 'common.loading') }}</h1>
    <p class="mt-2 text-sm text-gray-500">
      <code>{{ $route.name }}</code>
      <span v-if="Object.keys($route.params).length"> · {{ $route.params }}</span>
    </p>
  </section>
</template>

<script setup>
import { t } from '@/shared/lib/i18n.js'
</script>
