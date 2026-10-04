import { defineStore } from 'pinia'
import { computed } from 'vue'
import { api } from '@/crm/api.js'
import { createPaginatedList } from '@/shared/stores/paginatedList.js'
import { useSettingsStore } from './settings.js'

/**
 * Lists for the "Klienci" (profiles, grouped by category) and
 * "Kontakty" (plain address book) tabs, plus the group's categories.
 */
export const useContactsStore = defineStore('contacts', () => {
  const fetchContacts = (params) => api.get('/contacts', { params })

  const clients = createPaginatedList(
    (params) => fetchContacts({ ...params, is_client: 1 }),
    { search: '', category: '', owner: '', sort: 'last_activity_at', direction: 'desc' },
  )
  const addressBook = createPaginatedList(
    (params) => fetchContacts({ ...params, is_client: 0 }),
    { search: '', owner: '', sort: 'name', direction: 'asc' },
  )
  clients.state.meta.per_page = 100

  // Categories are edited in Settings; one source of truth for the whole CRM.
  const settings = useSettingsStore()
  const categories = computed(() => settings.categories)
  const categoriesError = computed(() => settings.error)

  async function loadCategories() {
    await settings.load()
    return categories.value
  }

  /**
   * Clients grouped by category in the categories' order, as in the mockup
   * ("Kategoria: B2B", "Kategoria: VIP", ...). Uncategorised go last.
   */
  const groupedClients = computed(() => {
    const groups = categories.value.map((category) => ({ category, items: [] }))
    const other = { category: null, items: [] }
    const byId = new Map(groups.map((group) => [group.category.id, group]))

    for (const client of clients.state.items) {
      ;(byId.get(client.category_id) ?? other).items.push(client)
    }

    return [...groups, other].filter((group) => group.items.length)
  })

  /** Keep lists in sync when a contact is created/edited/deleted elsewhere. */
  function syncContact(contact, { removed = false } = {}) {
    for (const list of [clients, addressBook]) {
      if (removed) list.remove(contact.id)
      else list.upsert(contact)
    }
  }

  function clear() {
    clients.reset()
    addressBook.reset()
  }

  return {
    clear,
    clients: clients.state,
    addressBook: addressBook.state,
    categories,
    categoriesError,
    groupedClients,
    loadClients: clients.load,
    loadAddressBook: addressBook.load,
    loadCategories,
    syncContact,
  }
})
