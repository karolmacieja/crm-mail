<template>
  <!-- Sender of the focused email is not in the CRM yet. -->
  <div class="space-y-4">
    <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-center text-sm text-gray-600">
      <Icon icon="circle-exclamation" class="mb-1 text-lg text-gray-400" />
      <p>{{ t('crm.client.notInCrm') }}</p>
    </div>
    <!-- Colleagues keep this contact on their own private cards (business correspondence). -->
    <div v-if="personal" class="rounded-lg border border-indigo-100 bg-indigo-50 p-3 text-xs text-indigo-900">
      <p class="font-medium"><Icon icon="lock" class="mr-1" />{{ t('crm.personal.othersHaveCards', { category: personal.category?.name ?? '—' }) }}</p>
      <p class="mt-1">{{ t('crm.personal.ownCardHint', { count: personal.team_emails }) }}</p>
    </div>
    <form class="space-y-2.5" @submit.prevent="create">
      <label class="block"><span class="gcrm-label">{{ t('contact.name') }}</span><input v-model.trim="form.name" class="gcrm-input" /></label>
      <div class="grid grid-cols-2 gap-2.5">
        <label><span class="gcrm-label">{{ t('crm.client.company') }}</span><input v-model.trim="form.company" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('contact.phone') }}</span><input v-model.trim="form.phone" type="tel" class="gcrm-input" /></label>
      </div>
      <label class="block">
        <span class="gcrm-label">{{ t('crm.client.category') }}</span>
        <select v-model="form.category_id" class="gcrm-input" :disabled="Boolean(personal?.category)">
          <option :value="null">—</option>
          <option v-for="c in settings.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.is_client" type="checkbox" class="accent-primary" /> {{ t('crm.client.isClient') }}</label>
      <p v-for="message in errors" :key="message" class="text-xs text-red-600">{{ message }}</p>
      <button type="submit" class="gcrm-btn-primary w-full" :disabled="saving"><Icon icon="plus" /> {{ t('crm.client.addToCrm') }}</button>
    </form>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useContactsStore } from '@/crm/stores/contacts.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const client = useClientStore()
const contacts = useContactsStore()
const settings = useSettingsStore()
const personal = client.personalInfo
const form = reactive({ name: client.context?.name ?? '', company: '', phone: '', category_id: personal?.category?.id ?? null, is_client: true })
const saving = ref(false)
const errors = ref([])

onMounted(() => settings.load())

async function create() {
  saving.value = true
  errors.value = []
  try {
    await client.createFromContext({ ...form, name: form.name || null, company: form.company || null, phone: form.phone || null })
  } catch (e) {
    errors.value = Object.keys(e.fieldErrors ?? {}).length ? Object.values(e.fieldErrors) : [e.message]
  } finally {
    saving.value = false
  }
}
</script>
