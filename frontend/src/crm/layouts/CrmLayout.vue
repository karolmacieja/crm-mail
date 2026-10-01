<template>
  <!-- Minimal shell for step 3; step 4 turns it into the mockup's layout with ContextSidebar. -->
  <div class="flex min-h-full bg-gray-50 text-gray-800">
    <aside class="w-56 shrink-0 bg-slate-900 p-4 text-slate-300">
      <p class="mb-6 text-lg font-bold text-white">GastroFlowx</p>
      <nav class="space-y-1">
        <RouterLink
          v-for="item in nav"
          :key="item.name"
          :to="{ name: item.name }"
          class="block rounded-lg px-3 py-2 hover:bg-slate-800 hover:text-white"
          active-class="bg-indigo-600/20 text-white"
        >
          {{ t(item.label) }}
        </RouterLink>
      </nav>
      <div class="mt-8 border-t border-slate-800 pt-4 text-sm">
        <p class="text-white">{{ auth.user?.name }}</p>
        <p class="text-xs text-slate-400">{{ auth.user?.group?.name }}</p>
        <button type="button" class="mt-2 text-slate-400 hover:text-white" @click="auth.logout()">{{ t('common.signOut') }}</button>
      </div>
    </aside>
    <main class="flex-1"><RouterView /></main>
  </div>
</template>

<script setup>
import { t } from '@/shared/lib/i18n.js'
import { useAuthStore } from '@/crm/stores/auth.js'

const auth = useAuthStore()

const nav = [
  { name: 'crm.dashboard', label: 'nav.dashboard' },
  { name: 'crm.clients', label: 'nav.clients' },
  { name: 'crm.contacts', label: 'nav.contacts' },
  { name: 'crm.tasks', label: 'nav.tasks' },
  { name: 'crm.reminders', label: 'nav.reminders' },
]
</script>
