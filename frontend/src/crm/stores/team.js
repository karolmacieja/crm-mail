import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '@/crm/api.js'

/** Colleagues from the same restaurant (task assignment). Loaded once. */
export const useTeamStore = defineStore('team', () => {
  const members = ref([])
  let promise = null

  function load() {
    promise ??= api
      .get('/team')
      .then(({ data }) => {
        members.value = data.data
      })
      .catch((error) => {
        promise = null
        throw error
      })
    return promise
  }

  function clear() {
    members.value = []
    promise = null
  }

  return { members, load, clear }
})
