<template>
  <form class="space-y-2.5" novalidate @submit.prevent="submit">
    <AlertMessage v-if="error && !Object.keys(fieldErrors).length" :message="error" />

    <div :class="compact ? 'space-y-2.5' : 'grid grid-cols-2 gap-3'">
      <div>
        <label class="gcrm-label" :for="`${uid}-name`">{{ t('contact.name') }}</label>
        <input :id="`${uid}-name`" v-model.trim="form.name" class="gcrm-input" :class="{ 'gcrm-input-error': fieldErrors.name }" maxlength="255" />
        <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
      </div>

      <div>
        <label class="gcrm-label" :for="`${uid}-email`">{{ t('contact.email') }}</label>
        <input
          :id="`${uid}-email`"
          v-model.trim="form.email"
          type="email"
          required
          class="gcrm-input"
          :class="{ 'gcrm-input-error': fieldErrors.email }"
          maxlength="255"
        />
        <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
      </div>

      <div>
        <label class="gcrm-label" :for="`${uid}-phone`">{{ t('contact.phone') }}</label>
        <input
          :id="`${uid}-phone`"
          v-model.trim="form.phone"
          type="tel"
          class="gcrm-input"
          :class="{ 'gcrm-input-error': fieldErrors.phone }"
          maxlength="50"
          :placeholder="t('contact.phonePlaceholder')"
        />
        <p v-if="fieldErrors.phone" class="mt-1 text-xs text-red-600">{{ fieldErrors.phone }}</p>
      </div>

      <div>
        <label class="gcrm-label" :for="`${uid}-status`">{{ t('contact.status') }}</label>
        <select :id="`${uid}-status`" v-model="form.status" class="gcrm-input" :class="{ 'gcrm-input-error': fieldErrors.status }">
          <option v-for="status in CONTACT_STATUSES" :key="status.value" :value="status.value">{{ t(`status.${status.value}`) }}</option>
        </select>
        <p v-if="fieldErrors.status" class="mt-1 text-xs text-red-600">{{ fieldErrors.status }}</p>
      </div>
    </div>

    <div>
      <label class="gcrm-label" :for="`${uid}-notes`">{{ t('contact.notes') }}</label>
      <textarea
        :id="`${uid}-notes`"
        v-model="form.notes"
        rows="3"
        class="gcrm-input resize-y"
        :class="{ 'gcrm-input-error': fieldErrors.notes }"
        maxlength="10000"
      />
      <p v-if="fieldErrors.notes" class="mt-1 text-xs text-red-600">{{ fieldErrors.notes }}</p>
    </div>

    <div class="flex justify-end gap-2 pt-1">
      <button v-if="cancellable" type="button" class="gcrm-btn-ghost" :disabled="saving" @click="$emit('cancel')">{{ t('common.cancel') }}</button>
      <button type="submit" class="gcrm-btn-primary" :disabled="saving || !form.email">
        <Spinner v-if="saving" size="xs" />
        {{ submitLabel || t('common.save') }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref, useId, watch } from 'vue'
import { CONTACT_STATUSES } from '@/shared/lib/config.js'
import { t } from '@/shared/lib/i18n.js'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import Spinner from '@/shared/components/Spinner.vue'

const props = defineProps({
  /** Existing contact or initial values ({ email, name }) for a new one. */
  initial: { type: Object, default: () => ({}) },
  /** Async (payload) => void. Throws ApiError on failure. */
  onSave: { type: Function, required: true },
  submitLabel: { type: String, default: '' },
  cancellable: { type: Boolean, default: true },
  compact: { type: Boolean, default: true },
})
const emit = defineEmits(['cancel', 'saved'])
const uid = useId()

const form = reactive({ name: '', email: '', phone: '', status: 'lead', notes: '' })
const saving = ref(false)
const error = ref('')
const fieldErrors = ref({})

watch(
  () => props.initial,
  (initial) => {
    Object.assign(form, {
      name: initial?.name ?? '',
      email: initial?.email ?? '',
      phone: initial?.phone ?? '',
      status: initial?.status ?? 'lead',
      notes: initial?.notes ?? '',
    })
  },
  { immediate: true },
)

async function submit() {
  saving.value = true
  error.value = ''
  fieldErrors.value = {}

  try {
    const result = await props.onSave({
      name: form.name || null,
      email: form.email,
      phone: form.phone || null,
      status: form.status,
      notes: form.notes?.trim() ? form.notes : null,
    })
    emit('saved', result)
  } catch (apiError) {
    error.value = apiError.message
    fieldErrors.value = apiError.fieldErrors ?? {}
  } finally {
    saving.value = false
  }
}
</script>
