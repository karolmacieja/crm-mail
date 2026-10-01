<template>
  <section>
    <div class="mb-3 flex items-center justify-between">
      <h3 class="text-sm font-bold uppercase tracking-wide text-gray-800">{{ t('crm.client.details') }}</h3>
      <div class="flex gap-3">
        <button v-if="!editing" type="button" class="text-xs font-medium text-gray-500 hover:text-primary" @click="startEdit">
          <Icon icon="pen" /> {{ t('common.edit') }}
        </button>
        <button type="button" class="text-xs font-medium text-primary hover:underline" @click="addingField = !addingField">
          <Icon icon="plus" /> {{ t('crm.client.addField') }}
        </button>
      </div>
    </div>

    <!-- Edit base data -->
    <form v-if="editing" class="space-y-2.5" @submit.prevent="save">
      <div class="grid grid-cols-2 gap-2.5">
        <label class="col-span-2"><span class="gcrm-label">{{ t('contact.name') }}</span><input v-model.trim="form.name" class="gcrm-input" /></label>
        <label><span class="gcrm-label">{{ t('contact.email') }}</span><input v-model.trim="form.email" type="email" class="gcrm-input" :class="{ 'gcrm-input-error': errors.email }" /></label>
        <label><span class="gcrm-label">{{ t('contact.phone') }}</span><input v-model.trim="form.phone" type="tel" class="gcrm-input" :class="{ 'gcrm-input-error': errors.phone }" /></label>
        <label class="col-span-2"><span class="gcrm-label">{{ t('crm.client.company') }}</span><input v-model.trim="form.company" class="gcrm-input" /></label>
        <label>
          <span class="gcrm-label">{{ t('crm.client.category') }}</span>
          <select v-model="form.category_id" class="gcrm-input">
            <option :value="null">—</option>
            <option v-for="c in contacts.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label>
          <span class="gcrm-label">{{ t('contact.status') }}</span>
          <select v-model="form.status" class="gcrm-input">
            <option v-for="s in CONTACT_STATUSES" :key="s.value" :value="s.value">{{ t(`status.${s.value}`) }}</option>
          </select>
        </label>
      </div>
      <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.is_client" type="checkbox" class="accent-primary" /> {{ t('crm.client.isClient') }}</label>
      <p v-for="message in Object.values(errors)" :key="message" class="text-xs text-red-600">{{ message }}</p>
      <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="gcrm-btn-ghost" @click="editing = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="gcrm-btn-primary" :disabled="saving">{{ t('common.save') }}</button>
      </div>
    </form>

    <!-- Read mode -->
    <dl v-else class="space-y-3">
      <Row icon="envelope" far :label="t('contact.email')">
        <a :href="`mailto:${contact.email}`" class="text-sm font-medium text-gray-900 hover:text-primary">{{ contact.email }}</a>
      </Row>
      <Row v-if="contact.phone" icon="phone" :label="t('contact.phone')">
        <a :href="`tel:${contact.phone}`" class="text-sm font-medium text-gray-900 hover:text-primary">{{ contact.phone }}</a>
      </Row>
      <Row v-if="contact.company" icon="briefcase" :label="t('crm.client.company')">
        <span class="text-sm font-medium text-gray-900">{{ contact.company }}</span>
      </Row>

      <!-- Custom fields ("Alergie" etc.) -->
      <div v-for="field in contact.custom_fields" :key="field.id" class="group flex items-start justify-between gap-3">
        <dt class="shrink-0 text-sm text-gray-500"><Icon icon="star" class="mr-1 w-4 text-gray-300" /> {{ field.label }}</dt>
        <dd v-if="editingField !== field.id" class="flex items-center gap-2 text-right">
          <span class="text-sm font-medium text-gray-900">{{ displayValue(field) }}</span>
          <button type="button" class="hidden text-gray-300 hover:text-primary group-hover:block" :aria-label="t('common.edit')" @click="editField(field)">
            <Icon icon="pen" class="text-xs" />
          </button>
          <button type="button" class="hidden text-gray-300 hover:text-red-600 group-hover:block" :aria-label="t('common.delete')" @click="removeField(field)">
            <Icon icon="trash" class="text-xs" />
          </button>
        </dd>
        <form v-else class="flex flex-1 gap-2" @submit.prevent="saveField(field)">
          <input v-model="fieldValue" :type="inputType(field.type)" class="gcrm-input py-1" />
          <button type="submit" class="gcrm-btn-primary px-2 py-1"><Icon icon="check" /></button>
          <button type="button" class="gcrm-btn-ghost px-2 py-1" @click="editingField = null"><Icon icon="xmark" /></button>
        </form>
      </div>
    </dl>

    <!-- New custom field -->
    <form v-if="addingField" class="mt-3 space-y-2 rounded-lg border border-dashed border-gray-300 p-3" @submit.prevent="addField">
      <div class="grid grid-cols-2 gap-2">
        <input v-model.trim="newField.label" class="gcrm-input" :placeholder="t('crm.client.fieldLabel')" required />
        <select v-model="newField.type" class="gcrm-input">
          <option v-for="type in ['text', 'number', 'date', 'boolean']" :key="type" :value="type">{{ t(`crm.fieldTypes.${type}`) }}</option>
        </select>
      </div>
      <select v-if="newField.type === 'boolean'" v-model="newField.value" class="gcrm-input">
        <option value="1">{{ t('common.yes') }}</option>
        <option value="0">{{ t('common.no') }}</option>
      </select>
      <input v-else v-model="newField.value" :type="inputType(newField.type)" class="gcrm-input" :placeholder="t('crm.client.fieldValue')" />
      <p v-if="fieldError" class="text-xs text-red-600">{{ fieldError }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="gcrm-btn-ghost" @click="addingField = false">{{ t('common.cancel') }}</button>
        <button type="submit" class="gcrm-btn-primary" :disabled="!newField.label">{{ t('common.add') }}</button>
      </div>
    </form>
  </section>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { CONTACT_STATUSES } from '@/shared/lib/config.js'
import { formatReservation } from '@/shared/lib/format.js'
import { t } from '@/shared/lib/i18n.js'
import { useClientStore } from '@/crm/stores/client.js'
import { useContactsStore } from '@/crm/stores/contacts.js'

const Row = defineComponent({
  props: { icon: String, far: Boolean, label: String },
  setup(props, { slots }) {
    return () =>
      h('div', { class: 'flex items-start justify-between gap-3' }, [
        h('dt', { class: 'shrink-0 text-sm text-gray-500' }, [
          h(Icon, { icon: props.far ? ['far', props.icon] : props.icon, class: 'mr-1 w-4' }),
          ` ${props.label}`,
        ]),
        h('dd', { class: 'min-w-0 truncate text-right' }, slots.default?.()),
      ])
  },
})

const client = useClientStore()
const contacts = useContactsStore()
const contact = computed(() => client.contact)

const editing = ref(false)
const saving = ref(false)
const error = ref('')
const errors = ref({})
const form = reactive({})

function startEdit() {
  contacts.loadCategories()
  const c = contact.value
  Object.assign(form, { name: c.name ?? '', email: c.email, phone: c.phone ?? '', company: c.company ?? '', category_id: c.category_id, status: c.status, is_client: c.is_client })
  errors.value = {}
  error.value = ''
  editing.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  error.value = ''
  try {
    await client.updateContact({ ...form, name: form.name || null, phone: form.phone || null, company: form.company || null })
    editing.value = false
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    if (!Object.keys(errors.value).length) error.value = e.message
  } finally {
    saving.value = false
  }
}

// ---- custom fields
const addingField = ref(false)
const newField = reactive({ label: '', type: 'text', value: '' })
const fieldError = ref('')
const editingField = ref(null)
const fieldValue = ref('')

const inputType = (type) => ({ number: 'number', date: 'date' })[type] ?? 'text'

function displayValue(field) {
  if (field.value === null || field.value === '') return '—'
  if (field.type === 'boolean') return field.value ? t('common.yes') : t('common.no')
  if (field.type === 'date') return formatReservation(field.value)
  return field.value
}

async function addField() {
  fieldError.value = ''
  try {
    await client.addCustomField({ ...newField, value: newField.value === '' ? null : newField.value })
    Object.assign(newField, { label: '', type: 'text', value: '' })
    addingField.value = false
  } catch (e) {
    fieldError.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
  }
}

function editField(field) {
  editingField.value = field.id
  fieldValue.value = field.value ?? ''
}

async function saveField(field) {
  try {
    await client.updateCustomField(field.id, { value: fieldValue.value === '' ? null : fieldValue.value })
    editingField.value = null
  } catch (e) {
    window.alert(Object.values(e.fieldErrors ?? {})[0] ?? e.message)
  }
}

async function removeField(field) {
  if (window.confirm(t('crm.client.confirmDeleteField', { label: field.label }))) {
    await client.removeCustomField(field.id)
  }
}
</script>
