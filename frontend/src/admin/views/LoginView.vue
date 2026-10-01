<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-900 px-4">
    <form class="w-full max-w-sm space-y-4 rounded-xl bg-white p-6 shadow-xl" novalidate @submit.prevent="submit">
      <div class="flex items-center justify-between">
        <h1 class="text-lg font-bold text-gray-900">{{ t('login.adminTitle') }}</h1>
        <LanguageSwitch />
      </div>

      <AlertMessage v-if="error && !Object.keys(fieldErrors).length" :message="error" />

      <div>
        <label class="gcrm-label" for="admin-email">{{ t('login.email') }}</label>
        <input id="admin-email" v-model.trim="email" type="email" autocomplete="username" class="gcrm-input" :class="{ 'gcrm-input-error': fieldErrors.email }" />
        <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
      </div>
      <div>
        <label class="gcrm-label" for="admin-password">{{ t('login.password') }}</label>
        <input id="admin-password" v-model="password" type="password" autocomplete="current-password" class="gcrm-input" />
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-600">
        <input v-model="remember" type="checkbox" class="accent-indigo-600" /> {{ t('login.remember') }}
      </label>

      <button type="submit" class="gcrm-btn-primary w-full" :disabled="loading || !email || !password">
        <Spinner v-if="loading" size="xs" />
        {{ loading ? t('login.submitting') : t('login.submit') }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import Spinner from '@/shared/components/Spinner.vue'
import { t } from '@/shared/lib/i18n.js'
import { redirectTarget } from '@/shared/router/guards.js'
import { useAdminAuthStore } from '@/admin/stores/auth.js'

const auth = useAdminAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const error = ref('')
const fieldErrors = ref({})

async function submit() {
  loading.value = true
  error.value = ''
  fieldErrors.value = {}
  try {
    await auth.login({ email: email.value, password: password.value, remember: remember.value })
    password.value = ''
    await router.replace(redirectTarget(route, '/'))
  } catch (apiError) {
    error.value = apiError.message
    fieldErrors.value = apiError.fieldErrors ?? {}
  } finally {
    loading.value = false
  }
}
</script>
