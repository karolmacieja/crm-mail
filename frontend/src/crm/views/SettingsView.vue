<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-800">{{ t('settings.title') }}</h2>
    </div>

    <nav class="mb-6 flex flex-wrap gap-1 rounded-lg bg-gray-100 p-1 text-sm font-medium" role="tablist">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        role="tab"
        class="rounded px-3 py-1.5 transition"
        :class="active === tab.key ? 'border border-gray-200 bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
        :aria-selected="active === tab.key"
        @click="active = tab.key"
      >
        <Icon :icon="tab.icon" class="mr-1.5" />{{ t(tab.label) }}
      </button>
    </nav>

    <AlertMessage v-if="settings.error" class="mb-6" :message="settings.error.message" retryable @retry="settings.load({ force: true })" />
    <div v-if="!settings.loaded" class="h-40 animate-pulse rounded-xl bg-gray-100" />

    <template v-else>
      <DictionaryEditor
        v-if="active === 'categories'"
        dictionary="categories"
        :items="settings.categories"
        :title="t('settings.categories.title')"
        :description="t('settings.categories.description')"
        :add-placeholder="t('settings.categories.add')"
        :options="{ color: true, icon: true }"
      />
      <DictionaryEditor
        v-else-if="active === 'statuses'"
        dictionary="statuses"
        :items="settings.statuses"
        :title="t('settings.statuses.title')"
        :description="t('settings.statuses.description')"
        :add-placeholder="t('settings.statuses.add')"
        :options="{ color: true, isDefault: true, moveRequired: true }"
      />
      <DictionaryEditor
        v-else-if="active === 'task-categories'"
        dictionary="task-categories"
        :items="settings.taskCategories"
        :title="t('settings.taskCategories.title')"
        :description="t('settings.taskCategories.description')"
        :add-placeholder="t('settings.taskCategories.add')"
        :options="{ color: true, icon: true, moveRequired: true }"
      />
      <DictionaryEditor
        v-else-if="active === 'field-templates'"
        dictionary="field-templates"
        label-field="label"
        :items="settings.fieldTemplates"
        :title="t('settings.fieldTemplates.title')"
        :description="t('settings.fieldTemplates.description')"
        :add-placeholder="t('settings.fieldTemplates.add')"
        :options="{ type: true }"
      />
      <PreferencesPanel v-else-if="active === 'preferences'" />
      <CalendarPanel v-else-if="active === 'calendar'" />
    </template>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AlertMessage from '@/shared/components/AlertMessage.vue'
import { Icon } from '@/shared/icons.js'
import { t } from '@/shared/lib/i18n.js'
import CalendarPanel from '@/crm/components/settings/CalendarPanel.vue'
import DictionaryEditor from '@/crm/components/settings/DictionaryEditor.vue'
import PreferencesPanel from '@/crm/components/settings/PreferencesPanel.vue'
import { useSettingsStore } from '@/crm/stores/settings.js'

const settings = useSettingsStore()
const active = ref('categories')

const tabs = [
  { key: 'categories', label: 'settings.tabs.categories', icon: 'briefcase' },
  { key: 'statuses', label: 'settings.tabs.statuses', icon: 'star' },
  { key: 'task-categories', label: 'settings.tabs.taskCategories', icon: 'list-check' },
  { key: 'field-templates', label: 'settings.tabs.fieldTemplates', icon: 'pen' },
  { key: 'preferences', label: 'settings.tabs.preferences', icon: 'user' },
  { key: 'calendar', label: 'settings.tabs.calendar', icon: 'calendar-check' },
]

onMounted(() => settings.load({ force: true }))
</script>
