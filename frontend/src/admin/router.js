import { createRouter, createWebHistory } from 'vue-router'
import { installAuthGuard } from '@/shared/router/guards.js'
import PlaceholderView from '@/shared/views/PlaceholderView.vue'
import { useAdminAuthStore } from '@/admin/stores/auth.js'
import AdminLayout from '@/admin/layouts/AdminLayout.vue'
import LoginView from '@/admin/views/LoginView.vue'

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
      { path: '', name: 'admin.dashboard', component: PlaceholderView, meta: { title: 'nav.adminDashboard' } },
      { path: 'groups', name: 'admin.groups', component: PlaceholderView, meta: { title: 'nav.groups' } },
      {
        path: 'groups/:id(\\d+)',
        name: 'admin.groups.show',
        component: PlaceholderView,
        props: (route) => ({ id: Number(route.params.id) }),
        meta: { title: 'nav.groups' },
      },
      { path: 'users', name: 'admin.users', component: PlaceholderView, meta: { title: 'nav.users' } },
      { path: 'licenses', name: 'admin.licenses', component: PlaceholderView, meta: { title: 'nav.licenses' } },
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
