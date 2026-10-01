<template>
  <div class="relative flex h-full min-h-[600px] overflow-hidden bg-gray-50 font-sans text-gray-800 antialiased">
    <!-- LEFT SIDEBAR – main navigation -->
    <aside class="z-30 flex w-64 shrink-0 flex-col justify-between bg-sidebar text-slate-300 shadow-xl">
      <div>
        <div class="flex h-16 items-center border-b border-slate-800/50 bg-slate-900 px-6">
          <Icon icon="layer-group" class="mr-3 text-xl text-primary" />
          <span class="text-lg font-bold tracking-wide text-white">GastroFlowx</span>
        </div>

        <nav class="space-y-1 px-3 py-6">
          <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('crm.layout.operations') }}</p>
          <RouterLink
            v-for="item in nav"
            :key="item.name"
            v-slot="{ href, navigate, isActive }"
            :to="{ name: item.name }"
            custom
          >
            <a
              :href="href"
              class="group flex w-full items-center rounded-lg px-3 py-2.5 transition-colors"
              :class="isActive || isSection(item) ? 'bg-primary/20 text-white' : 'hover:bg-slate-800 hover:text-white'"
              @click="navigate"
            >
              <Icon :icon="item.icon" class="w-6 text-left" :class="isActive || isSection(item) ? 'text-primary' : 'group-hover:text-primary'" />
              <span :class="{ 'font-medium': isActive }">{{ t(item.label) }}</span>
              <span v-if="item.badge?.()" class="ml-auto rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white">{{ item.badge() }}</span>
            </a>
          </RouterLink>
        </nav>
      </div>

      <div class="border-t border-slate-800/50 bg-slate-900 p-4">
        <div class="flex items-center">
          <Avatar :name="auth.user?.name" :email="auth.user?.email" size="sm" class="h-9 w-9" />
          <div class="ml-3 min-w-0 text-left">
            <p class="truncate text-sm font-medium text-white">{{ auth.user?.name }}</p>
            <p class="truncate text-xs text-slate-400">{{ auth.user?.group?.name }}</p>
          </div>
          <button type="button" class="ml-auto text-slate-500 hover:text-white" :title="t('common.signOut')" @click="auth.logout()">
            <Icon icon="right-from-bracket" />
          </button>
        </div>
        <div class="mt-3"><LanguageSwitch /></div>
      </div>
    </aside>

    <!-- CENTER – workspace -->
    <main class="relative flex h-full flex-1 flex-col overflow-y-auto bg-gray-50/50">
      <header class="sticky top-0 z-10 flex h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white px-8">
        <h1 class="text-xl font-bold text-gray-800">{{ t($route.meta.title ?? 'nav.dashboard') }}</h1>
        <div class="flex items-center space-x-4">
          <GlobalSearch />
          <button type="button" class="relative p-2 text-gray-400 transition-colors hover:text-primary" :title="t('crm.layout.inbox')" @click="gmail?.goInbox?.()">
            <Icon icon="envelope" class="text-lg" />
            <span v-if="dashboard.summary?.emails.today" class="absolute right-1 top-1 h-2 w-2 rounded-full bg-red-500" />
          </button>
        </div>
      </header>

      <div class="mx-auto w-full max-w-7xl p-8">
        <RouterView />
      </div>
    </main>

    <!-- RIGHT SIDEBAR – client context (slides in) -->
    <ContextSidebar />
  </div>
</template>

<script setup>
import { inject, watch } from 'vue'
import { useRoute } from 'vue-router'
import Avatar from '@/shared/components/Avatar.vue'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import ContextSidebar from '@/crm/components/ContextSidebar.vue'
import GlobalSearch from '@/crm/components/GlobalSearch.vue'
import { useAuthStore } from '@/crm/stores/auth.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useDashboardStore } from '@/crm/stores/dashboard.js'

const auth = useAuthStore()
const dashboard = useDashboardStore()
const client = useClientStore()
const route = useRoute()
const gmail = inject('gmail', null)

const nav = [
  { name: 'crm.dashboard', label: 'nav.dashboard', icon: 'chart-pie' },
  { name: 'crm.clients', label: 'nav.clients', icon: 'users' },
  { name: 'crm.contacts', label: 'nav.contacts', icon: 'address-book' },
  { name: 'crm.tasks', label: 'nav.tasks', icon: 'list-check', badge: () => dashboard.summary?.tasks.overdue || null },
  { name: 'crm.reminders', label: 'nav.reminders', icon: ['far', 'bell'], badge: () => dashboard.summary?.reminders.overdue || null },
]

// "Klienci" stays highlighted on a client's full profile.
const isSection = (item) => item.name === 'crm.clients' && route.name === 'crm.clients.show'

// Switching tabs closes the right panel (as in the mockup).
watch(() => route.name, () => client.closeSidebar())
</script>
