import { defineStore } from 'pinia'
import { reactive, ref } from 'vue'
import { GOOGLE_ENABLED } from '@/shared/lib/config.js'
import { t } from '@/shared/lib/i18n.js'
import { googleConnect, googleDisconnect, googleStatus } from '@/crm/google/client.js'
import { syncCalendarItem } from '@/crm/google/calendarSync.js'
import { importEmailHistory } from '@/crm/google/emailHistory.js'
import { useAuthStore } from './auth.js'
import { useSettingsStore } from './settings.js'

/**
 * Google integration of the signed-in staff member (the Gmail tab's Google account):
 *  - import of past Gmail correspondence of contacts into the timeline,
 *  - their tasks & reminders as events in their own Google Calendar.
 */
export const useGoogleStore = defineStore('google', () => {
  const enabled = GOOGLE_ENABLED
  /** null = not checked yet */
  const connected = ref(null)
  const lastError = ref(null)
  /** contactId -> { status: 'running'|'done'|'error', progress, result, error } */
  const history = reactive({})
  const autoImported = new Set()

  async function checkStatus() {
    if (!enabled) return false
    try {
      connected.value = await googleStatus()
    } catch {
      connected.value = false
    }
    return connected.value
  }

  async function connect() {
    lastError.value = null
    try {
      await googleConnect()
      connected.value = true
    } catch (error) {
      const known = ['wrong_account', 'missing_scopes', 'access_denied'].includes(error.code)
      lastError.value = known ? t(`settings.google.errors.${error.code}`) : error.message
      connected.value = false
      throw error
    }
  }

  async function disconnect() {
    await googleDisconnect()
    connected.value = false
  }

  const prefs = () => useSettingsStore().preferences
  const ready = async () => enabled && (connected.value ?? (await checkStatus()))

  /** Manual "Wczytaj historię z Gmaila" or the automatic first import. */
  async function importHistory(contact) {
    if (!(await ready())) return null
    if (history[contact.id]?.status === 'running') return null

    history[contact.id] = { status: 'running', progress: null, result: null, error: null }
    try {
      const result = await importEmailHistory(contact, {
        onProgress: (progress) => {
          history[contact.id].progress = progress
        },
      })
      history[contact.id] = { status: 'done', progress: null, result, error: null }
      return result
    } catch (error) {
      history[contact.id] = { status: 'error', progress: null, result: null, error: error.message }
      return null
    }
  }

  /**
   * Called whenever a contact is opened: imports the full history the first
   * time, then only new emails since the last import (once per session).
   */
  async function maybeAutoImport(contact) {
    if (!contact || autoImported.has(contact.id) || prefs()?.google?.email_history === false) return null
    autoImported.add(contact.id)
    return importHistory(contact)
  }

  /** Mirror a task/reminder change into the person's Google Calendar. */
  async function syncCalendar(kind, item, { removed = false } = {}) {
    if (!prefs()?.google?.calendar_sync || !(await ready())) return
    try {
      const eventId = await syncCalendarItem(kind, item, { meId: useAuthStore().user?.id, preferences: prefs(), removed })
      if (!removed) item.calendar_event_id = eventId
    } catch (error) {
      lastError.value = error.message
      console.warn('[GastroFlowx] Google Calendar sync failed', error)
    }
  }

  function clear() {
    Object.keys(history).forEach((key) => delete history[key])
    autoImported.clear()
    lastError.value = null
  }

  return { enabled, connected, lastError, history, checkStatus, connect, disconnect, importHistory, maybeAutoImport, syncCalendar, clear }
})
