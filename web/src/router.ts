import { nextTick, ref } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import { can } from '@/lib/abilities'
import { useAuthStore } from '@/stores/auth'
import AppShell from '@/layouts/AppShell.vue'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    section?: string
    public?: boolean
    /** [action, subject] the user needs, checked against userData.ability. */
    can?: [string, string]
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'landing', component: () => import('@/pages/Landing.vue'), meta: { public: true } },
    { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { public: true, title: 'Ingresar' } },
    { path: '/forgot-password', name: 'forgot-password', component: () => import('@/pages/ForgotPassword.vue'), meta: { public: true, title: 'Recuperar contraseña' } },
    { path: '/reset-password/:token', name: 'reset-password', component: () => import('@/pages/ResetPassword.vue'), meta: { public: true, title: 'Nueva contraseña' } },
    {
      path: '/',
      component: AppShell,
      children: [
        {
          path: 'inicio', name: 'inicio', component: () => import('@/pages/Inicio.vue'),
          meta: { title: 'Inicio', can: ['read', 'Auth'] },
        },
        {
          path: 'balances', name: 'balances', component: () => import('@/pages/balances/Listado.vue'),
          meta: { title: 'Listado', section: 'Balances', can: ['read', 'Balances'] },
        },
        {
          path: 'balances/nuevo', name: 'balances-nuevo', component: () => import('@/pages/balances/Nuevo.vue'),
          meta: { title: 'Nuevo balance', section: 'Balances', can: ['create', 'Balances'] },
        },
        {
          path: 'balances/sesion/:id(\\d+)', name: 'balance-sesion', component: () => import('@/pages/balances/Sesion.vue'),
          meta: { title: 'Espacio de balance', section: 'Balances', can: ['read', 'Balances'] },
        },
        {
          path: 'analytics', name: 'analytics', component: () => import('@/pages/Analytics.vue'),
          meta: { title: 'Analytics', can: ['read', 'DashboardAnalytics'] },
        },
        {
          path: 'admin/usuarios', name: 'admin-usuarios', component: () => import('@/pages/admin/Usuarios.vue'),
          meta: { title: 'Usuarios', section: 'Administración', can: ['manage', 'Users'] },
        },
        {
          // Admin and operator: operator's only write ability is on Balances.
          path: 'admin/diagramas', name: 'admin-diagramas', component: () => import('@/pages/admin/Diagramas.vue'),
          meta: { title: 'Diagramas de proceso', section: 'Administración', can: ['update', 'Balances'] },
        },
      ],
    },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFound.vue'), meta: { public: true, title: 'Página no encontrada' } },
  ],
})

router.beforeEach(async to => {
  const auth = useAuthStore()

  if (to.meta.public) {
    if (auth.loggedIn && (to.name === 'landing' || to.name === 'login')) return { name: 'inicio' }
    return true
  }
  if (!auth.loggedIn) return { name: 'login', query: { redirect: to.fullPath } }
  if (!auth.validated && !(await auth.validate())) return { name: 'login', query: { sesion: 'expirada' } }
  if (to.meta.can && !can(auth.user?.ability, ...to.meta.can)) {
    // Inicio is the fallback; if even that is denied the stored user is unusable, so start over.
    if (to.name !== 'inicio') return { name: 'inicio' }
    auth.reset()
    return { name: 'login' }
  }
  return true
})

/** Page title for the aria-live region in App.vue. */
export const announcement = ref('')

router.afterEach((to, from, failure) => {
  if (failure) return
  document.title = to.meta.title ? `${to.meta.title} · Balances CMP` : 'Balances CMP | Compañía Minera del Pacífico'
  // First load: leave focus where the browser puts it. Later navigations: move it to the new content.
  // Same page with new query/params (listado filters, a clone taking over the session URL): keep focus where the user is.
  if (from.matched.length === 0 || to.name === from.name) return
  announcement.value = to.meta.title ?? 'Balances CMP'
  nextTick(() => document.getElementById('contenido')?.focus({ preventScroll: true }))
})
