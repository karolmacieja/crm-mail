<template>
  <!-- The sender is a colleague's private client: one card, one owner. -->
  <div class="space-y-3">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center text-sm text-gray-700">
      <Icon icon="user-lock" class="mb-1 text-lg text-gray-400" />
      <p class="font-medium">{{ t('crm.sharing.hasOwner', { name: ownership.owner.name }) }}</p>
      <p class="mt-1 text-xs text-gray-500">{{ t('crm.sharing.hasOwnerHint') }}</p>
    </div>

    <p v-if="ownership.access_requested" class="rounded-lg bg-green-50 p-3 text-center text-xs text-green-700">
      <Icon icon="check" class="mr-1" />{{ t('crm.sharing.requested') }}
    </p>
    <form v-else class="space-y-2" @submit.prevent="send">
      <textarea v-model.trim="message" rows="2" maxlength="500" class="gcrm-input resize-none" :placeholder="t('crm.sharing.requestPlaceholder')" />
      <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
      <button type="submit" class="gcrm-btn-primary w-full" :disabled="sending"><Icon icon="key" /> {{ t('crm.sharing.requestAccess') }}</button>
    </form>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const ownership = computed(() => client.ownership)
const message = ref('')
const sending = ref(false)
const error = ref('')

async function send() {
  sending.value = true
  error.value = ''
  try {
    await client.requestAccess(message.value || null)
  } catch (e) {
    error.value = e.message
  } finally {
    sending.value = false
  }
}
</script>
