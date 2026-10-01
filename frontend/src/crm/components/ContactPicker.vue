<template>
  <div class="relative">
    <div v-if="selected" class="gcrm-input flex items-center justify-between">
      <span class="truncate">{{ selected.name || selected.email }} <span class="text-xs text-gray-400">{{ selected.name ? selected.email : '' }}</span></span>
      <button type="button" class="ml-2 text-gray-400 hover:text-gray-700" :aria-label="t('common.clear')" @click="select(null)">
        <Icon icon="xmark" />
      </button>
    </div>
    <input
      v-else
      v-model="query"
      type="search"
      class="gcrm-input"
      :placeholder="t('crm.picker.placeholder')"
      @focus="open = true"
      @keydown.esc="open = false"
    />
    <ul
      v-if="open && !selected && (results.length || loading)"
      class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
    >
      <li v-if="loading" class="px-3 py-2 text-xs text-gray-400">{{ t('common.loading') }}</li>
      <li v-for="contact in results" :key="contact.id">
        <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-50" @mousedown.prevent="select(contact)">
          <span class="font-medium text-gray-900">{{ contact.name || contact.email }}</span>
          <span v-if="contact.name" class="block text-xs text-gray-500">{{ contact.email }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { api } from '@/crm/api.js'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'

const props = defineProps({
  modelValue: { type: Number, default: null },
  /** Initially selected contact object ({ id, name, email }), e.g. the focused client. */
  initial: { type: Object, default: null },
})
const emit = defineEmits(['update:modelValue'])

const query = ref('')
const results = ref([])
const loading = ref(false)
const open = ref(false)
const selected = ref(props.initial && props.initial.id === props.modelValue ? props.initial : null)

let timer
let requestId = 0
watch(query, (value) => {
  clearTimeout(timer)
  if (value.trim().length < 2) {
    results.value = []
    return
  }
  timer = setTimeout(async () => {
    const id = ++requestId
    loading.value = true
    try {
      const { data } = await api.get('/contacts', { params: { search: value.trim(), per_page: 8 } })
      if (id === requestId) results.value = data.data
    } catch {
      if (id === requestId) results.value = []
    } finally {
      if (id === requestId) loading.value = false
    }
  }, 250)
})

function select(contact) {
  selected.value = contact
  query.value = ''
  open.value = false
  emit('update:modelValue', contact?.id ?? null)
}
</script>
