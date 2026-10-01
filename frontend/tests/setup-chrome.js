import { vi } from 'vitest'

/** Minimal chrome.* stub (storage + onChanged) for code that runs in the extension. */
export function installChromeStub(initial = {}) {
  const data = { ...initial }
  const listeners = new Set()
  const emit = (changes) => listeners.forEach((listener) => listener(changes, 'local'))

  globalThis.chrome = {
    storage: {
      local: {
        get: vi.fn(async (key) => (key in data ? { [key]: data[key] } : {})),
        set: vi.fn(async (items) => {
          const changes = {}
          for (const [k, v] of Object.entries(items)) {
            changes[k] = { oldValue: data[k], newValue: v }
            data[k] = v
          }
          emit(changes)
        }),
        remove: vi.fn(async (key) => {
          const changes = { [key]: { oldValue: data[key], newValue: undefined } }
          delete data[key]
          emit(changes)
        }),
      },
      onChanged: {
        addListener: (fn) => listeners.add(fn),
        removeListener: (fn) => listeners.delete(fn),
      },
    },
    runtime: { id: 'test', lastError: null, sendMessage: vi.fn() },
  }

  return data
}
