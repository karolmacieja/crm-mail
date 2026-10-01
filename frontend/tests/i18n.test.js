import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import en from '@/shared/locales/en.js'
import pl from '@/shared/locales/pl.js'

function files(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    return statSync(path).isDirectory() ? files(path) : /\.(vue|js)$/.test(name) ? [path] : []
  })
}

const lookup = (messages, key) => key.split('.').reduce((node, part) => node?.[part], messages)

// t('a.b'), t(`a.${x}`), and route meta / nav entries: label: 'nav.x', title: 'nav.x'
const KEY = /\bt\((['`])([a-zA-Z0-9_.]+(?:\$\{[^}]+\}[a-zA-Z0-9_.]*)?)\1|\b(?:label|title): '([a-z]+\.[a-zA-Z0-9_.]+)'/g

const usedKeys = new Set()
for (const file of files(join(__dirname, '../src'))) {
  for (const match of readFileSync(file, 'utf8').matchAll(KEY)) usedKeys.add(match[2] ?? match[3])
}

describe('translations', () => {
  it('finds the keys used in the code', () => {
    expect(usedKeys.size).toBeGreaterThan(200)
  })

  for (const [name, messages] of Object.entries({ pl, en })) {
    it(`every key used in the code exists in ${name}`, () => {
      const missing = [...usedKeys].filter((key) => {
        if (!key.includes('${')) return lookup(messages, key) === undefined
        // Dynamic key, e.g. `crm.taskTypes.${type}`: the parent object must exist.
        return typeof lookup(messages, key.slice(0, key.indexOf('${')).replace(/\.$/, '')) !== 'object'
      })
      expect(missing).toEqual([])
    })
  }

  it('pl and en define the same keys', () => {
    const flatten = (obj, prefix = '') =>
      Object.entries(obj).flatMap(([k, v]) =>
        v && typeof v === 'object' && !('other' in v) ? flatten(v, `${prefix}${k}.`) : [`${prefix}${k}`],
      )
    expect(flatten(pl).sort()).toEqual(flatten(en).sort())
  })
})
