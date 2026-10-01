<template>
  <div class="gcrm-root min-h-full bg-gray-50">
    <div class="mx-auto max-w-6xl space-y-5 p-6">
      <!-- Header -->
      <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-gray-900">CRM Dashboard</h1>
          <p v-if="auth.user" class="text-xs text-gray-500">
            {{ auth.user.email }}
            <template v-if="auth.hasValidLicense && auth.license?.expires_at"> · license valid until {{ formatDateTime(auth.license.expires_at) }}</template>
          </p>
        </div>
        <div v-if="auth.isAuthenticated" class="flex items-center gap-2">
          <button type="button" class="gcrm-btn-secondary" :disabled="refreshing" @click="refreshAll">
            <Spinner v-if="refreshing" size="xs" />
            Refresh
          </button>
          <button type="button" class="gcrm-btn-ghost" @click="auth.logout()">Sign out</button>
        </div>
      </header>

      <div v-if="!auth.ready" class="flex items-center gap-2 py-16 text-gray-500"><Spinner /> Loading…</div>

      <div v-else-if="!auth.isAuthenticated" class="gcrm-card mx-auto max-w-sm p-6 shadow-sm">
        <LoginForm />
      </div>

      <div v-else-if="!auth.hasValidLicense" class="mx-auto max-w-lg">
        <LicenseNotice @renewed="refreshAll" />
      </div>

      <template v-else>
        <!-- Summary cards -->
        <AlertMessage v-if="crm.summary.error" :message="crm.summary.error.message" retryable @retry="crm.fetchSummary()" />
        <section class="grid grid-cols-2 gap-3 md:grid-cols-5">
          <StatCard label="Clients" :value="summary?.contacts.total" :loading="!summary" />
          <StatCard label="Customers" :value="summary?.contacts.by_status.customer" :loading="!summary" />
          <StatCard label="Open tasks" :value="summary?.tasks.open" :loading="!summary" />
          <StatCard label="Overdue" :value="summary?.tasks.overdue" :loading="!summary" :tone="summary?.tasks.overdue ? 'danger' : 'default'" />
          <StatCard label="Due today" :value="summary?.tasks.due_today" :loading="!summary" :tone="summary?.tasks.due_today ? 'warning' : 'default'" />
        </section>

        <!-- flex-wrap (not viewport breakpoints): Gmail's nav/side panels shrink our area. -->
        <div class="flex flex-wrap items-start gap-5">
          <!-- Reminders -->
          <section class="gcrm-card min-w-0 flex-[1_1_300px] p-4">
            <h2 class="text-sm font-semibold text-gray-900">Reminders</h2>
            <nav class="mt-3 flex gap-1 rounded-md bg-gray-100 p-1 text-xs font-medium" role="tablist">
              <button
                v-for="tab in reminderTabs"
                :key="tab.key"
                type="button"
                role="tab"
                class="flex-1 rounded px-2 py-1 transition"
                :class="activeReminderTab === tab.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                :aria-selected="activeReminderTab === tab.key"
                @click="activeReminderTab = tab.key"
              >
                {{ tab.label }}
                <span v-if="summary" class="ml-0.5 text-gray-400">{{ tab.count }}</span>
              </button>
            </nav>
            <div class="mt-2">
              <div v-if="!summary && crm.summary.loading" class="space-y-2 py-2">
                <div v-for="i in 3" :key="i" class="h-8 animate-pulse rounded bg-gray-100" />
              </div>
              <TaskList
                v-else
                :tasks="summary?.reminders[activeReminderTab] ?? []"
                show-contact
                :removable="false"
                :empty-text="reminderEmptyText"
                @open-contact="openInGmail"
              />
            </div>
          </section>

          <!-- Clients -->
          <section class="gcrm-card min-w-0 flex-[2_1_760px] overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 p-4">
              <h2 class="mr-auto text-sm font-semibold text-gray-900">
                Clients
                <span v-if="crm.contactList.loaded" class="font-normal text-gray-400">({{ crm.contactList.meta.total }})</span>
              </h2>
              <input
                v-model="search"
                type="search"
                class="gcrm-input w-48"
                placeholder="Search name, email, phone"
                aria-label="Search clients"
              />
              <select v-model="statusFilter" class="gcrm-input w-36" aria-label="Filter by status">
                <option value="">All statuses</option>
                <option v-for="s in CONTACT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
              <button type="button" class="gcrm-btn-primary" @click="showCreate = !showCreate">
                {{ showCreate ? 'Close' : 'Add client' }}
              </button>
            </div>

            <div v-if="showCreate" class="border-b border-gray-100 bg-gray-50 p-4">
              <ContactForm
                :compact="false"
                :on-save="(payload) => crm.createContact(payload)"
                submit-label="Create client"
                @saved="showCreate = false"
                @cancel="showCreate = false"
              />
            </div>

            <AlertMessage
              v-if="crm.contactList.error"
              class="m-4"
              :message="crm.contactList.error.message"
              retryable
              @retry="crm.fetchContacts()"
            />

            <div class="relative overflow-x-auto">
              <div v-if="crm.contactList.loading && crm.contactList.loaded" class="absolute inset-0 z-10 bg-white/50" />
              <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500">
                  <tr>
                    <th class="px-4 py-2">Client</th>
                    <th class="px-4 py-2">Phone</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-center">Open tasks</th>
                    <th class="px-4 py-2">Updated</th>
                    <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                  <tr v-if="!crm.contactList.loaded && crm.contactList.loading">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500"><Spinner class="inline" /> Loading clients…</td>
                  </tr>
                  <tr v-else-if="!crm.contactList.items.length && crm.contactList.loaded">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                      {{ search || statusFilter ? 'No clients match your filters.' : 'No clients yet. Open an email and add the sender from the sidebar.' }}
                    </td>
                  </tr>
                  <tr v-for="contact in crm.contactList.items" :key="contact.id" class="hover:bg-gray-50">
                    <td class="max-w-xs px-4 py-2">
                      <button type="button" class="block max-w-full text-left" title="Show conversations in Gmail" @click="openInGmail(contact)">
                        <span class="block truncate font-medium text-gray-900 hover:text-indigo-600">{{ contact.name || '—' }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ contact.email }}</span>
                      </button>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ contact.phone || '—' }}</td>
                    <td class="px-4 py-2">
                      <select
                        :value="contact.status"
                        class="rounded-md border border-gray-200 bg-white py-1 pl-2 pr-7 text-xs focus:border-indigo-500 focus:outline-none"
                        :disabled="rowBusy[contact.id]"
                        :aria-label="`Status of ${contact.email}`"
                        @change="changeStatus(contact, $event.target.value)"
                      >
                        <option v-for="s in CONTACT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                      </select>
                    </td>
                    <td class="px-4 py-2 text-center">
                      <span
                        class="inline-flex min-w-[1.5rem] justify-center rounded-full px-1.5 text-xs font-medium"
                        :class="contact.open_tasks_count ? 'bg-indigo-50 text-indigo-700' : 'text-gray-400'"
                      >
                        {{ contact.open_tasks_count ?? 0 }}
                      </span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2 text-xs text-gray-500" :title="formatDateTime(contact.updated_at)">
                      {{ formatRelative(contact.updated_at) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                      <button
                        type="button"
                        class="gcrm-btn-danger px-2 py-1 text-xs"
                        :disabled="rowBusy[contact.id]"
                        @click="removeContact(contact)"
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p v-if="rowError" class="px-4 pt-2 text-xs text-red-600">{{ rowError }}</p>

            <footer
              v-if="crm.contactList.meta.last_page > 1"
              class="flex items-center justify-between border-t border-gray-100 px-4 py-2 text-xs text-gray-600"
            >
              <span>Page {{ crm.contactList.meta.current_page }} of {{ crm.contactList.meta.last_page }}</span>
              <div class="flex gap-2">
                <button type="button" class="gcrm-btn-secondary px-2 py-1 text-xs" :disabled="page <= 1 || crm.contactList.loading" @click="page--">
                  Previous
                </button>
                <button
                  type="button"
                  class="gcrm-btn-secondary px-2 py-1 text-xs"
                  :disabled="page >= crm.contactList.meta.last_page || crm.contactList.loading"
                  @click="page++"
                >
                  Next
                </button>
              </div>
            </footer>
          </section>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, inject, reactive, ref, watch } from 'vue'
import { CONTACT_STATUSES } from '@/lib/config.js'
import { formatDateTime, formatRelative } from '@/lib/format.js'
import { useCrmSession } from '@/lib/useCrmSession.js'
import AlertMessage from '@/components/AlertMessage.vue'
import ContactForm from '@/components/ContactForm.vue'
import LicenseNotice from '@/components/LicenseNotice.vue'
import LoginForm from '@/components/LoginForm.vue'
import Spinner from '@/components/Spinner.vue'
import StatCard from '@/components/StatCard.vue'
import TaskList from '@/components/TaskList.vue'

const gmail = inject('gmail')
const { auth, crm } = useCrmSession()

const search = ref(crm.contactList.filters.search)
const statusFilter = ref(crm.contactList.filters.status)
const page = ref(1)
const showCreate = ref(false)
const refreshing = ref(false)
const activeReminderTab = ref('overdue')
const rowBusy = reactive({})
const rowError = ref('')

const summary = computed(() => crm.summary.data)

const reminderTabs = computed(() => [
  { key: 'overdue', label: 'Overdue', count: summary.value?.reminders.overdue.length ?? 0 },
  { key: 'today', label: 'Today', count: summary.value?.reminders.today.length ?? 0 },
  { key: 'upcoming', label: '7 days', count: summary.value?.reminders.upcoming.length ?? 0 },
])

const reminderEmptyText = computed(
  () =>
    ({
      overdue: 'Nothing overdue. Nice work!',
      today: 'Nothing else due today.',
      upcoming: 'No reminders in the next 7 days.',
    })[activeReminderTab.value],
)

const canFetch = computed(() => auth.ready && auth.isAuthenticated && auth.hasValidLicense)

async function refreshAll() {
  refreshing.value = true
  try {
    await Promise.all([crm.fetchSummary(), crm.fetchContacts({ page: page.value })])
  } finally {
    refreshing.value = false
  }
}

watch(
  canFetch,
  (ready) => {
    if (ready) refreshAll()
  },
  { immediate: true },
)

// Jump to the most relevant reminder tab once numbers first arrive.
watch(summary, (data, previous) => {
  if (!previous && data && !data.reminders.overdue.length) {
    activeReminderTab.value = data.reminders.today.length ? 'today' : 'upcoming'
  }
})

// Debounced search; status/page changes fetch immediately.
let searchTimer
watch(search, (value) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    crm.fetchContacts({ search: value.trim(), page: 1 })
  }, 300)
})

watch(statusFilter, (value) => {
  page.value = 1
  crm.fetchContacts({ status: value, page: 1 })
})

watch(page, (value) => {
  if (value !== crm.contactList.filters.page) crm.fetchContacts({ page: value })
})

async function withRow(contact, action) {
  rowBusy[contact.id] = true
  rowError.value = ''
  try {
    await action()
  } catch (error) {
    rowError.value = error.message
  } finally {
    delete rowBusy[contact.id]
  }
}

const changeStatus = (contact, status) => withRow(contact, () => crm.updateContact(contact, { status }))

function removeContact(contact) {
  if (!window.confirm(`Delete ${contact.name || contact.email} and all their tasks?`)) return
  return withRow(contact, async () => {
    await crm.deleteContact(contact)
    // Step back if we just emptied the last page.
    if (!crm.contactList.items.length && page.value > 1) page.value--
    else crm.fetchContacts()
  })
}

function openInGmail(contact) {
  gmail.searchEmail(contact.email)
}
</script>
