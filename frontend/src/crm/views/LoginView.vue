<template>
  <div class="flex min-h-full items-center justify-center bg-gray-50 p-8">
    <div class="gcrm-card w-full max-w-sm p-6 shadow-sm">
      <LoginForm />
    </div>
  </div>
</template>

<script setup>
// The guard leaves this page automatically once the auth store has a session.
import LoginForm from '@/crm/components/LoginForm.vue'
</script>
