<template>
  <div class="gcrm-root space-y-3 bg-white p-3">
    <!-- Booting -->
    <div v-if="!auth.ready || resolving" class="flex items-center gap-2 py-6 text-gray-500">
      <Spinner /> <span>{{ t('common.loadingCrm') }}</span>
    </div>

    <!-- Signed out -->
    <LoginForm v-else-if="!auth.isAuthenticated" />

    <!-- License expired / missing -->
    <LicenseNotice v-else-if="!auth.hasValidLicense" @renewed="reload" />

    <!-- No external participant (e.g. a note to self) -->
    <div v-else-if="!activeEmail" class="py-4 text-center text-sm text-gray-500">
      {{ t('sidebar.noExternal') }}
    </div>

    <template v-else>
      <!-- Participant switcher for multi-party threads -->
      <div v-if="participants.length > 1">
        <label class="gcrm-label" :for="`${uid}-participant`">{{ t('sidebar.participant') }}</label>
        <select :id="`${uid}-participant`" v-model="participantModel" class="gcrm-input">
          <option v-for="p in participants" :key="p.email" :value="p.email">
            {{ p.name ? `${p.name} <${p.email}>` : p.email }}
          </option>
        </select>
      </div>

      <!-- Lookup in progress -->
      <div v-if="lookup.status === 'loading' && !contact" class="space-y-2 py-2" aria-busy="true">
        <div class="h-10 animate-pulse rounded bg-gray-100" />
        <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100" />
        <div class="h-24 animate-pulse rounded bg-gray-100" />
      </div>

      <!-- Lookup failed -->
      <AlertMessage
        v-else-if="lookup.status === 'error' && !lookup.error?.isLicenseError"
        :message="lookup.error?.message"
        retryable
        @retry="reload"
      />

      <!-- Not in CRM yet -->
      <section v-else-if="!contact" class="space-y-3">
        <div class="flex items-center gap-3">
          <Avatar :name="activeName" :email="activeEmail" muted />
          <div class="min-w-0">
            <p class="truncate font-medium text-gray-900">{{ activeName || activeEmail }}</p>
            <p class="truncate text-xs text-gray-500">{{ activeEmail }}</p>
          </div>
        </div>

        <div v-if="!creating" class="rounded-lg border border-dashed border-gray-300 p-3 text-center">
          <p class="text-sm text-gray-600">{{ t('sidebar.notInCrm') }}</p>
          <button type="button" class="gcrm-btn-primary mt-2" @click="creating = true">{{ t('sidebar.addToCrm') }}</button>
        </div>

        <ContactForm
          v-else
          :initial="{ email: activeEmail, name: activeName, status: 'lead' }"
          :on-save="(payload) => crm.createContact(payload)"
          :submit-label="t('sidebar.createContact')"
          @saved="creating = false"
          @cancel="creating = false"
        />
      </section>

      <!-- Existing contact -->
      <template v-else>
        <section class="space-y-2">
          <div class="flex items-start gap-3">
            <Avatar :name="contact.name" :email="contact.email" />
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-gray-900">{{ contact.name || contact.email }}</p>
              <p class="truncate text-xs text-gray-500">{{ contact.email }}</p>
              <div class="mt-1 flex items-center gap-2">
                <StatusBadge :status="contact.status" />
                <span v-if="contact.open_tasks_count" class="text-xs text-gray-500">
                  {{ t('sidebar.openTasks', { count: contact.open_tasks_count }) }}
                </span>
              </div>
            </div>
            <button
              v-if="!editing"
              type="button"
              class="gcrm-btn-ghost px-2 py-1 text-xs"
              @click="editing = true"
            >
              {{ t('common.edit') }}
            </button>
          </div>

          <ContactForm
            v-if="editing"
            :initial="contact"
            :on-save="(payload) => crm.updateContact(contact, payload)"
            @saved="onContactSaved"
            @cancel="editing = false"
          />

          <dl v-else class="space-y-1.5 text-sm">
            <div v-if="contact.phone" class="flex gap-2">
              <dt class="w-16 shrink-0 text-gray-500">{{ t('contact.phone') }}</dt>
              <dd><a :href="`tel:${contact.phone}`" class="text-indigo-600 hover:underline">{{ contact.phone }}</a></dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-16 shrink-0 text-gray-500">{{ t('contact.status') }}</dt>
              <dd>
                <select
                  :value="contact.status"
                  class="rounded border-0 bg-transparent p-0 text-sm text-gray-800 focus:ring-2 focus:ring-indigo-500/30"
                  :disabled="statusSaving"
                  :aria-label="t('sidebar.changeStatus')"
                  @change="changeStatus($event.target.value)"
                >
                  <option v-for="s in CONTACT_STATUSES" :key="s.value" :value="s.value">{{ t(`status.${s.value}`) }}</option>
                </select>
              </dd>
            </div>
            <div v-if="contact.notes">
              <dt class="text-gray-500">{{ t('contact.notes') }}</dt>
              <dd class="mt-0.5 whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-gray-700">{{ contact.notes }}</dd>
            </div>
          </dl>
          <p v-if="actionError" class="text-xs text-red-600">{{ actionError }}</p>
        </section>

        <section class="border-t border-gray-100 pt-3">
          <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('sidebar.tasksHeading') }}</h3>
          <TaskForm :on-submit="(payload) => crm.createTask(contact, payload)" />
          <div class="mt-2">
            <TaskList :tasks="contact.tasks ?? []" :empty-text="t('sidebar.noTasks')" />
          </div>
        </section>

        <section class="flex items-center justify-between border-t border-gray-100 pt-3">
          <button type="button" class="gcrm-btn-ghost px-2 text-xs" @click="gmail.openDashboard()">{{ t('sidebar.openDashboard') }}</button>
          <button type="button" class="gcrm-btn-danger px-2 text-xs" :disabled="deleting" @click="removeContact">
            {{ t('sidebar.deleteContact') }}
          </button>
        </section>
      </template>
    </template>

    <div class="flex justify-end border-t border-gray-100 pt-2">
      <LanguageSwitch />
    </div>
  </div>
</template>

<script setup>
import { computed, inject, ref, useId, watch } from 'vue'
import { CONTACT_STATUSES } from '@/lib/config.js'
import { t } from '@/lib/i18n.js'
import { useCrmSession } from '@/lib/useCrmSession.js'
import AlertMessage from '@/components/AlertMessage.vue'
import Avatar from '@/components/Avatar.vue'
import ContactForm from '@/components/ContactForm.vue'
import LanguageSwitch from '@/components/LanguageSwitch.vue'
import LicenseNotice from '@/components/LicenseNotice.vue'
import LoginForm from '@/components/LoginForm.vue'
import Spinner from '@/components/Spinner.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import TaskForm from '@/components/TaskForm.vue'
import TaskList from '@/components/TaskList.vue'

const props = defineProps({
  /** Counterparties extracted from the ThreadView by the content script, most relevant first. */
  participants: { type: Array, default: () => [] },
  /** True while the content script is still reading the thread. */
  resolving: { type: Boolean, default: false },
})

const gmail = inject('gmail')
const uid = useId()
const { auth, crm } = useCrmSession()

const selectedEmail = ref(null)
const creating = ref(false)
const editing = ref(false)
const statusSaving = ref(false)
const deleting = ref(false)
const actionError = ref('')

const activeEmail = computed(() => selectedEmail.value ?? props.participants[0]?.email ?? null)
// The select must show the default (first) participant even before the user picks one.
const participantModel = computed({
  get: () => activeEmail.value,
  set: (email) => {
    selectedEmail.value = email
  },
})
const activeName = computed(() => props.participants.find((p) => p.email === activeEmail.value)?.name ?? '')
const lookup = computed(() => crm.lookups[activeEmail.value] ?? { status: 'loading', contact: null, error: null })
const contact = computed(() => lookup.value.contact)

const canFetch = computed(() => auth.ready && auth.isAuthenticated && auth.hasValidLicense && Boolean(activeEmail.value))

watch(
  () => props.participants,
  (participants) => {
    if (!participants.some((p) => p.email === selectedEmail.value)) selectedEmail.value = null
  },
)

watch(
  [canFetch, activeEmail],
  ([ready]) => {
    creating.value = false
    editing.value = false
    actionError.value = ''
    if (ready) crm.lookupContact(activeEmail.value).catch(() => {}) // error state is rendered from the store
  },
  { immediate: true },
)

function reload() {
  if (activeEmail.value) crm.lookupContact(activeEmail.value, { force: true }).catch(() => {})
}

function onContactSaved(saved) {
  editing.value = false
  // Email changed -> follow the contact under its new address.
  if (saved.email !== activeEmail.value) selectedEmail.value = saved.email
}

async function changeStatus(status) {
  statusSaving.value = true
  actionError.value = ''
  try {
    await crm.updateContact(contact.value, { status })
  } catch (error) {
    actionError.value = error.message
  } finally {
    statusSaving.value = false
  }
}

async function removeContact() {
  if (!window.confirm(t('sidebar.confirmDelete', { name: contact.value.name || contact.value.email }))) return
  deleting.value = true
  actionError.value = ''
  try {
    await crm.deleteContact(contact.value)
  } catch (error) {
    actionError.value = error.message
  } finally {
    deleting.value = false
  }
}
</script>
