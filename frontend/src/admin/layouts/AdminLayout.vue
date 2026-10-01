<template>
  <!-- Minimal shell for step 3; step 4 turns it into the mockup's dark sidebar layout. -->
  <div class="flex min-h-screen bg-gray-50 text-gray-800">
    <aside class="w-64 shrink-0 bg-slate-900 p-4 text-slate-300">
      <p class="mb-6 text-lg font-bold text-white">GastroFlowx</p>
      <nav class="space-y-1">
        <RouterLink
          v-for="item in nav"
          :key="item.name"
          :to="{ name: item.name }"
          class="block rounded-lg px-3 py-2 hover:bg-slate-800 hover:text-white"
          exact-active-class="bg-indigo-600/20 text-white"
        >
          {{ t(item.label) }}
        </RouterLink>
      </nav>
      <div class="mt-8 border-t border-slate-800 pt-4 text-sm">
        <p class="text-white">{{ auth.user?.name }}</p>
        <button type="button" class="mt-2 text-slate-400 hover:text-white" @click="logout">{{ t('common.signOut') }}</button>
        <div class="mt-3"><LanguageSwitch /></div>
      </div>
    </aside>
    <main class="flex-1"><RouterView /></main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import { t } from '@/shared/lib/i18n.js'
import { useAdminAuthStore } from '@/admin/stores/auth.js'

const auth = useAdminAuthStore()
const router = useRouter()

const nav = [
  { name: 'admin.dashboard', label: 'nav.adminDashboard' },
  { name: 'admin.groups', label: 'nav.groups' },
  { name: 'admin.users', label: 'nav.users' },
  { name: 'admin.licenses', label: 'nav.licenses' },
]

async function logout() {
  await auth.logout()
  router.replace({ name: 'admin.login' })
}
</script>
