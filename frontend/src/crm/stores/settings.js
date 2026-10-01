import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, toApiError } from '@/crm/api.js'

/**
 * Settings panel state:
 *  - the restaurant's dictionaries (contact categories, statuses, task
 *    categories, custom field templates) used across the whole CRM;
 *  - the signed-in person's preferences (defaults, calendar, Google).
 */
export const useSettingsStore = defineStore('settings', () => {
  const dictionaries = ref({ categories: [], statuses: [], task_categories: [], field_templates: [], colors: [] })
  const preferences = ref(null)
  const loaded = ref(false)
  const error = ref(null)
  let loading = null

  const statuses = computed(() => dictionaries.value.statuses)
  const taskCategories = computed(() => dictionaries.value.task_categories)
  const categories = computed(() => dictionaries.value.categories)
  const fieldTemplates = computed(() => dictionaries.value.field_templates)

  const statusByKey = (key) => statuses.value.find((s) => s.key === key) ?? null
  const taskCategoryByKey = (key) => taskCategories.value.find((c) => c.key === key) ?? null

  /** Loaded once per session; force after changes made elsewhere. */
  function load({ force = false } = {}) {
    if (loaded.value && !force) return Promise.resolve()
    loading ??= Promise.all([api.get('/settings'), api.get('/me/preferences')])
      .then(([settings, prefs]) => {
        dictionaries.value = settings.data.data
        preferences.value = prefs.data.data
        loaded.value = true
        error.value = null
      })
      .catch((e) => {
        error.value = toApiError(e)
      })
      .finally(() => {
        loading = null
      })
    return loading
  }

  const listKey = (dictionary) => dictionary.replace('-', '_')

  async function call(request) {
    try {
      return await request()
    } catch (e) {
      throw toApiError(e)
    }
  }

  async function create(dictionary, payload) {
    const { data } = await call(() => api.post(`/settings/${dictionary}`, payload))
    await load({ force: true }) // default flags / usage counts may change elsewhere in the list
    return data.data
  }

  async function update(dictionary, id, payload) {
    const { data } = await call(() => api.patch(`/settings/${dictionary}/${id}`, payload))
    if (payload.is_default) await load({ force: true })
    else {
      const list = dictionaries.value[listKey(dictionary)]
      const index = list.findIndex((item) => item.id === id)
      if (index !== -1) list[index] = { ...list[index], ...data.data }
    }
    return data.data
  }

  /** Statuses / task categories in use need `moveTo` (another key). */
  async function remove(dictionary, id, { moveTo } = {}) {
    await call(() => api.delete(`/settings/${dictionary}/${id}`, { params: moveTo ? { move_to: moveTo } : {} }))
    await load({ force: true })
  }

  async function reorder(dictionary, ids) {
    const list = dictionaries.value[listKey(dictionary)]
    // Optimistic: reorder locally, the API confirms.
    dictionaries.value[listKey(dictionary)] = ids.map((id) => list.find((item) => item.id === id))
    const { data } = await call(() => api.post(`/settings/${dictionary}/reorder`, { ids }))
    dictionaries.value[listKey(dictionary)] = data.data
  }

  async function savePreferences(patch) {
    const { data } = await call(() => api.patch('/me/preferences', patch))
    preferences.value = data.data
    return data.data
  }

  async function rotateCalendarFeed() {
    const { data } = await call(() => api.post('/me/calendar-feed'))
    preferences.value = { ...preferences.value, calendar_feed_url: data.data.url }
    return data.data.url
  }

  async function disableCalendarFeed() {
    await call(() => api.delete('/me/calendar-feed'))
    preferences.value = { ...preferences.value, calendar_feed_url: null }
  }

  function clear() {
    dictionaries.value = { categories: [], statuses: [], task_categories: [], field_templates: [], colors: [] }
    preferences.value = null
    loaded.value = false
  }

  return {
    dictionaries,
    preferences,
    loaded,
    error,
    statuses,
    taskCategories,
    categories,
    fieldTemplates,
    statusByKey,
    taskCategoryByKey,
    load,
    create,
    update,
    remove,
    reorder,
    savePreferences,
    rotateCalendarFeed,
    disableCalendarFeed,
    clear,
  }
})
