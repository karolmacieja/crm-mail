<template>
  <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
    <p class="font-semibold">{{ title }}</p>
    <p class="mt-1 text-amber-800">{{ message }}</p>
    <p v-if="auth.user" class="mt-1 text-xs text-amber-700">Signed in as {{ auth.user.email }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" class="gcrm-btn-secondary" :disabled="checking" @click="recheck">
        <Spinner v-if="checking" size="xs" />
        I've renewed – check again
      </button>
      <button type="button" class="gcrm-btn-ghost" @click="auth.logout()">Sign out</button>
    </div>
    <p v-if="checkError" class="mt-2 text-xs text-red-700">{{ checkError }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '@/stores/auth.js'
import { formatDateTime } from '@/lib/format.js'
import Spinner from './Spinner.vue'

const emit = defineEmits(['renewed'])
const auth = useAuthStore()
const checking = ref(false)
const checkError = ref('')

const status = computed(() => auth.licenseError?.code ?? auth.license?.status)

const title = computed(() => (status.value === 'license_expired' || status.value === 'expired' ? 'Your license has expired' : 'No active license'))

const message = computed(() => {
  if (auth.licenseError?.message) return auth.licenseError.message
  if (auth.license?.expires_at) return `Your license expired on ${formatDateTime(auth.license.expires_at)}. Renew it to keep using the CRM.`
  return 'This account does not have an active license yet. Contact your administrator or purchase a plan.'
})

async function recheck() {
  checking.value = true
  checkError.value = ''
  try {
    const user = await auth.refreshUser()
    if (user?.license?.is_valid) emit('renewed')
    else checkError.value = 'Your license is still inactive.'
  } catch (error) {
    checkError.value = error.message
  } finally {
    checking.value = false
  }
}
</script>
