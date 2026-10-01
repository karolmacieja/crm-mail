import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia } from 'pinia'
import { installChromeStub } from './setup-chrome.js'

vi.mock('@/crm/api.js', async () => {
  const errors = await import('@/shared/api/errors.js')
  return {
    api: { get: vi.fn(async () => ({ data: { data: { id: 1, role: 'staff', license: { is_valid: true } } } })), post: vi.fn() },
    ApiError: errors.ApiError,
    toApiError: errors.toApiError,
    onApiError: errors.onApiError,
  }
})
vi.mock('@/extension/api.js', async () => {
  const mocked = await import('@/crm/api.js')
  return { ...mocked }
})

const settle = () => new Promise((r) => setTimeout(r, 0))

describe('CRM router inside Gmail (memory history)', () => {
  beforeEach(() => vi.resetModules())

  it('shows the login page when no token is stored', async () => {
    installChromeStub()
    const { createCrmRouter } = await import('@/crm/router.js')
    const router = createCrmRouter(createPinia(), { initialPath: '/clients/5' })
    await router.isReady()
    await settle()

    expect(router.currentRoute.value.name).toBe('crm.login')
    expect(router.currentRoute.value.query.redirect).toBe('/clients/5')
  })

  it('opens the requested page for licensed staff and never touches window.location', async () => {
    installChromeStub({
      'gcrm.auth': { token: 'abc', user: { id: 1, role: 'staff', license: { is_valid: true } } },
    })
    const before = window.location.href
    const { createCrmRouter } = await import('@/crm/router.js')
    const router = createCrmRouter(createPinia(), { initialPath: '/clients/5' })
    await router.isReady()
    await settle()

    expect(router.currentRoute.value.name).toBe('crm.clients.show')
    expect(router.currentRoute.value.params.id).toBe('5')
    expect(window.location.href).toBe(before)
  })

  it('sends staff with an expired license to the license page', async () => {
    installChromeStub({
      'gcrm.auth': { token: 'abc', user: { id: 1, role: 'staff', license: { is_valid: false, status: 'expired' } } },
    })
    const { api } = await import('@/crm/api.js')
    api.get.mockResolvedValue({ data: { data: { id: 1, role: 'staff', license: { is_valid: false, status: 'expired' } } } })
    const { createCrmRouter } = await import('@/crm/router.js')
    const router = createCrmRouter(createPinia(), { initialPath: '/tasks' })
    await router.isReady()
    await settle()

    expect(router.currentRoute.value.name).toBe('crm.license')
  })
})
