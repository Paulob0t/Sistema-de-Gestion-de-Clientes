import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/',
    redirect: '/login'
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guestOnly: true }
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/clientes',
    name: 'clientes',
    component: () => import('@/views/ClientesView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/dominios',
    name: 'dominios',
    component: () => import('@/views/DominiosView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/hostings',
    name: 'hostings',
    component: () => import('@/views/HostingsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/hosting',
    redirect: '/hostings'
  },
  {
    path: '/pagos',
    name: 'pagos',
    component: () => import('@/views/PagosView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/pago',
    redirect: '/pagos'
  },
  {
    path: '/portal/clientes',
    name: 'portal-clientes',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/:pathMatch(.*)*',
    name: 'admin-views',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    redirect: '/login'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore()

  // Si hay token en local storage pero no datos de usuario, intentar cargarlos
  if (authStore.token && !authStore.user) {
    await authStore.fetchCurrentUser()
  }

  const isAuth = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuth) {
    return next({ path: '/login', query: { redirect: to.fullPath } })
  }

  if (to.meta.guestOnly && isAuth) {
    return next({ path: '/dashboard' })
  }

  next()
})

export default router
