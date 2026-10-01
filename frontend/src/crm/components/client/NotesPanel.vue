<template>
  <!-- Latest notes, right under "Dane kontaktowe" (they are also on the timeline). -->
  <section>
    <div class="mb-3 flex items-center justify-between">
      <h3 class="text-sm font-bold uppercase tracking-wide text-gray-800">
        <Icon icon="note-sticky" class="mr-2 text-yellow-500" />{{ t('crm.notes.title') }}
        <span v-if="count" class="ml-1 rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-700">{{ count }}</span>
      </h3>
      <button v-if="count > notes.length" type="button" class="text-xs font-medium text-primary hover:underline" @click="showAll">
        {{ t('crm.notes.showAll', { count }) }}
      </button>
    </div>

    <div v-if="notes.length" class="space-y-2">
      <div v-for="note in notes" :key="note.id" class="group rounded-lg border border-yellow-100 bg-yellow-50 p-3 text-sm shadow-sm">
        <form v-if="editing === note.id" class="space-y-2" @submit.prevent="save(note)">
          <textarea v-model="draft" rows="3" class="w-full resize-none rounded border border-yellow-200 bg-white p-2 text-sm focus:border-primary focus:outline-none" />
          <div class="flex justify-end gap-2">
            <button type="button" class="gcrm-btn-ghost px-2 py-1 text-xs" @click="editing = null">{{ t('common.cancel') }}</button>
            <button type="submit" class="gcrm-btn-primary px-2 py-1 text-xs" :disabled="!draft.trim()">{{ t('common.save') }}</button>
          </div>
        </form>
        <template v-else>
          <p class="whitespace-pre-wrap text-yellow-900">{{ note.body }}</p>
          <p class="mt-1 flex items-center justify-between text-xs text-yellow-700/80">
            <span>{{ note.author?.name ?? '—' }} · {{ formatCalendar(note.occurred_at) }}</span>
            <span v-if="note.author?.id === auth.user?.id" class="hidden gap-2 group-hover:flex">
              <button type="button" class="hover:text-primary" :aria-label="t('common.edit')" @click="edit(note)"><Icon icon="pen" /></button>
              <button type="button" class="hover:text-red-600" :aria-label="t('common.delete')" @click="remove(note)"><Icon icon="trash" /></button>
            </span>
          </p>
        </template>
      </div>
    </div>
    <p v-else class="text-xs text-gray-500">{{ t('crm.notes.empty') }}</p>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { formatCalendar } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { useAuthStore } from '@/crm/stores/auth.js'
import { useClientStore } from '@/crm/stores/client.js'

const client = useClientStore()
const auth = useAuthStore()
const notes = computed(() => client.contact?.recent_notes ?? [])
const count = computed(() => client.contact?.notes_count ?? notes.value.length)
const editing = ref(null)
const draft = ref('')

function edit(note) {
  editing.value = note.id
  draft.value = note.body
}

async function save(note) {
  try {
    await client.updateNote(note.id, draft.value.trim())
    editing.value = null
  } catch (e) {
    window.alert(e.message)
  }
}

async function remove(note) {
  if (window.confirm(t('crm.timeline.confirmDeleteNote'))) await client.deleteNote(note.id)
}

/** All notes = the timeline filtered to notes. */
function showAll() {
  client.loadTimeline({ type: 'note' })
}
</script>
