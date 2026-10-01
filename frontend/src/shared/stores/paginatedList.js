import { reactive } from 'vue'
import { toApiError } from '@/shared/api/errors.js'

/**
 * Reactive state + loader for a Laravel paginated resource
 * ({ data: [], meta: { current_page, last_page, total, per_page } }).
 *
 * Out-of-order responses are ignored, so fast typing in a search box
 * never shows results for an older query.
 *
 * @param {(params: object) => Promise<{ data: { data: any[], meta: object } }>} fetchPage
 * @param {object} defaultFilters
 */
export function createPaginatedList(fetchPage, defaultFilters = {}) {
  const state = reactive({
    items: [],
    meta: { current_page: 1, last_page: 1, total: 0, per_page: 25 },
    filters: { ...defaultFilters },
    page: 1,
    loading: false,
    loaded: false,
    error: null,
  })

  let requestId = 0

  async function load({ filters, page } = {}) {
    if (filters) {
      Object.assign(state.filters, filters)
      state.page = 1
    }
    if (page) state.page = page

    const id = ++requestId
    state.loading = true
    state.error = null

    try {
      const params = { ...cleanParams(state.filters), page: state.page, per_page: state.meta.per_page }
      const { data } = await fetchPage(params)
      if (id !== requestId) return

      state.items = data.data
      state.meta = { ...state.meta, ...data.meta }
      state.loaded = true
    } catch (error) {
      if (id === requestId) state.error = toApiError(error)
    } finally {
      if (id === requestId) state.loading = false
    }
  }

  function reset() {
    requestId++
    Object.assign(state, {
      items: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: state.meta.per_page },
      filters: { ...defaultFilters },
      page: 1,
      loading: false,
      loaded: false,
      error: null,
    })
  }

  /** Replace or remove one item locally after a mutation. */
  function upsert(item) {
    const index = state.items.findIndex((existing) => existing.id === item.id)
    if (index !== -1) state.items[index] = { ...state.items[index], ...item }
  }

  function remove(id) {
    const before = state.items.length
    state.items = state.items.filter((item) => item.id !== id)
    if (state.items.length !== before) state.meta.total = Math.max(0, state.meta.total - 1)
  }

  return { state, load, reset, upsert, remove }
}

/** Drop empty filters so they don't end up as `?search=` in the URL. */
export function cleanParams(params) {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )
}
