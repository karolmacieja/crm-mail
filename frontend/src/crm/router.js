import { createMemoryHistory, createRouter } from 'vue-router'
import { installAuthGuard } from '@/shared/router/guards.js'
import PlaceholderView from '@/shared/views/PlaceholderView.vue'
import { useAuthStore } from '@/crm/stores/auth.js'
import CrmLayout from '@/crm/layouts/CrmLayout.vue'
import LicenseView from '@/crm/views/LicenseView.vue'
import LoginView from '@/crm/views/LoginView.vue'

/**
 * CRM for restaurant staff, rendered inside Gmail (InboxSDK custom route).
 * Memory history: Gmail owns the address bar (#inbox, #gastroflowx), so the
 * Vue router must never touch window.location.
 */
export const crmRoutes = [
  { path: '/login', name: 'crm.login', component: LoginView, meta: { guestOnly: true, title: 'login.title' } },
  { path: '/license', name: 'crm.license', component: LicenseView, meta: { requiresAuth: true, title: 'license.missingTitle' } },
  {
    path: '/',
    component: CrmLayout,
    meta: { requiresAuth: true, requiresLicense: true, roles: ['manager', 'staff'] },
    children: [
      { path: '', redirect: { name: 'crm.dashboard' } },
      { path: 'dashboard', name: 'crm.dashboard', component: PlaceholderView, meta: { title: 'nav.dashboard' } },
      { path: 'clients', name: 'crm.clients', component: PlaceholderView, meta: { title: 'nav.clients' } },
      {
        // Full client card: replaces the list (the list route is left).
        path: 'clients/:id(\\d+)',
        name: 'crm.clients.show',
        component: PlaceholderView,
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'nav.clients' },
      },
      { path: 'contacts', name: 'crm.contacts', component: PlaceholderView, meta: { title: 'nav.contacts' } },
      { path: 'tasks', name: 'crm.tasks', component: PlaceholderView, meta: { title: 'nav.tasks' } },
      { path: 'reminders', name: 'crm.reminders', component: PlaceholderView, meta: { title: 'nav.reminders' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: { name: 'crm.dashboard' } },
]

export function createCrmRouter(pinia, { initialPath = '/dashboard', history = createMemoryHistory() } = {}) {
  const router = createRouter({ history, routes: crmRoutes })

  installAuthGuard(router, {
    auth: useAuthStore(pinia),
    loginRoute: 'crm.login',
    homeRoute: 'crm.dashboard',
    licenseRoute: 'crm.license',
  })

  router.push(initialPath)
  return router
}
