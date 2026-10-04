<template>
  <!--
    Content of the right sidebar and of Gmail's thread panel:
    header + the mockup's sections in order (contact data, note, reservations, timeline).
  -->
  <div class="flex h-full flex-col">
    <div class="relative border-b border-gray-200 bg-gray-50/80" :class="[pad, { 'pr-14': closable }]">
      <slot name="close" />
      <ClientHeader />
      <div v-if="gmail?.compose && client.email" class="mt-3 flex flex-wrap gap-2">
        <button type="button" class="gcrm-btn-primary px-3 py-1.5 text-xs" @click="gmail.compose(client.email)">
          <Icon :icon="['far', 'pen-to-square']" /> {{ t('crm.client.compose') }}
        </button>
        <button v-if="gmail.searchEmail" type="button" class="gcrm-btn-secondary px-3 py-1.5 text-xs" @click="gmail.searchEmail(client.email)">
          <Icon icon="magnifying-glass" /> {{ t('crm.profile.emails') }}
        </button>
      </div>
      <p v-if="client.context?.subject" class="mt-3 truncate text-xs text-gray-500">
        <Icon :icon="['far', 'envelope']" class="mr-1" /> {{ client.context.subject }}
      </p>
    </div>

    <div class="flex-1 overflow-y-auto pb-10">
      <div v-if="client.status === 'loading' && !client.contact" :class="pad" class="space-y-3" aria-busy="true">
        <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-lg bg-gray-100" />
      </div>

      <div v-else-if="client.status === 'error'" :class="pad">
        <AlertMessage :message="client.error?.message" retryable @retry="client.reload()" />
      </div>

      <div v-else-if="client.status === 'not_found'" :class="pad"><NewContactForm /></div>

      <div v-else-if="client.status === 'owned_elsewhere'" :class="pad"><OwnedByColleague /></div>

      <template v-else-if="client.contact">
        <SharingPanel class="border-b border-gray-100" :class="dense ? 'px-4 py-3' : 'px-6 py-3'" />
        <ContactDetails class="border-b border-gray-100" :class="pad" />
        <NotesPanel class="border-b border-gray-100" :class="pad" />
        <QuickNote class="border-b border-gray-100 bg-blue-50/20" :class="pad" />
        <ReservationsPanel class="border-b border-gray-100 bg-orange-50/30" :class="pad" />
        <ClientWork class="border-b border-gray-100" :class="pad" />
        <Timeline :class="pad" />
        <div class="flex items-center justify-between text-xs" :class="dense ? 'px-4' : 'px-6'">
          <button v-if="showProfileLink" type="button" class="font-medium text-primary hover:underline" @click="$emit('open-profile', client.contact.id)">
            {{ t('crm.client.openProfile') }} <Icon icon="chevron-right" />
          </button>
          <button v-if="client.canManage" type="button" class="text-gray-400 hover:text-red-600" @click="remove">{{ t('crm.client.delete') }}</button>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'
import ClientHeader from './ClientHeader.vue'
import ClientWork from './ClientWork.vue'
import ContactDetails from './ContactDetails.vue'
import NewContactForm from './NewContactForm.vue'
import NotesPanel from './NotesPanel.vue'
import OwnedByColleague from './OwnedByColleague.vue'
import QuickNote from './QuickNote.vue'
import ReservationsPanel from './ReservationsPanel.vue'
import SharingPanel from './SharingPanel.vue'
import Timeline from './Timeline.vue'

const props = defineProps({
  closable: { type: Boolean, default: false },
  showProfileLink: { type: Boolean, default: true },
  /** Narrow host (Gmail's thread sidebar): tighter padding. */
  dense: { type: Boolean, default: false },
})
const pad = computed(() => (props.dense ? 'p-4' : 'p-6'))
defineEmits(['open-profile'])

const gmail = inject('gmail', null)
const client = useClientStore()

async function remove() {
  if (!window.confirm(t('crm.client.confirmDelete', { name: client.displayName }))) return
  try {
    await client.deleteContact()
  } catch (e) {
    window.alert(e.message)
  }
}
</script>
