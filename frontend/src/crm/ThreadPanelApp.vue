<template>
  <!-- Gmail thread sidebar panel (InboxSDK): the client card of the open email's sender. -->
  <div class="gcrm-root h-full bg-white">
    <div v-if="!auth.ready || busy" class="flex items-center gap-2 p-6 text-gray-500"><Spinner /> {{ t('common.loadingCrm') }}</div>
    <div v-else-if="!auth.isAuthenticated" class="p-4"><LoginForm /></div>
    <div v-else-if="auth.role === 'master_admin'" class="p-4 text-sm text-gray-600">{{ t('crm.thread.adminNoCrm') }}</div>
    <div v-else-if="!auth.hasValidLicense" class="p-4"><LicenseNotice /></div>
    <p v-else-if="!activeEmail" class="p-6 text-center text-sm text-gray-500">{{ t('sidebar.noExternal') }}</p>

    <template v-else>
      <p v-if="activeParticipant?.via" class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-xs text-gray-500">
        {{ t('sidebar.forwardedVia', { address: activeParticipant.via }) }}
      </p>
      <div v-if="participants.length > 1" class="border-b border-gray-100 p-3">
        <label class="gcrm-label" for="gfx-participant">{{ t('sidebar.participant') }}</label>
        <select id="gfx-participant" v-model="activeEmail" class="gcrm-input">
          <option v-for="p in participants" :key="p.email" :value="p.email">{{ p.name ? `${p.name} <${p.email}>` : p.email }}</option>
        </select>
      </div>
      <ClientCard dense @open-profile="(id) => gmail.openCrm(`/clients/${id}`)" />
    </template>

    <div class="flex justify-end border-t border-gray-100 p-2"><LanguageSwitch /></div>
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref, watch } from 'vue'
import LanguageSwitch from '@/shared/components/LanguageSwitch.vue'
import Spinner from '@/shared/components/Spinner.vue'
import { initLocale, t } from '@/shared/lib/i18n.js'
import ClientCard from '@/crm/components/client/ClientCard.vue'
import LicenseNotice from '@/crm/components/LicenseNotice.vue'
import LoginForm from '@/crm/components/LoginForm.vue'
import { useAuthStore } from '@/crm/stores/auth.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useSettingsStore } from '@/crm/stores/settings.js'
import { useGoogleStore } from '@/crm/stores/google.js'
import { internalMatcher, resolveParticipants } from '@/crm/lib/senders.js'

const props = defineProps({
  /** Loaded messages, newest first: [{ email, name, messageId, snippet, forwarded, recipients }] */
  messages: { type: Array, default: () => [] },
  /** The Gmail tab's own address. */
  myEmail: { type: String, default: '' },
  subject: { type: String, default: '' },
  threadId: { type: String, default: null },
  resolving: { type: Boolean, default: false },
})

const gmail = inject('gmail')
const auth = useAuthStore()
const client = useClientStore()
const selected = ref(null)
const settings = useSettingsStore()
const google = useGoogleStore()

/**
 * Who the thread is with. Internal addresses (me, my company domain, those
 * from Preferences → Poczta – e.g. info@ forwarding to me) are replaced by the
 * real sender of the forwarded message, or skipped.
 */
const participants = ref([])
const resolvingParticipants = ref(true)
const busy = computed(() => props.resolving || resolvingParticipants.value)
let run = 0
watch(
  [() => props.messages, () => settings.preferences?.mail],
  async () => {
    const id = ++run
    resolvingParticipants.value = true
    const result = await resolveParticipants(props.messages, {
      me: props.myEmail,
      isInternal: internalMatcher(props.myEmail, settings.preferences?.mail ?? {}),
      lookupHeaders: (messageId) => google.originalSender(messageId),
    })
    if (id !== run) return
    participants.value = result
    resolvingParticipants.value = false
  },
  { immediate: true, deep: true },
)
const activeParticipant = computed(() => participants.value.find((p) => p.email === activeEmail.value) ?? null)

const activeEmail = computed({
  get: () => selected.value ?? participants.value[0]?.email ?? null,
  set: (email) => {
    selected.value = email
  },
})

const ready = computed(() => auth.ready && auth.isAuthenticated && auth.hasValidLicense && auth.role !== 'master_admin' && Boolean(activeEmail.value))

onMounted(() => {
  initLocale()
  auth.init()
})

// Dictionaries (statuses, categories) and preferences once signed in.
watch(ready, (isReady) => isReady && useSettingsStore().load(), { immediate: true })

watch(
  [ready, activeEmail],
  ([isReady, email]) => {
    if (!isReady) return
    const participant = participants.value.find((p) => p.email === email) ?? { email }
    client.openEmail(
      {
        email,
        name: participant.name,
        subject: props.subject,
        snippet: participant.snippet ?? null,
        threadId: props.threadId,
        messageId: participant.messageId ?? (props.threadId ? `thread:${props.threadId}` : null),
      },
      { sidebar: false },
    )
  },
  { immediate: true },
)
</script>
