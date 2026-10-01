<template>
  <div class="gcrm-root w-80 bg-white p-4">
    <div v-if="!auth.ready" class="flex items-center gap-2 py-6 text-gray-500"><Spinner /> {{ t('common.loading') }}</div>

    <div class="mb-3 flex justify-end">
      <LanguageSwitch />
    </div>

    <LoginForm v-if="auth.ready && !auth.isAuthenticated" />

    <div v-else-if="auth.ready" class="space-y-4">
      <div class="flex items-center gap-3">
        <Avatar :name="auth.user?.name" :email="auth.user?.email" />
        <div class="min-w-0">
          <p class="truncate font-semibold text-gray-900">{{ auth.user?.name }}</p>
          <p class="truncate text-xs text-gray-500">{{ auth.user?.email }}</p>
        </div>
      </div>

      <div class="rounded-lg border p-3 text-sm" :class="auth.hasValidLicense ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
        <p class="font-medium" :class="auth.hasValidLicense ? 'text-emerald-800' : 'text-amber-800'">
          {{ auth.hasValidLicense ? t('license.active') : t('license.inactive') }}
        </p>
        <p v-if="auth.license?.expires_at" class="mt-0.5 text-xs text-gray-600">
          {{ t(auth.hasValidLicense ? 'license.validUntil' : 'license.expiredOnShort', { date: formatDateTime(auth.license.expires_at) }) }}
        </p>
        <p v-if="auth.license?.key" class="mt-0.5 font-mono text-xs text-gray-500">{{ auth.license.key }}</p>
      </div>

      <div class="flex gap-2">
        <a class="gcrm-btn-primary flex-1" href="https://mail.google.com/" target="_blank" rel="noopener">{{ t('popup.openGmail') }}</a>
        <button type="button" class="gcrm-btn-secondary" @click="auth.logout()">{{ t('common.signOut') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatDateTime } from '@/lib/format.js'
import { t } from '@/lib/i18n.js'
import { useCrmSession } from '@/lib/useCrmSession.js'
import Avatar from '@/components/Avatar.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import LoginForm from '@/components/LoginForm.vue'
import Spinner from '@/components/Spinner.vue'

// auth.init() (via useCrmSession) also refreshes account + license status from the server.
const { auth } = useCrmSession()
</script>
