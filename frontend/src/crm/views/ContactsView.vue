<template>
  <!-- Address book: plain email contacts without a client profile. -->
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('crm.contacts.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover" @click="showCreate = true">
        <Icon icon="plus" class="mr-1" /> {{ t('crm.contacts.new') }}
      </button>
    </div>

    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('crm.contacts.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
    </div>

    <AlertMessage v-if="list.error" class="mb-6" :message="list.error.message" retryable @retry="contacts.loadAddressBook()" />

    <div v-if="list.items.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50/50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3 font-semibold">{{ t('contact.name') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('contact.email') }}</th>
            <th class="px-6 py-3 font-semibold">{{ t('crm.clients.columns.activity') }}</th>
            <th class="px-6 py-3 text-right font-semibold">{{ t('crm.clients.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="contact in list.items" :key="contact.id" class="group cursor-pointer hover:bg-gray-50" @click="client.openContact(contact.id, { sidebar: true })">
            <td class="px-6 py-3 font-medium text-gray-900 group-hover:text-primary">{{ contact.name || '—' }}</td>
            <td class="px-6 py-3 text-gray-700">{{ contact.email }}</td>
            <td class="px-6 py-3 text-gray-500">{{ contact.last_activity ? formatCalendar(contact.last_activity.occurred_at) : '—' }}</td>
            <td class="px-6 py-3 text-right">
              <button type="button" class="text-xs font-medium text-primary hover:underline" :disabled="busy[contact.id]" @click.stop="promote(contact)">
                {{ t('crm.contacts.makeClient') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
      <footer v-if="list.meta.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-6 py-3 text-xs text-gray-600">
        <span>{{ t('dashboard.page', { page: list.meta.current_page, pages: list.meta.last_page }) }}</span>
        <div class="flex gap-2">
          <button type="button" class="gcrm-btn-secondary px-2 py-1 text-xs" :disabled="list.meta.current_page <= 1" @click="contacts.loadAddressBook({ page: list.meta.current_page - 1 })">{{ t('dashboard.previous') }}</button>
          <button type="button" class="gcrm-btn-secondary px-2 py-1 text-xs" :disabled="list.meta.current_page >= list.meta.last_page" @click="contacts.loadAddressBook({ page: list.meta.current_page + 1 })">{{ t('dashboard.next') }}</button>
        </div>
      </footer>
    </div>

    <EmptyState v-else-if="list.loaded" icon="address-book" :text="search ? t('crm.clients.noMatches') : t('crm.contacts.empty')" />

    <NewClientModal v-if="showCreate" :is-client="false" @close="showCreate = false" />
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { api } from '@/crm/api.js'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import NewClientModal from '@/crm/components/NewClientModal.vue'
import { useClientStore } from '@/crm/stores/client.js'
import { useContactsStore } from '@/crm/stores/contacts.js'
import { useDashboardStore } from '@/crm/stores/dashboard.js'

const contacts = useContactsStore()
const client = useClientStore()
const list = contacts.addressBook
const search = ref(list.filters.search)
const showCreate = ref(false)
const busy = reactive({})

onMounted(() => contacts.loadAddressBook())

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => contacts.loadAddressBook({ filters: { search: value.trim() } }), 300)
})

/** Turn an address-book entry into a client profile. */
async function promote(contact) {
  busy[contact.id] = true
  try {
    await api.patch(`/contacts/${contact.id}`, { is_client: true })
    contacts.syncContact(contact, { removed: true })
    useDashboardStore().invalidate()
  } catch (e) {
    window.alert(e.message)
  } finally {
    delete busy[contact.id]
  }
}
</script>
