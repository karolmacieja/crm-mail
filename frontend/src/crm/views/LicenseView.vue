<template>
  <div class="mx-auto max-w-lg p-8">
    <!-- When the license becomes valid the router guard redirects away from here. -->
    <LicenseNotice />
  </div>
</template>

<script setup>
import LicenseNotice from '@/crm/components/LicenseNotice.vue'
</script>
