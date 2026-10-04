<template>
  <Modal :open="open" :title="t('crm.sharing.dialogTitle', { name: client.displayName })" size="lg" @close="$emit('close')">
    <div class="space-y-5">
      <!-- Who -->
      <label class="block">
        <span class="gcrm-label">{{ t('crm.sharing.shareWith') }}</span>
        <select v-model="target" class="gcrm-input">
          <option value="team">{{ t('crm.sharing.wholeTeam') }}</option>
          <option v-for="member in colleagues" :key="member.id" :value="member.id">{{ member.name }}</option>
        </select>
      </label>

      <!-- What -->
      <fieldset>
        <legend class="gcrm-label">{{ t('crm.sharing.whatToShare') }}</legend>
        <div class="grid gap-2 sm:grid-cols-2">
          <label v-for="scope in SHARE_SCOPES" :key="scope" class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-200 p-2.5 hover:bg-gray-50">
            <input v-model="scopes" type="checkbox" :value="scope" class="mt-0.5 accent-primary" />
            <span>
              <span class="block text-sm font-medium text-gray-900">{{ t(`crm.sharing.scopes.${scope}`) }}</span>
              <span class="block text-xs text-gray-500">{{ t(`crm.sharing.scopeHints.${scope}`) }}</span>
            </span>
          </label>
        </div>
      </fieldset>

      <!-- Individual emails, when the whole correspondence is not shared -->
      <fieldset v-if="!scopes.includes('emails')">
        <legend class="gcrm-label">{{ t('crm.sharing.selectedEmails') }}</legend>
        <p v-if="emailsLoading" class="text-xs text-gray-500">{{ t('common.loading') }}</p>
        <p v-else-if="!emails.length" class="text-xs text-gray-500">{{ t('crm.sharing.noEmails') }}</p>
        <div v-else class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
          <label v-for="email in emails" :key="email.id" class="flex cursor-pointer items-start gap-2 rounded p-1.5 hover:bg-gray-50">
            <input v-model="emailIds" type="checkbox" :value="email.id" class="mt-0.5 accent-primary" />
            <span class="min-w-0">
              <span class="block truncate text-sm text-gray-900">{{ email.title || t('crm.timeline.noSubject') }}</span>
              <span class="block text-xs text-gray-500">{{ formatCalendar(email.occurred_at) }}</span>
            </span>
          </label>
        </div>
      </fieldset>

      <p v-if="error" class="text-xs text-red-600">{{ error }}</p>

      <!-- Current shares -->
      <div v-if="shares.length">
        <h4 class="gcrm-label">{{ t('crm.sharing.currentShares') }}</h4>
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
          <li v-for="item in shares" :key="item.id" class="flex items-center justify-between gap-3 p-2.5 text-sm">
            <button type="button" class="min-w-0 text-left" @click="target = item.user ? item.user.id : 'team'">
              <span class="font-medium text-gray-900"><Icon :icon="item.user ? 'user' : 'users'" class="mr-1 text-gray-400" />{{ item.user?.name ?? t('crm.sharing.wholeTeam') }}</span>
              <span class="block truncate text-xs text-gray-500">{{ describe(item) }}</span>
            </button>
            <button type="button" class="shrink-0 text-xs text-gray-400 hover:text-red-600" @click="remove(item)">{{ t('crm.sharing.stopSharing') }}</button>
          </li>
        </ul>
      </div>

      <!-- Hand over -->
      <details class="rounded-lg border border-gray-200 p-3 text-sm">
        <summary class="cursor-pointer font-medium text-gray-700"><Icon icon="handshake" class="mr-1 text-gray-400" />{{ t('crm.sharing.transferTitle') }}</summary>
        <p class="mt-2 text-xs text-gray-500">{{ t('crm.sharing.transferHint') }}</p>
        <div class="mt-2 flex gap-2">
          <select v-model="newOwner" class="gcrm-input">
            <option :value="null">—</option>
            <option v-for="member in colleagues" :key="member.id" :value="member.id">{{ member.name }}</option>
          </select>
          <button type="button" class="gcrm-btn-secondary shrink-0" :disabled="!newOwner || saving" @click="handOver">{{ t('crm.sharing.transfer') }}</button>
        </div>
      </details>
    </div>

    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.cancel') }}</button>
      <button type="button" class="gcrm-btn-primary" :disabled="saving || (!scopes.length && !emailIds.length)" @click="save">
        <Icon icon="share-nodes" /> {{ t('crm.sharing.save') }}
      </button>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import Modal from '@/shared/components/Modal.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { api } from '@/crm/api.js'
import { SHARE_SCOPES, useClientStore } from '@/crm/stores/client.js'
import { useTeamStore } from '@/crm/stores/team.js'

const props = defineProps({
  open: { type: Boolean, default: false },
  /** Preselected colleague (answering "Poproś o dostęp"). */
  userId: { type: Number, default: null },
})
const emit = defineEmits(['close'])

const client = useClientStore()
const team = useTeamStore()

const target = ref('team')
const scopes = ref([])
const emailIds = ref([])
const emails = ref([])
const emailsLoading = ref(false)
const newOwner = ref(null)
const saving = ref(false)
const error = ref('')

const shares = computed(() => client.contact?.shares ?? [])
const ownerId = computed(() => client.contact?.owner?.id ?? null)
const colleagues = computed(() => team.members.filter((m) => !m.is_me && m.id !== ownerId.value && m.role !== 'master_admin'))

watch(
  () => props.open,
  (open) => {
    if (!open) return
    error.value = ''
    newOwner.value = null
    team.load().catch(() => {})
    loadEmails()
    target.value = props.userId ?? 'team'
    prefill()
  },
  { immediate: true },
)
watch(target, prefill)

/** Editing an existing share starts from what it covers now. */
function prefill() {
  const userId = target.value === 'team' ? null : target.value
  const existing = shares.value.find((s) => (s.user?.id ?? null) === userId)
  scopes.value = existing ? [...existing.scopes] : userId === null ? [] : ['details']
  emailIds.value = existing ? [...existing.email_ids] : []
}

async function loadEmails() {
  emailsLoading.value = true
  try {
    const { data } = await api.get(`/contacts/${client.contact.id}/activities`, { params: { type: 'email', per_page: 100 } })
    emails.value = data.data
  } catch {
    emails.value = []
  } finally {
    emailsLoading.value = false
  }
}

function describe(item) {
  const parts = item.scopes.map((s) => t(`crm.sharing.scopes.${s}`))
  if (!item.scopes.includes('emails') && item.email_ids.length) parts.push(t('crm.sharing.emailsCount', { count: item.email_ids.length }))
  return parts.join(', ')
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    await client.share({
      user_id: target.value === 'team' ? null : target.value,
      scopes: scopes.value,
      email_ids: scopes.value.includes('emails') ? [] : emailIds.value,
    })
    emit('close')
  } catch (e) {
    error.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  try {
    await client.unshare(item.id)
  } catch (e) {
    error.value = e.message
  }
}

async function handOver() {
  const member = colleagues.value.find((m) => m.id === newOwner.value)
  if (!member || !window.confirm(t('crm.sharing.confirmTransfer', { name: member.name }))) return
  saving.value = true
  try {
    await client.transfer(member.id)
    emit('close')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}
</script>
