import { createRouter, createWebHistory } from 'vue-router'
import { installAuthGuard } from '@/shared/router/guards.js'
import { useAdminAuthStore } from '@/admin/stores/auth.js'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import DashboardView from '@/admin/views/DashboardView.vue'
import GroupDetailView from '@/admin/views/GroupDetailView.vue'
import GroupsView from '@/admin/views/GroupsView.vue'
import LicensesView from '@/admin/views/LicensesView.vue'
import LoginView from '@/admin/views/LoginView.vue'
import UsersView from '@/admin/views/UsersView.vue'

/**
 * Master Admin web panel (browser, e.g. https://app.domena.pl).
 * Every page except /login requires a master_admin session.
 */
export const adminRoutes = [
  { path: '/login', name: 'admin.login', component: LoginView, meta: { guestOnly: true, title: 'login.adminTitle' } },
  {
    path: '/',
    component: AdminLayout,
    meta: { requiresAuth: true, roles: ['master_admin'] },
    children: [
      { path: '', name: 'admin.dashboard', component: DashboardView, meta: { title: 'nav.adminDashboard' } },
      { path: 'groups', name: 'admin.groups', component: GroupsView, meta: { title: 'nav.groups' } },
      {
        path: 'groups/:id(\\d+)',
        name: 'admin.groups.show',
        component: GroupDetailView,
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'nav.groups' },
      },
      { path: 'users', name: 'admin.users', component: UsersView, meta: { title: 'nav.users' } },
      { path: 'licenses', name: 'admin.licenses', component: LicensesView, meta: { title: 'nav.licenses' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: { name: 'admin.dashboard' } },
]

export function createAdminRouter(pinia, history = createWebHistory(import.meta.env.BASE_URL)) {
  const router = createRouter({ history, routes: adminRoutes })

  installAuthGuard(router, {
    auth: useAdminAuthStore(pinia),
    loginRoute: 'admin.login',
    homeRoute: 'admin.dashboard',
  })

  return router
}
