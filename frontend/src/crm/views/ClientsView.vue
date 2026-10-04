<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('crm.clients.title') }}</h2>
      <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-hover" @click="showCreate = true">
        <Icon icon="plus" class="mr-1" /> {{ t('crm.clients.new') }}
      </button>
    </div>

    <!-- Search & filters -->
    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
      <div class="relative min-w-[250px] flex-1">
        <Icon icon="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
        <input v-model="search" type="search" :placeholder="t('crm.clients.search')" class="w-full rounded-lg border border-gray-300 py-2 pl-10 pr-4 text-sm outline-none transition-all focus:border-primary focus:ring-1 focus:ring-primary" />
      </div>
      <div class="w-48">
        <select v-model="category" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
          <option value="">{{ t('crm.clients.allCategories') }}</option>
          <option v-for="c in contacts.categories" :key="c.id" :value="c.slug">{{ c.name }}</option>
        </select>
      </div>
      <div class="w-48">
        <select v-model="owner" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm outline-none focus:border-primary focus:ring-1 focus:ring-primary">
          <option value="">{{ t('crm.sharing.filter.all') }}</option>
          <option value="mine">{{ t('crm.sharing.filter.mine') }}</option>
          <option value="shared">{{ t('crm.sharing.filter.shared') }}</option>
        </select>
      </div>
    </div>

    <AlertMessage v-if="list.error" class="mb-6" :message="list.error.message" retryable @retry="contacts.loadClients()" />

    <div v-if="list.loading && !list.loaded" class="space-y-3">
      <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-xl bg-gray-100" />
    </div>

    <!-- One table per category, as in the mockup -->
    <div v-for="(group, index) in contacts.groupedClients" :key="group.category?.id ?? 'none'" class="mb-6">
      <h3 class="mb-3 flex items-center px-1 text-sm font-bold uppercase tracking-wider text-gray-500">
        <Icon :icon="categoryIcon(group.category)" class="mr-2" :class="{ 'text-yellow-400': group.category?.slug === 'vip' }" />
        {{ group.category ? t('crm.clients.categoryHeading', { name: group.category.name }) : t('crm.clients.noCategory') }}
        <span class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-700">{{ group.items.length }}</span>
      </h3>
      <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full table-fixed text-left text-sm">
          <thead v-if="index === 0" class="border-b border-gray-200 bg-gray-50/50 text-xs uppercase text-gray-500">
            <tr>
              <th class="w-1/3 px-6 py-3 font-semibold">{{ t('crm.clients.columns.client') }}</th>
              <th class="w-1/4 px-6 py-3 font-semibold">{{ t('crm.clients.columns.contact') }}</th>
              <th class="w-1/4 px-6 py-3 font-semibold">{{ t('crm.clients.columns.activity') }}</th>
              <th class="px-6 py-3 text-right font-semibold">{{ t('crm.clients.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="contact in group.items" :key="contact.id" class="group cursor-pointer transition-colors hover:bg-gray-50" @click="open(contact)">
              <td class="w-1/3 px-6 py-4">
                <p class="truncate font-bold text-gray-900 group-hover:text-primary">{{ contact.name || contact.email }}</p>
                <p class="truncate text-xs text-gray-500">
                  <template v-if="contact.access && !contact.access.is_owner && contact.owner">
                    <Icon icon="share-nodes" class="mr-1 text-gray-400" />{{ t('crm.sharing.ownerShort', { name: contact.owner.name }) }}
                  </template>
                  <template v-else>{{ contact.company || t('crm.client.individual') }}</template>
                </p>
              </td>
              <td class="w-1/4 px-6 py-4">
                <p class="truncate text-gray-700">
                  <template v-if="contact.phone && !contact.company"><Icon icon="phone" class="mr-1 text-gray-400" /> {{ contact.phone }}</template>
                  <template v-else><Icon :icon="['far', 'envelope']" class="mr-1 text-gray-400" /> {{ contact.email }}</template>
                </p>
              </td>
              <td class="w-1/4 px-6 py-4 text-gray-500">
                <template v-if="contact.last_activity">
                  {{ formatCalendar(contact.last_activity.occurred_at) }}
                  <span class="block text-xs text-gray-400">{{ activityLabel(contact.last_activity) }}</span>
                </template>
                <template v-else>—</template>
              </td>
              <td class="px-6 py-4 text-right text-gray-400">
                <button type="button" class="mx-1 p-1 hover:text-primary" :title="t('crm.clients.quickView')" @click.stop="client.openContact(contact.id, { sidebar: true })">
                  <Icon icon="pen" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <EmptyState v-if="list.loaded && !list.items.length" icon="users" :text="search || category || owner ? t('crm.clients.noMatches') : t('crm.clients.empty')" />

    <NewClientModal v-if="showCreate" @close="showCreate = false" @created="open" />
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import EmptyState from '@/shared/components/EmptyState.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import NewClientModal from '@/crm/components/NewClientModal.vue'
import { activityLabel } from '@/crm/lib/activity.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useContactsStore } from '@/crm/stores/contacts.js'

const contacts = useContactsStore()
const client = useClientStore()
const router = useRouter()
const list = contacts.clients

const search = ref(list.filters.search)
const category = ref(list.filters.category)
const owner = ref(list.filters.owner)
const showCreate = ref(false)

onMounted(async () => {
  await contacts.loadCategories()
  contacts.loadClients()
})

let timer
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => contacts.loadClients({ filters: { search: value.trim() } }), 300)
})
watch(category, (value) => contacts.loadClients({ filters: { category: value } }))
watch(owner, (value) => contacts.loadClients({ filters: { owner: value } }))

const categoryIcon = (category) => category?.icon?.replace(/^fa-/, '') || 'user'

/** Full profile replaces the list (FullClientProfile route). */
function open(contact) {
  router.push({ name: 'crm.clients.show', params: { id: contact.id } })
}
</script>
