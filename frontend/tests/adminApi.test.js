import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { adminApi } from '@/admin/api.js'
import { useAdminAuthStore } from '@/admin/stores/auth.js'

const response = (config, status, data = {}) => ({ data, status, statusText: '', headers: {}, config })
const reject = (config, status, data = {}) => {
  const error = new axios.AxiosError(`status ${status}`, 'ERR_BAD_REQUEST', config, null, response(config, status, data))
  return Promise.reject(error)
}

describe('admin web panel API (Sanctum SPA)', () => {
  let csrf
  beforeEach(() => {
    setActivePinia(createPinia())
    csrf = vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 })
  })

  it('fetches the CSRF cookie before state-changing requests and sends cookies', async () => {
    const seen = []
    adminApi.defaults.adapter = async (config) => {
      seen.push(config)
      return response(config, 200, { ok: true })
    }

    await adminApi.post('/admin/groups', { name: 'X' })

    expect(csrf).toHaveBeenCalledWith('http://api.test/sanctum/csrf-cookie', expect.objectContaining({ withCredentials: true }))
    expect(seen[0].withCredentials).toBe(true)
    expect(seen[0].withXSRFToken).toBe(true)
  })

  it('refreshes the CSRF token and retries once after a 419', async () => {
    let calls = 0
    adminApi.defaults.adapter = async (config) => (++calls === 1 ? reject(config, 419) : response(config, 200, { ok: true }))

    const result = await adminApi.patch('/admin/licenses/1', { seats: 3 })

    expect(result.data.ok).toBe(true)
    expect(calls).toBe(2)
  })

  it('a 401 signs the admin out locally', async () => {
    adminApi.defaults.adapter = async (config) =>
      config.url === '/web/me' ? response(config, 200, { data: { id: 1, role: 'master_admin' } }) : reject(config, 401)
    const auth = useAdminAuthStore()
    await auth.ensureReady()
    expect(auth.isAuthenticated).toBe(true)

    await expect(adminApi.get('/admin/stats')).rejects.toMatchObject({ status: 401, code: 'unauthenticated' })
    expect(auth.isAuthenticated).toBe(false)
  })
})
