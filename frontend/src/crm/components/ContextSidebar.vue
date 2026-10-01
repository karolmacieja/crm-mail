<template>
  <!-- Right slide-over (450px) from the mockup: opens when an email is clicked. -->
  <div
    class="absolute inset-0 z-30 bg-slate-900/20 backdrop-blur-sm transition-opacity duration-300"
    :class="client.isSidebarOpen ? 'opacity-100' : 'pointer-events-none opacity-0'"
    aria-hidden="true"
    @click="client.closeSidebar()"
  />
  <aside
    class="absolute right-0 top-0 z-40 flex h-full w-[450px] max-w-full flex-col border-l border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-in-out"
    :class="client.isSidebarOpen ? 'translate-x-0' : 'translate-x-full'"
    :aria-hidden="!client.isSidebarOpen"
    @keydown.esc="client.closeSidebar()"
  >
    <!-- Content only while open: no hidden duplicate of the card (e.g. under FullClientProfile) and no off-screen focusable controls. -->
    <ClientCard v-if="client.context && client.isSidebarOpen" closable @open-profile="openProfile">
      <template #close>
        <button
          type="button"
          class="absolute right-4 top-4 rounded-full bg-white p-2 text-gray-400 shadow-sm hover:text-gray-700"
          :aria-label="t('common.close')"
          @click="client.closeSidebar()"
        >
          <Icon icon="xmark" class="text-lg" />
        </button>
      </template>
    </ClientCard>
  </aside>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import ClientCard from '@/crm/components/client/ClientCard.vue'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const router = useRouter()

function openProfile(id) {
  client.closeSidebar()
  router.push({ name: 'crm.clients.show', params: { id } })
}
</script>
