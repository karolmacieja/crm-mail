<template>
  <!-- Full client card: replaces the "Klienci" list; fed by the same useClientStore as ContextSidebar. -->
  <div>
    <button type="button" class="mb-4 text-sm font-medium text-gray-500 hover:text-primary" @click="router.push({ name: 'crm.clients' })">
      <Icon icon="arrow-left" class="mr-1" /> {{ t('crm.profile.back') }}
    </button>

    <div v-if="client.status === 'loading' && !loaded" class="space-y-4">
      <div class="h-32 animate-pulse rounded-xl bg-gray-100" />
      <div class="grid gap-6 lg:grid-cols-3"><div class="h-64 animate-pulse rounded-xl bg-gray-100 lg:col-span-1" /><div class="h-64 animate-pulse rounded-xl bg-gray-100 lg:col-span-2" /></div>
    </div>

    <AlertMessage v-else-if="client.status === 'error'" :message="client.error?.message" retryable @retry="client.openContact(id)" />

    <template v-else-if="loaded">
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-xl border border-gray-200 bg-gray-50/80 p-6 shadow-sm">
        <ClientHeader />
        <div class="flex gap-2">
          <button v-if="gmail?.compose" type="button" class="gcrm-btn-primary" @click="gmail.compose(client.contact.email)">
            <Icon :icon="['far', 'pen-to-square']" /> {{ t('crm.client.compose') }}
          </button>
          <button v-if="gmail?.searchEmail" type="button" class="gcrm-btn-secondary" @click="gmail.searchEmail(client.contact.email)">
            <Icon :icon="['far', 'envelope']" /> {{ t('crm.profile.emails') }}
          </button>
          <button type="button" class="gcrm-btn-danger" @click="remove"><Icon icon="trash" /> {{ t('common.delete') }}</button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
          <ContactDetails class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm" />
          <NotesPanel class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm" />
          <ReservationsPanel class="rounded-xl border border-orange-100 bg-orange-50/30 p-6 shadow-sm" />
          <ClientWork class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm" />
        </div>
        <div class="space-y-6 lg:col-span-2">
          <QuickNote class="rounded-xl border border-gray-200 bg-blue-50/20 p-6 shadow-sm" />
          <Timeline class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm" />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, inject, watch } from 'vue'
import { useRouter } from 'vue-router'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import ClientHeader from '@/crm/components/client/ClientHeader.vue'
import ClientWork from '@/crm/components/client/ClientWork.vue'
import ContactDetails from '@/crm/components/client/ContactDetails.vue'
import NotesPanel from '@/crm/components/client/NotesPanel.vue'
import QuickNote from '@/crm/components/client/QuickNote.vue'
import ReservationsPanel from '@/crm/components/client/ReservationsPanel.vue'
import Timeline from '@/crm/components/client/Timeline.vue'
import { useClientStore } from '@/crm/stores/client.js'
import { useContactsStore } from '@/crm/stores/contacts.js'

const props = defineProps({ id: { type: Number, required: true } })

const client = useClientStore()
const contacts = useContactsStore()
const router = useRouter()
const gmail = inject('gmail', null)

const loaded = computed(() => client.contact?.id === props.id)

watch(() => props.id, (id) => client.openContact(id), { immediate: true })

async function remove() {
  if (!window.confirm(t('crm.client.confirmDelete', { name: client.displayName }))) return
  const id = client.contact.id
  await client.deleteContact()
  contacts.syncContact({ id }, { removed: true })
  router.push({ name: 'crm.clients' })
}
</script>
