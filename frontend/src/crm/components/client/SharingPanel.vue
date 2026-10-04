<template>
  <!-- Who looks after the client and who else can see the card. -->
  <section class="text-sm">
    <!-- Business correspondence: my own private card -->
    <p v-if="client.contact.is_personal" class="text-gray-600">
      <Icon icon="lock" class="mr-1.5 text-gray-400" />{{ t('crm.personal.cardInfo', { category: client.contact.category?.name ?? '' }) }}
    </p>

    <template v-else-if="client.canManage">
      <div class="flex items-center justify-between gap-3">
        <p class="min-w-0 text-gray-600">
          <Icon :icon="shares.length ? 'share-nodes' : 'lock'" class="mr-1.5 text-gray-400" />
          <template v-if="!client.contact.owner">{{ t('crm.sharing.noOwner') }}</template>
          <template v-else-if="!shares.length">{{ t('crm.sharing.private') }}</template>
          <template v-else>{{ t('crm.sharing.sharedWith', { list: sharedList }) }}</template>
        </p>
        <button type="button" class="gcrm-btn-secondary shrink-0 px-3 py-1.5 text-xs" @click="openDialog()">
          <Icon icon="share-nodes" /> {{ t('crm.sharing.share') }}
        </button>
      </div>

      <!-- Pending "Poproś o dostęp" -->
      <ul v-if="requests.length" class="mt-3 space-y-2">
        <li v-for="request in requests" :key="request.id" class="rounded-lg border border-amber-200 bg-amber-50 p-2.5">
          <p class="text-gray-800"><Icon icon="user-lock" class="mr-1.5 text-amber-500" />{{ t('crm.sharing.requestFrom', { name: request.user.name }) }}</p>
          <p v-if="request.message" class="mt-1 text-xs italic text-gray-600">„{{ request.message }}”</p>
          <div class="mt-2 flex gap-2">
            <button type="button" class="gcrm-btn-primary px-2.5 py-1 text-xs" @click="openDialog(request.user.id)">{{ t('crm.sharing.chooseAndShare') }}</button>
            <button type="button" class="gcrm-btn-ghost px-2.5 py-1 text-xs" @click="decline(request)">{{ t('crm.sharing.decline') }}</button>
          </div>
        </li>
      </ul>

      <ShareDialog :open="dialog.open" :user-id="dialog.userId" @close="dialog.open = false" />
    </template>

    <!-- A colleague's client shared with me -->
    <template v-else>
      <p class="text-gray-600">
        <Icon icon="user-tie" class="mr-1.5 text-gray-400" />{{ t('crm.sharing.ownedBy', { name: client.contact.owner?.name ?? '—' }) }}
      </p>
      <p class="mt-1 text-xs text-gray-500">{{ t('crm.sharing.youSee', { list: mySections }) }}</p>
      <button v-if="!requested && !isFull" type="button" class="mt-2 text-xs font-medium text-primary hover:underline" @click="askForMore">
        {{ t('crm.sharing.askForMore') }}
      </button>
      <p v-else-if="requested" class="mt-2 text-xs text-gray-500">{{ t('crm.sharing.requested') }}</p>
    </template>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { SHARE_SCOPES, useClientStore } from '@/crm/stores/client.js'
import ShareDialog from './ShareDialog.vue'

const client = useClientStore()
const shares = computed(() => client.contact?.shares ?? [])
const requests = computed(() => client.contact?.access_requests ?? [])
const sharedList = computed(() => shares.value.map((s) => s.user?.name ?? t('crm.sharing.wholeTeam')).join(', '))
const isFull = computed(() => SHARE_SCOPES.every((s) => client.access.scopes.includes(s)))
const mySections = computed(() => {
  const parts = client.access.scopes.map((s) => t(`crm.sharing.scopes.${s}`).toLowerCase())
  if (!client.access.scopes.includes('emails') && client.access.email_ids.length) parts.push(t('crm.sharing.emailsCount', { count: client.access.email_ids.length }))
  return parts.length ? parts.join(', ') : t('crm.sharing.basicOnly')
})

const dialog = reactive({ open: false, userId: null })
const requested = ref(false)

function openDialog(userId = null) {
  dialog.userId = userId
  dialog.open = true
}

async function decline(request) {
  try {
    await client.declineRequest(request.id)
  } catch (e) {
    window.alert(e.message)
  }
}

async function askForMore() {
  try {
    await client.requestAccess()
    requested.value = true
  } catch (e) {
    window.alert(e.message)
  }
}
</script>
