<template>
  <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <header class="border-b border-gray-200 bg-gray-50/50 px-5 py-4">
      <h3 class="font-bold text-gray-800">{{ title }}</h3>
      <p v-if="description" class="mt-0.5 text-xs text-gray-500">{{ description }}</p>
    </header>

    <ul class="divide-y divide-gray-100">
      <li v-for="(item, index) in items" :key="item.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
        <div class="flex flex-col text-gray-300">
          <button type="button" class="hover:text-primary disabled:opacity-30" :disabled="index === 0" :aria-label="t('settings.moveUp')" @click="move(index, -1)"><Icon icon="arrow-up" class="text-xs" /></button>
          <button type="button" class="hover:text-primary disabled:opacity-30" :disabled="index === items.length - 1" :aria-label="t('settings.moveDown')" @click="move(index, 1)"><Icon icon="arrow-down" class="text-xs" /></button>
        </div>

        <template v-if="options.color">
          <span class="h-4 w-4 shrink-0 rounded-full" :class="tone(item.color).swatch" />
          <select :value="item.color" class="gcrm-input w-36 py-1" :aria-label="t('settings.color')" @change="save(item, { color: $event.target.value })">
            <option v-for="color in COLOR_NAMES" :key="color" :value="color">{{ t(`settings.colors.${color}`) }}</option>
          </select>
        </template>

        <template v-if="options.icon">
          <Icon :icon="item.icon || 'list-check'" class="w-4 text-gray-500" />
          <select :value="item.icon ?? ''" class="gcrm-input w-36 py-1" :aria-label="t('settings.icon')" @change="save(item, { icon: $event.target.value || null })">
            <option value="">—</option>
            <option v-for="icon in DICTIONARY_ICONS" :key="icon" :value="icon">{{ icon }}</option>
          </select>
        </template>

        <input
          :value="item[labelField]"
          class="gcrm-input min-w-[160px] flex-1 py-1 font-medium"
          :aria-label="t('settings.name')"
          maxlength="100"
          @change="rename(item, $event)"
          @keydown.enter="$event.target.blur()"
        />

        <select v-if="options.type" :value="item.type" class="gcrm-input w-32 py-1" :aria-label="t('crm.form.type')" @change="save(item, { type: $event.target.value })">
          <option v-for="type in ['text', 'number', 'date', 'boolean']" :key="type" :value="type">{{ t(`crm.fieldTypes.${type}`) }}</option>
        </select>

        <label v-if="options.isDefault" class="flex items-center gap-1 text-xs text-gray-600" :title="t('settings.defaultHint')">
          <input type="radio" :name="`${dictionary}-default`" class="accent-primary" :checked="item.is_default" @change="save(item, { is_default: true })" />
          {{ t('settings.default') }}
        </label>

        <!-- Business correspondence: everyone keeps a private card, emails shared one by one. -->
        <label v-if="options.isPrivate" class="flex items-center gap-1 whitespace-nowrap text-xs text-gray-600" :title="t('settings.categories.privateHint')">
          <input type="checkbox" class="accent-primary" :checked="item.is_private" @change="togglePrivate(item, $event)" />
          <Icon icon="lock" class="text-gray-400" /> {{ t('settings.categories.private') }}
        </label>

        <span v-if="item.usage_count !== undefined" class="w-24 whitespace-nowrap text-right text-xs text-gray-400">{{ t('settings.usage', { count: item.usage_count }) }}</span>

        <div class="ml-auto flex items-center gap-2">
          <template v-if="removing === item.id">
            <select v-model="moveTo" class="gcrm-input w-40 py-1 text-xs" :aria-label="t('settings.moveTo')">
              <option :value="null" disabled>{{ t('settings.moveTo') }}</option>
              <option v-for="other in items.filter((o) => o.id !== item.id)" :key="other.id" :value="other.key">{{ other.name }}</option>
            </select>
            <button type="button" class="gcrm-btn bg-red-600 px-2 py-1 text-xs text-white hover:bg-red-700" :disabled="!moveTo" @click="remove(item)">{{ t('settings.moveAndDelete') }}</button>
            <button type="button" class="gcrm-btn-ghost px-2 py-1 text-xs" @click="removing = null">{{ t('common.cancel') }}</button>
          </template>
          <button v-else type="button" class="p-1 text-gray-300 hover:text-red-600" :aria-label="t('common.delete')" @click="askRemove(item)"><Icon icon="trash" /></button>
        </div>
      </li>
      <li v-if="!items.length" class="px-5 py-4 text-sm text-gray-500">{{ t('settings.empty') }}</li>
    </ul>

    <form class="flex flex-wrap items-center gap-2 border-t border-gray-100 px-5 py-3" @submit.prevent="add">
      <input v-model.trim="newName" class="gcrm-input min-w-[200px] flex-1" :placeholder="addPlaceholder" maxlength="100" />
      <button type="submit" class="gcrm-btn-primary" :disabled="!newName || busy"><Icon icon="plus" /> {{ t('common.add') }}</button>
    </form>
    <p v-if="error" class="px-5 pb-3 text-xs text-red-600">{{ error }}</p>
  </section>
</template>

<script setup>
import { ref } from 'vue'
import { Icon } from '@/shared/icons.js'
import { COLOR_NAMES, DICTIONARY_ICONS, tone } from '@/shared/lib/colors.js'
import { t } from '@/shared/lib/i18n.js'
import { useSettingsStore } from '@/crm/stores/settings.js'

const props = defineProps({
  /** API name: categories | statuses | task-categories | field-templates */
  dictionary: { type: String, required: true },
  items: { type: Array, required: true },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  addPlaceholder: { type: String, default: '' },
  /** { color, icon, isDefault, isPrivate, type, moveRequired } */
  options: { type: Object, default: () => ({}) },
  labelField: { type: String, default: 'name' },
})

const settings = useSettingsStore()
const newName = ref('')
const busy = ref(false)
const error = ref('')
const removing = ref(null)
const moveTo = ref(null)

async function run(action) {
  busy.value = true
  error.value = ''
  try {
    await action()
    return true
  } catch (e) {
    error.value = Object.values(e.fieldErrors ?? {})[0] ?? e.message
    return false
  } finally {
    busy.value = false
  }
}

const save = (item, patch) => run(() => settings.update(props.dictionary, item.id, patch))

async function togglePrivate(item, event) {
  const value = event.target.checked
  const question = value ? t('settings.categories.confirmPrivate', { name: item.name }) : t('settings.categories.confirmShared', { name: item.name })
  if (!window.confirm(question) || !(await save(item, { is_private: value }))) event.target.checked = item.is_private
}

async function rename(item, event) {
  const value = event.target.value.trim()
  if (!value || value === item[props.labelField]) {
    event.target.value = item[props.labelField]
    return
  }
  if (!(await save(item, { [props.labelField]: value }))) event.target.value = item[props.labelField]
}

async function add() {
  if (await run(() => settings.create(props.dictionary, { [props.labelField]: newName.value }))) newName.value = ''
}

function move(index, delta) {
  const ids = props.items.map((item) => item.id)
  ;[ids[index], ids[index + delta]] = [ids[index + delta], ids[index]]
  run(() => settings.reorder(props.dictionary, ids))
}

/** Statuses / task categories in use must be moved to another entry first. */
function askRemove(item) {
  if (props.options.moveRequired && item.usage_count > 0) {
    removing.value = item.id
    moveTo.value = null
    return
  }
  if (window.confirm(t('settings.confirmDelete', { name: item[props.labelField] }))) remove(item)
}

async function remove(item) {
  if (await run(() => settings.remove(props.dictionary, item.id, { moveTo: moveTo.value }))) removing.value = null
}
</script>
