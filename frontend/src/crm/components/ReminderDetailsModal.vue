<template>
  <!-- Reminder details: when, client, the email it was created from – open it in Gmail or reply. -->
  <Modal open :title="reminder.title" @close="$emit('close')">
    <div class="space-y-4 text-sm">
      <div class="flex flex-wrap items-center gap-2">
        <span class="rounded border px-2 py-0.5 text-xs font-bold" :class="overdue ? 'border-red-200 bg-red-50 text-red-600' : 'border-blue-200 bg-blue-50 text-blue-600'">
          <Icon :icon="['far', 'clock']" class="mr-1" />{{ formatCalendar(reminder.remind_at) }}
        </span>
        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ t(`crm.reminderTypes.${reminder.type}`) }}</span>
        <span v-if="reminder.is_done" class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-700">{{ t('crm.reminders.done') }}</span>
      </div>
      <p v-if="reminder.notes" class="whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-gray-700">{{ reminder.notes }}</p>

      <!-- Client -->
      <section v-if="reminder.contact" class="rounded-lg border border-gray-200 p-3">
        <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ t('crm.reminders.details.client') }}</h4>
        <p class="font-bold text-gray-900">{{ contact?.name || reminder.contact.name || reminder.contact.email }}</p>
        <p class="text-gray-600"><Icon :icon="['far', 'envelope']" class="mr-1 text-gray-400" />{{ reminder.contact.email }}</p>
        <p v-if="contact?.phone" class="text-gray-600"><Icon icon="phone" class="mr-1 text-gray-400" /><a :href="`tel:${contact.phone}`" class="hover:text-primary">{{ contact.phone }}</a></p>
        <p v-if="contact?.company" class="text-gray-600"><Icon icon="briefcase" class="mr-1 text-gray-400" />{{ contact.company }}</p>
        <button type="button" class="mt-2 text-xs font-medium text-primary hover:underline" @click="openClient">{{ t('crm.client.openProfile') }} <Icon icon="chevron-right" /></button>
      </section>

      <!-- Source email -->
      <section v-if="fromEmail" class="rounded-lg border border-gray-200 p-3">
        <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">{{ t('crm.reminders.details.email') }}</h4>
        <p class="font-medium text-gray-900">{{ reminder.source_email_subject || email?.title || t('crm.timeline.noSubject') }}</p>
        <p v-if="email" class="mt-0.5 text-xs text-gray-500">{{ formatCalendar(email.occurred_at) }}<template v-if="email.meta?.from"> · {{ email.meta.from }}</template></p>
        <p v-if="email?.body" class="mt-1 line-clamp-3 text-gray-600">{{ email.body }}</p>
        <div v-if="gmail?.openThread" class="mt-3 flex flex-wrap gap-2">
          <button type="button" class="gcrm-btn-secondary px-3 py-1.5 text-xs" :disabled="opening" @click="openEmail(false)">
            <Icon icon="arrow-up-right-from-square" /> {{ t('crm.timeline.openInGmail') }}
          </button>
          <button type="button" class="gcrm-btn-primary px-3 py-1.5 text-xs" :disabled="opening" @click="openEmail(true)">
            <Icon icon="reply" /> {{ t('crm.timeline.reply') }}
          </button>
        </div>
      </section>
      <p v-else class="text-xs text-gray-400">{{ t('crm.reminders.details.noEmail') }}</p>
    </div>

    <template #footer>
      <button type="button" class="gcrm-btn-ghost" @click="$emit('close')">{{ t('common.close') }}</button>
      <button v-if="!reminder.is_done && reminder.can_edit !== false" type="button" class="gcrm-btn bg-green-600 text-white hover:bg-green-700" :disabled="busy" @click="markDone">
        <Icon icon="check" /> {{ t('crm.reminders.markDone') }}
      </button>
    </template>
  </Modal>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Modal from '@/shared/components/Modal.vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { api } from '@/crm/api.js'
import { hasSourceEmail, sourceThreadId } from '@/crm/lib/emailSource.js'
import { useRemindersStore } from '@/crm/stores/reminders.js'

const props = defineProps({ reminder: { type: Object, required: true } })
const emit = defineEmits(['close'])

const gmail = inject('gmail', null)
// Outside the CRM route (e.g. Gmail's thread panel) there is no router.
const router = (() => {
  try {
    return useRouter()
  } catch {
    return null
  }
})()
const reminders = useRemindersStore()
const contact = ref(null)
const email = ref(null)
const busy = ref(false)
const opening = ref(false)

const overdue = computed(() => props.reminder.time_status === 'overdue')
const fromEmail = computed(() => hasSourceEmail(props.reminder))

/** Client details and the source email from the timeline (only what I may see). */
onMounted(async () => {
  const id = props.reminder.contact?.id
  if (!id) return
  try {
    contact.value = (await api.get(`/contacts/${id}`)).data.data
  } catch {
    contact.value = null
  }
  const messageId = props.reminder.source_email_id
  if (!messageId || !contact.value) return
  try {
    const { data } = await api.get(`/contacts/${id}/activities`, { params: { type: 'email', per_page: 100 } })
    const threadId = props.reminder.source_thread_id ?? (messageId.startsWith('thread:') ? messageId.slice(7) : null)
    email.value = data.data.find((a) => a.meta?.message_id === messageId) ?? data.data.find((a) => threadId && a.meta?.thread_id === threadId) ?? null
  } catch {
    email.value = null
  }
})

async function openEmail(reply) {
  opening.value = true
  try {
    const threadId = await sourceThreadId(props.reminder)
    if (!threadId) return
    emit('close')
    reply && gmail.replyToThread ? gmail.replyToThread(threadId) : gmail.openThread(threadId)
  } finally {
    opening.value = false
  }
}

function openClient() {
  emit('close')
  const id = props.reminder.contact.id
  if (router) router.push({ name: 'crm.clients.show', params: { id } })
  else gmail?.openCrm?.(`/clients/${id}`)
}

async function markDone() {
  busy.value = true
  try {
    await reminders.markDone(props.reminder)
    emit('close')
  } catch (e) {
    window.alert(e.message)
  } finally {
    busy.value = false
  }
}
</script>
