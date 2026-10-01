import { watch } from 'vue'

/**
 * Route meta used by both apps:
 *   requiresAuth: true      – must be signed in (inherited by child routes)
 *   roles: ['master_admin'] – user.role must be one of these
 *   requiresLicense: true   – CRM only: a seat on an active license
 *   guestOnly: true         – e.g. the login page; signed-in users are sent home
 *   title: 'nav.clients'    – i18n key for the page title
 *
 * `auth` is a store exposing: ensureReady(), isAuthenticated, role, hasValidLicense.
 *
 * @param {import('vue-router').Router} router
 * @param {{ auth: object, loginRoute: string, homeRoute: string, licenseRoute?: string, forbiddenRoute?: string }} options
 */
export function installAuthGuard(router, { auth, loginRoute, homeRoute, licenseRoute, forbiddenRoute }) {
  router.beforeEach(async (to) => {
    await auth.ensureReady()

    const requiresAuth = to.matched.some((record) => record.meta.requiresAuth)
    const requiresLicense = to.matched.some((record) => record.meta.requiresLicense)
    const roles = to.matched.flatMap((record) => record.meta.roles ?? [])

    // Signed in while on the login page (also when the session appears outside the
    // login form, e.g. via the popup): continue to the page the user asked for.
    if (to.matched.some((record) => record.meta.guestOnly) && auth.isAuthenticated) {
      return redirectTarget(to, null) ?? { name: homeRoute }
    }

    if (requiresAuth && !auth.isAuthenticated) {
      return to.name === loginRoute ? true : { name: loginRoute, query: { redirect: to.fullPath } }
    }

    if (roles.length && !roles.includes(auth.role)) {
      return forbiddenRoute ? { name: forbiddenRoute } : { name: loginRoute }
    }

    // License renewed while on the "license expired" page: continue where the user wanted to go.
    if (licenseRoute && to.name === licenseRoute && auth.hasValidLicense) {
      return redirectTarget(to, null) ?? { name: homeRoute }
    }

    if (requiresLicense && licenseRoute && !auth.hasValidLicense) {
      return { name: licenseRoute, query: { redirect: to.fullPath } }
    }

    return true
  })

  // Session changes outside navigation (logout in the popup or another tab,
  // 401/402 from the API) re-run the guard for the current page.
  watch(
    () => [auth.isAuthenticated, auth.role, auth.hasValidLicense],
    () => {
      const current = router.currentRoute.value
      // force: re-navigating to the same location would otherwise be skipped without running guards.
      if (current.matched.length) {
        router.replace({ path: current.path, query: current.query, hash: current.hash, force: true }).catch(() => {})
      }
    },
  )
}

/** After login: go back to where the guard sent us from (same-app paths only). */
export function redirectTarget(route, fallback) {
  const target = route.query.redirect
  return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : fallback
}
