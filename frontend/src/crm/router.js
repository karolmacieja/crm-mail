import { createMemoryHistory, createRouter } from 'vue-router'
import { installAuthGuard } from '@/shared/router/guards.js'
import { useAuthStore } from '@/crm/stores/auth.js'
import CrmLayout from '@/crm/layouts/CrmLayout.vue'
import LicenseView from '@/crm/views/LicenseView.vue'
import ClientsView from '@/crm/views/ClientsView.vue'
import ContactsView from '@/crm/views/ContactsView.vue'
import DashboardView from '@/crm/views/DashboardView.vue'
import FullClientProfile from '@/crm/views/FullClientProfile.vue'
import LoginView from '@/crm/views/LoginView.vue'
import RemindersView from '@/crm/views/RemindersView.vue'
import SettingsView from '@/crm/views/SettingsView.vue'
import TasksView from '@/crm/views/TasksView.vue'

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
      { path: 'dashboard', name: 'crm.dashboard', component: DashboardView, meta: { title: 'nav.dashboard' } },
      { path: 'clients', name: 'crm.clients', component: ClientsView, meta: { title: 'nav.clients' } },
      {
        // Full client card: replaces the list (the list route is left).
        path: 'clients/:id(\\d+)',
        name: 'crm.clients.show',
        component: FullClientProfile,
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'nav.clients' },
      },
      { path: 'contacts', name: 'crm.contacts', component: ContactsView, meta: { title: 'nav.contacts' } },
      { path: 'tasks', name: 'crm.tasks', component: TasksView, meta: { title: 'nav.tasks' } },
      { path: 'reminders', name: 'crm.reminders', component: RemindersView, meta: { title: 'nav.reminders' } },
      { path: 'settings', name: 'crm.settings', component: SettingsView, meta: { title: 'nav.settings' } },
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
