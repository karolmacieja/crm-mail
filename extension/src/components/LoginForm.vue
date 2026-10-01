<template>
  <form class="space-y-3" novalidate @submit.prevent="submit">
    <div>
      <h2 class="text-base font-semibold text-gray-900">{{ t('login.title') }}</h2>
      <p class="mt-0.5 text-xs text-gray-500">{{ t('login.subtitle') }}</p>
    </div>

    <AlertMessage v-if="error && !Object.keys(fieldErrors).length" :message="error" />

    <div>
      <label class="gcrm-label" :for="`${uid}-email`">{{ t('login.email') }}</label>
      <input
        :id="`${uid}-email`"
        v-model.trim="email"
        type="email"
        autocomplete="username"
        required
        class="gcrm-input"
        :class="{ 'gcrm-input-error': fieldErrors.email }"
        :disabled="loading"
      />
      <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
    </div>

    <div>
      <label class="gcrm-label" :for="`${uid}-password`">{{ t('login.password') }}</label>
      <input
        :id="`${uid}-password`"
        v-model="password"
        type="password"
        autocomplete="current-password"
        required
        class="gcrm-input"
        :class="{ 'gcrm-input-error': fieldErrors.password }"
        :disabled="loading"
      />
      <p v-if="fieldErrors.password" class="mt-1 text-xs text-red-600">{{ fieldErrors.password }}</p>
    </div>

    <button type="submit" class="gcrm-btn-primary w-full" :disabled="loading || !email || !password">
      <Spinner v-if="loading" size="xs" />
      {{ loading ? t('login.submitting') : t('login.submit') }}
    </button>
  </form>
</template>

<script setup>
import { ref, useId } from 'vue'
import { t } from '@/lib/i18n.js'
import { useAuthStore } from '@/stores/auth.js'
import AlertMessage from './AlertMessage.vue'
import Spinner from './Spinner.vue'

const emit = defineEmits(['success'])
const auth = useAuthStore()
const uid = useId()

const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const fieldErrors = ref({})

async function submit() {
  loading.value = true
  error.value = ''
  fieldErrors.value = {}

  try {
    const user = await auth.login({ email: email.value, password: password.value })
    password.value = ''
    emit('success', user)
  } catch (apiError) {
    error.value = apiError.message
    fieldErrors.value = apiError.fieldErrors ?? {}
  } finally {
    loading.value = false
  }
}
</script>
