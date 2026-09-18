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
    path: '/clientes/nuevo',
    name: 'nuevo-cliente',
    component: () => import('@/views/NuevoClienteView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/nuevo-cliente',
    redirect: '/clientes/nuevo'
  },
  {
    path: '/dominios',
    name: 'dominios',
    component: () => import('@/views/DominiosView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/dominios/nuevo',
    name: 'nuevo-dominio',
    component: () => import('@/views/NuevoDominioView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/asignar-dominio',
    redirect: '/dominios/nuevo'
  },
  {
    path: '/hostings',
    name: 'hostings',
    component: () => import('@/views/HostingsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/hostings/nuevo',
    name: 'nuevo-hosting',
    component: () => import('@/views/NuevoHostingView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/asignar-hosting',
    redirect: '/hostings/nuevo'
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
    path: '/pagos/nuevo',
    name: 'nuevo-pago',
    component: () => import('@/views/NuevoPagoView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/registrar-pago',
    redirect: '/pagos/nuevo'
  },
  {
    path: '/pago',
    redirect: '/pagos'
  },
  {
    path: '/recordatorios',
    name: 'recordatorios',
    component: () => import('@/views/RecordatoriosView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/comunicacion/recordatorios',
    redirect: '/recordatorios'
  },
  {
    path: '/solicitudes',
    name: 'solicitudes',
    component: () => import('@/views/SolicitudesView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/solicitudes',
    redirect: '/solicitudes'
  },
  {
    path: '/admin/tickets',
    redirect: '/solicitudes'
  },
  {
    path: '/portal',
    name: 'portal-inicio',
    component: () => import('@/views/portal/PortalInicioView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/portal/inicio',
    redirect: '/portal'
  },
  {
    path: '/portal/clientes',
    redirect: '/portal'
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
    if (authStore.isCliente) {
      return next({ path: '/portal' })
    }
    if (authStore.isAgente) {
      return next({ path: '/solicitudes' })
    }
    return next({ path: '/dashboard' })
  }

  // Redirección para Clientes al entrar a la raíz o al dashboard administrativo
  if (isAuth && authStore.isCliente && (to.path === '/' || to.path === '/dashboard')) {
    return next({ path: '/portal' })
  }

  // Redirección para Agentes al entrar a la raíz o al dashboard administrativo
  if (isAuth && authStore.isAgente && (to.path === '/' || to.path === '/dashboard')) {
    return next({ path: '/solicitudes' })
  }

  next()
})

export default router
