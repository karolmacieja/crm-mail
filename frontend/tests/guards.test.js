import { describe, expect, it } from 'vitest'
import { nextTick, reactive } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import { installAuthGuard } from '@/shared/router/guards.js'

const View = { render: () => null }

function setup(authState) {
  const auth = reactive({ isAuthenticated: false, role: null, hasValidLicense: false, ensureReady: async () => {}, ...authState })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: View, meta: { guestOnly: true } },
      { path: '/license', name: 'license', component: View, meta: { requiresAuth: true } },
      { path: '/forbidden', name: 'forbidden', component: View },
      {
        path: '/',
        component: View,
        meta: { requiresAuth: true, requiresLicense: true, roles: ['staff', 'manager'] },
        children: [
          { path: 'home', name: 'home', component: View },
          { path: 'clients/:id', name: 'client', component: View },
        ],
      },
    ],
  })
  installAuthGuard(router, { auth, loginRoute: 'login', homeRoute: 'home', licenseRoute: 'license', forbiddenRoute: 'forbidden' })
  return { auth, router }
}

describe('installAuthGuard', () => {
  it('sends guests to login and remembers where they were going', async () => {
    const { router } = setup()
    await router.push('/clients/7')
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/clients/7')
  })

  it('enforces roles from meta (master admin cannot open the CRM)', async () => {
    const { router } = setup({ isAuthenticated: true, role: 'master_admin', hasValidLicense: true })
    await router.push('/home')
    expect(router.currentRoute.value.name).toBe('forbidden')
  })

  it('redirects staff without a license and returns once it is renewed', async () => {
    const { router, auth } = setup({ isAuthenticated: true, role: 'staff', hasValidLicense: false })
    await router.push('/clients/7')
    expect(router.currentRoute.value.name).toBe('license')

    auth.hasValidLicense = true
    await nextTick()
    await router.isReady()
    await new Promise((r) => setTimeout(r))
    expect(router.currentRoute.value.fullPath).toBe('/clients/7')
  })

  it('keeps signed-in users away from the login page', async () => {
    const { router } = setup({ isAuthenticated: true, role: 'staff', hasValidLicense: true })
    await router.push('/login')
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('after signing in on the login page, continues to the originally requested page', async () => {
    const { router, auth } = setup({ role: 'staff', hasValidLicense: true })
    await router.push('/clients/7')
    expect(router.currentRoute.value.name).toBe('login')

    auth.isAuthenticated = true // e.g. login form or popup
    await nextTick()
    await new Promise((r) => setTimeout(r))
    expect(router.currentRoute.value.fullPath).toBe('/clients/7')
  })

  it('reacts to logout happening outside the router', async () => {
    const { router, auth } = setup({ isAuthenticated: true, role: 'staff', hasValidLicense: true })
    await router.push('/home')
    expect(router.currentRoute.value.name).toBe('home')

    auth.isAuthenticated = false
    await nextTick()
    await new Promise((r) => setTimeout(r))
    expect(router.currentRoute.value.name).toBe('login')
  })
})
