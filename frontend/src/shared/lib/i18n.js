import { ref } from 'vue'
import en from '@/shared/locales/en.js'
import pl from '@/shared/locales/pl.js'
import { LOCALE_STORAGE_KEY } from '@/shared/lib/config.js'

/*
 * Minimal i18n: `vue-i18n` compiles messages with `new Function`, which the
 * Manifest V3 CSP forbids, so plain lookups + Intl.PluralRules are used instead.
 */

const messages = { pl, en }

export const SUPPORTED_LOCALES = [
  { code: 'pl', label: 'PL', name: 'Polski' },
  { code: 'en', label: 'EN', name: 'English' },
]

const isSupported = (code) => Object.hasOwn(messages, code)

function detectLocale() {
  const browser = (globalThis.navigator?.language ?? 'en').toLowerCase()
  return browser.startsWith('pl') ? 'pl' : 'en'
}

/** Current UI language. Shared by every Vue app in the same JS context. */
export const locale = ref(detectLocale())

let initPromise = null

/**
 * Where the language preference lives: chrome.storage.local inside the
 * extension (shared by popup, Gmail tabs, service worker), localStorage in
 * the web panel.
 */
const hasChromeStorage = () => typeof chrome !== 'undefined' && Boolean(chrome.storage?.local)

async function readStoredLocale() {
  if (hasChromeStorage()) {
    return (await chrome.storage.local.get(LOCALE_STORAGE_KEY))[LOCALE_STORAGE_KEY]
  }
  return globalThis.localStorage?.getItem(LOCALE_STORAGE_KEY) ?? null
}

async function writeStoredLocale(code) {
  if (hasChromeStorage()) {
    await chrome.storage.local.set({ [LOCALE_STORAGE_KEY]: code })
  } else {
    globalThis.localStorage?.setItem(LOCALE_STORAGE_KEY, code)
  }
}

/** Load the saved language and follow changes made in other tabs / the popup. */
export function initLocale() {
  initPromise ??= (async () => {
    try {
      const stored = await readStoredLocale()
      if (isSupported(stored)) locale.value = stored

      if (hasChromeStorage()) {
        chrome.storage.onChanged.addListener((changes, areaName) => {
          const next = changes[LOCALE_STORAGE_KEY]?.newValue
          if (areaName === 'local' && isSupported(next)) locale.value = next
        })
      } else {
        globalThis.addEventListener?.('storage', (event) => {
          if (event.key === LOCALE_STORAGE_KEY && isSupported(event.newValue)) locale.value = event.newValue
        })
      }
    } catch (error) {
      console.warn('[GastroFlowx] Could not load language preference', error)
    }
  })()

  return initPromise
}

export async function setLocale(code) {
  if (!isSupported(code)) return
  locale.value = code
  try {
    await writeStoredLocale(code)
  } catch (error) {
    console.warn('[GastroFlowx] Could not save language preference', error)
  }
}

function lookup(code, key) {
  return key.split('.').reduce((node, part) => node?.[part], messages[code])
}

/**
 * Translate `key`, interpolating `{name}` placeholders from `params`.
 * Plural messages are objects keyed by Intl.PluralRules category and use `params.count`.
 * Reading `locale.value` makes templates re-render when the language changes.
 */
export function t(key, params = {}) {
  const code = locale.value
  let message = lookup(code, key) ?? lookup('en', key) ?? key

  if (message && typeof message === 'object') {
    const category = new Intl.PluralRules(code).select(Number(params.count ?? 0))
    message = message[category] ?? message.other
  }

  return String(message).replace(/\{(\w+)\}/g, (match, name) => (params[name] ?? match).toString())
}
