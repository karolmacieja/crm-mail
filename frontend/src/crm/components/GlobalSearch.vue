<template>
  <div class="relative hidden w-72 md:block">
    <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
    <input
      v-model="query"
      type="search"
      :placeholder="t('crm.search.placeholder')"
      class="w-full rounded-lg border border-transparent bg-gray-100 py-1.5 pl-10 pr-4 text-sm transition-all focus:border-primary focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary/20"
      @focus="open = true"
      @keydown.esc="open = false"
    />
    <ul v-if="open && query.trim().length >= 2" class="absolute right-0 z-50 mt-1 max-h-80 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
      <li v-if="loading" class="px-3 py-2 text-xs text-gray-400">{{ t('common.loading') }}</li>
      <li v-else-if="!results.length" class="px-3 py-2 text-xs text-gray-400">{{ t('crm.search.none') }}</li>
      <li v-for="contact in results" :key="contact.id">
        <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-gray-50" @mousedown.prevent="openContact(contact)">
          <span class="min-w-0">
            <span class="block truncate text-sm font-medium text-gray-900">{{ contact.name || contact.email }}</span>
            <span class="block truncate text-xs text-gray-500">{{ contact.company || contact.email }}</span>
          </span>
          <CategoryBadge :category="contact.category" />
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '@/crm/api.js'
import CategoryBadge from '@/shared/components/CategoryBadge.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'

const router = useRouter()
const client = useClientStore()
const query = ref('')
const results = ref([])
const loading = ref(false)
const open = ref(false)

let timer
let requestId = 0
watch(query, (value) => {
  clearTimeout(timer)
  if (value.trim().length < 2) return
  timer = setTimeout(async () => {
    const id = ++requestId
    loading.value = true
    try {
      const { data } = await api.get('/contacts', { params: { search: value.trim(), per_page: 8 } })
      if (id === requestId) results.value = data.data
    } finally {
      if (id === requestId) loading.value = false
    }
  }, 250)
})

/** Clients open as a full profile; plain contacts in the right sidebar. */
function openContact(contact) {
  open.value = false
  query.value = ''
  if (contact.is_client) router.push({ name: 'crm.clients.show', params: { id: contact.id } })
  else client.openContact(contact.id, { sidebar: true })
}
</script>
