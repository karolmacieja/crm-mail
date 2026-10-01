<template>
  <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
    <p class="font-semibold">{{ title }}</p>
    <p class="mt-1 text-amber-800">{{ message }}</p>
    <p v-if="auth.user" class="mt-1 text-xs text-amber-700">{{ t('license.signedInAs', { email: auth.user.email }) }}</p>
    <div class="mt-3 flex flex-wrap gap-2">
      <button type="button" class="gcrm-btn-secondary" :disabled="checking" @click="recheck">
        <Spinner v-if="checking" size="xs" />
        {{ t('license.recheck') }}
      </button>
      <button type="button" class="gcrm-btn-ghost" @click="auth.logout()">{{ t('common.signOut') }}</button>
    </div>
    <p v-if="checkError" class="mt-2 text-xs text-red-700">{{ checkError }}</p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '@/crm/stores/auth.js'
import { formatDateTime } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import Spinner from '@/shared/components/Spinner.vue'

const emit = defineEmits(['renewed'])
const auth = useAuthStore()
const checking = ref(false)
const checkError = ref('')

const status = computed(() => auth.licenseError?.code ?? auth.license?.status)
const isExpired = computed(() => status.value === 'license_expired' || status.value === 'expired')

const title = computed(() => (isExpired.value ? t('license.expiredTitle') : t('license.missingTitle')))

const message = computed(() => {
  if (!isExpired.value) return t('license.missingBody')
  const expiresAt = auth.license?.expires_at
  // A stale cached expiry in the future means the server said "expired" more recently.
  return expiresAt && Date.parse(expiresAt) <= Date.now()
    ? t('license.expiredOn', { date: formatDateTime(expiresAt) })
    : t('license.expiredBody')
})

async function recheck() {
  checking.value = true
  checkError.value = ''
  try {
    const user = await auth.refreshUser()
    if (user?.license?.is_valid) emit('renewed')
    else checkError.value = t('license.stillInactive')
  } catch (error) {
    checkError.value = error.message
  } finally {
    checking.value = false
  }
}
</script>
