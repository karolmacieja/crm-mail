import { describe, expect, it } from 'vitest'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'

const deferred = () => {
  let resolve
  const promise = new Promise((r) => (resolve = r))
  return { promise, resolve }
}
const page = (items, meta = {}) => ({ data: { data: items, meta: { current_page: 1, last_page: 1, total: items.length, ...meta } } })

describe('createPaginatedList', () => {
  it('ignores responses that arrive after a newer request', async () => {
    const calls = []
    const list = createPaginatedList((params) => {
      const d = deferred()
      calls.push({ params, ...d })
      return d.promise
    }, { search: '' })

    const first = list.load({ filters: { search: 'ko' } })
    const second = list.load({ filters: { search: 'kowalski' } })
    calls[1].resolve(page([{ id: 2 }]))
    await second
    calls[0].resolve(page([{ id: 1 }, { id: 3 }]))
    await first

    expect(list.state.items).toEqual([{ id: 2 }])
    expect(calls[1].params).toMatchObject({ search: 'kowalski', page: 1 })
  })

  it('drops empty filters and resets to page 1 when filters change', async () => {
    let lastParams
    const list = createPaginatedList(async (params) => {
      lastParams = params
      return page([])
    }, { search: '', category: '' })

    await list.load({ page: 3 })
    expect(lastParams).toEqual({ page: 3, per_page: 25 })
    await list.load({ filters: { category: 'vip' } })
    expect(lastParams).toEqual({ category: 'vip', page: 1, per_page: 25 })
  })
})
