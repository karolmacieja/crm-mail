<template>
  <div class="flex h-screen overflow-hidden bg-gray-50 font-sans text-gray-800 antialiased">
    <aside class="z-30 flex w-64 shrink-0 flex-col justify-between bg-sidebar text-slate-300 shadow-xl">
      <div>
        <div class="flex h-16 items-center border-b border-slate-800/50 bg-slate-900 px-6">
          <Icon icon="layer-group" class="mr-3 text-xl text-primary" />
          <span class="text-lg font-bold tracking-wide text-white">GastroFlowx</span>
        </div>
        <nav class="space-y-1 px-3 py-6">
          <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ t('admin.layout.section') }}</p>
          <RouterLink v-for="item in nav" :key="item.name" v-slot="{ href, navigate, isActive, isExactActive }" :to="{ name: item.name }" custom>
            <a
              :href="href"
              class="group flex w-full items-center rounded-lg px-3 py-2.5 transition-colors"
              :class="(item.exact ? isExactActive : isActive) ? 'bg-primary/20 text-white' : 'hover:bg-slate-800 hover:text-white'"
              @click="navigate"
            >
              <Icon :icon="item.icon" class="w-6 text-left" :class="(item.exact ? isExactActive : isActive) ? 'text-primary' : 'group-hover:text-primary'" />
              <span>{{ t(item.label) }}</span>
            </a>
          </RouterLink>
        </nav>
      </div>

      <div class="border-t border-slate-800/50 bg-slate-900 p-4">
        <div class="flex items-center">
          <Avatar :name="auth.user?.name" :email="auth.user?.email" size="sm" class="h-9 w-9" />
          <div class="ml-3 min-w-0 text-left">
            <p class="truncate text-sm font-medium text-white">{{ auth.user?.name }}</p>
            <p class="text-xs text-slate-400">Master Admin</p>
          </div>
          <button type="button" class="ml-auto text-slate-500 hover:text-white" :title="t('common.signOut')" @click="logout">
            <Icon icon="right-from-bracket" />
          </button>
        </div>
        <div class="mt-3"><LanguageSwitch /></div>
      </div>
    </aside>

    <main class="relative flex h-screen flex-1 flex-col overflow-y-auto bg-gray-50/50">
      <header class="sticky top-0 z-10 flex h-16 shrink-0 items-center border-b border-gray-200 bg-white px-8">
        <h1 class="text-xl font-bold text-gray-800">{{ t($route.meta.title ?? 'nav.adminDashboard') }}</h1>
      </header>
      <div class="mx-auto w-full max-w-7xl p-8"><RouterView /></div>
    </main>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import Avatar from '@/shared/components/Avatar.vue'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useAdminAuthStore } from '@/admin/stores/auth.js'

const auth = useAdminAuthStore()
const router = useRouter()

const nav = [
  { name: 'admin.dashboard', label: 'nav.adminDashboard', icon: 'chart-pie', exact: true },
  { name: 'admin.groups', label: 'nav.groups', icon: 'building' },
  { name: 'admin.users', label: 'nav.users', icon: 'user-tie' },
  { name: 'admin.licenses', label: 'nav.licenses', icon: 'key' },
]

async function logout() {
  await auth.logout()
  router.replace({ name: 'admin.login' })
}
</script>
