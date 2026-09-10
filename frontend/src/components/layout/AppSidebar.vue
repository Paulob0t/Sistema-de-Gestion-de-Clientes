<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineProps<{
  isMobileOpen: boolean
}>()

const emit = defineEmits<{
  (e: 'close-mobile'): void
}>()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const isSuperAdmin = computed(() => authStore.isSuperAdmin || (user.value?.id_tipo_usuario ?? 0) >= 1)
const isClient = computed(() => authStore.isCliente)

// Estado colapsado / expandido adaptativo en escritorio
const isCollapsed = ref(false)

// Estados para módulos colapsables del menú
const openModules = ref<Record<string, boolean>>({
  consultas: true,
  registros: false,
  comunicacion: false,
  soporte: false,
  analytics: false,
  seguridad: false,
})

function toggleModule(modKey: string) {
  if (isCollapsed.value) {
    isCollapsed.value = false
  }
  openModules.value[modKey] = !openModules.value[modKey]
}

function handleNavigation(path: string) {
  emit('close-mobile')
  router.push(path)
}

function handleLogout() {
  emit('close-mobile')
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div>
    <!-- Overlay móvil con Teleport -->
    <Teleport to="body">
      <transition name="fade">
        <div
          v-if="isMobileOpen"
          @click="emit('close-mobile')"
          class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm lg:hidden"
        ></div>
      </transition>
    </Teleport>

    <!-- Menú Lateral Fijo, Pinned y Ultra-Premium -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 bg-[#070B14] text-slate-300 border-r border-slate-800/70 flex flex-col transition-all duration-200 ease-out lg:static lg:h-screen lg:sticky lg:top-0 shrink-0 select-none shadow-2xl overflow-hidden',
        isCollapsed ? 'lg:w-20' : 'lg:w-72',
        isMobileOpen ? 'translate-x-0 w-72' : '-translate-x-full lg:translate-x-0'
      ]"
    >
      <!-- Cabecera / Marca -->
      <div class="h-16 px-4 border-b border-slate-800/60 flex items-center justify-between shrink-0 bg-[#0A0F1D]/80">
        <div
          class="flex items-center space-x-3 cursor-pointer overflow-hidden group"
          @click="handleNavigation('/dashboard')"
        >
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 p-0.5 shadow-lg shadow-blue-500/25 shrink-0 group-hover:scale-105 transition-transform duration-200">
            <div class="w-full h-full bg-[#070B14] rounded-[9px] flex items-center justify-center">
              <i class="pi pi-bolt text-base text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300"></i>
            </div>
          </div>
          <div v-show="!isCollapsed" class="min-w-0 transition-opacity duration-150">
            <span class="text-sm font-extrabold tracking-tight text-white flex items-center space-x-1">
              <span>NEXUS</span><span class="text-blue-500">BOT</span>
            </span>
            <span class="block text-[9px] font-bold tracking-widest text-slate-500 uppercase">Cloud CRM Suite</span>
          </div>
        </div>

        <!-- Botón toggle colapso -->
        <div class="flex items-center space-x-1">
          <button
            @click="isCollapsed = !isCollapsed"
            class="hidden lg:flex w-7 h-7 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800/80 items-center justify-center transition-colors"
            :title="isCollapsed ? 'Expandir menú' : 'Colapsar menú'"
          >
            <i :class="isCollapsed ? 'pi pi-chevron-right text-xs' : 'pi pi-chevron-left text-xs'"></i>
          </button>
          <button
            @click="emit('close-mobile')"
            class="lg:hidden w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 flex items-center justify-center transition-colors"
          >
            <i class="pi pi-times text-sm"></i>
          </button>
        </div>
      </div>

      <!-- Scroll de Módulos -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-3.5 custom-scrollbar overflow-x-hidden">
        <!-- Dashboard Principal -->
        <div>
          <button
            @click="handleNavigation('/dashboard')"
            :class="[
              'w-full flex items-center rounded-xl text-xs font-semibold transition-colors duration-150 relative group',
              isCollapsed ? 'justify-center p-3' : 'space-x-3 px-3 py-2.5',
              route.path === '/dashboard'
                ? 'bg-gradient-to-r from-blue-600/20 to-transparent text-blue-400 font-bold border-l-2 border-blue-500'
                : 'text-slate-400 hover:bg-slate-900 hover:text-slate-200'
            ]"
            :title="isCollapsed ? 'Dashboard Principal' : undefined"
          >
            <i class="pi pi-th-large text-sm text-blue-400 shrink-0 group-hover:scale-110 transition-transform"></i>
            <span v-show="!isCollapsed" class="truncate">Dashboard Principal</span>
          </button>
        </div>

        <!-- SECCIÓN: CLIENTES / PORTAL (Solo si es Cliente) -->
        <div v-if="isClient" class="space-y-1">
          <div v-show="!isCollapsed" class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
            Mi Cuenta
          </div>
          <button
            @click="handleNavigation('/dashboard')"
            :class="[
              'w-full flex items-center rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-900 hover:text-white transition-colors',
              isCollapsed ? 'justify-center p-3' : 'space-x-3 px-3 py-2'
            ]"
            :title="isCollapsed ? 'Mis Dominios & Hosting' : undefined"
          >
            <i class="pi pi-globe text-sm text-cyan-400 shrink-0"></i>
            <span v-show="!isCollapsed" class="truncate">Mis Dominios & Hosting</span>
          </button>
          <button
            @click="handleNavigation('/pagos')"
            :class="[
              'w-full flex items-center rounded-xl text-xs font-medium text-slate-400 hover:bg-slate-900 hover:text-white transition-colors',
              isCollapsed ? 'justify-center p-3' : 'space-x-3 px-3 py-2',
              route.path.startsWith('/pago') ? 'bg-blue-600/15 text-blue-400 font-bold' : ''
            ]"
            :title="isCollapsed ? 'Mis Pagos & Facturas' : undefined"
          >
            <i class="pi pi-credit-card text-sm text-emerald-400 shrink-0"></i>
            <span v-show="!isCollapsed" class="truncate">Mis Pagos & Facturas</span>
          </button>
        </div>

        <!-- SECCIONES PARA ADMINISTRADORES / STAFF -->
        <template v-if="isSuperAdmin">
          <!-- 1. MÓDULO: CONSULTAS (CRM Core) -->
          <div class="space-y-1">
            <button
              @click="toggleModule('consultas')"
              :class="[
                'w-full flex items-center rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group',
                isCollapsed ? 'justify-center p-3' : 'justify-between px-3 py-2'
              ]"
              :title="isCollapsed ? 'Consultas' : undefined"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0">
                  <i class="pi pi-search text-xs"></i>
                </span>
                <span v-show="!isCollapsed" class="uppercase tracking-wider text-[10px] font-bold text-slate-400">Consultas</span>
              </div>
              <i
                v-show="!isCollapsed"
                class="pi pi-chevron-down text-[10px] transition-transform duration-150 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.consultas }"
              ></i>
            </button>

            <div v-show="openModules.consultas && !isCollapsed" class="space-y-0.5 pl-3 pt-0.5 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/clientes')"
                :class="[
                  'w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs transition-colors',
                  route.path === '/clientes'
                    ? 'bg-blue-600/15 text-blue-400 font-bold border-l-2 border-blue-500'
                    : 'text-slate-400 hover:bg-slate-900 hover:text-white'
                ]"
              >
                <i class="pi pi-building text-xs" :class="route.path === '/clientes' ? 'text-blue-400' : 'text-slate-500'"></i>
                <span class="truncate">Consulta de Clientes</span>
              </button>
              <button
                @click="handleNavigation('/dominios')"
                :class="[
                  'w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs transition-colors',
                  route.path === '/dominios'
                    ? 'bg-blue-600/15 text-blue-400 font-bold border-l-2 border-blue-500'
                    : 'text-slate-400 hover:bg-slate-900 hover:text-white'
                ]"
              >
                <i class="pi pi-globe text-xs" :class="route.path === '/dominios' ? 'text-blue-400' : 'text-slate-500'"></i>
                <span class="truncate">Consulta de Dominios</span>
              </button>
              <button
                @click="handleNavigation('/hostings')"
                :class="[
                  'w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs transition-colors',
                  route.path.startsWith('/hosting')
                    ? 'bg-blue-600/15 text-blue-400 font-bold border-l-2 border-blue-500'
                    : 'text-slate-400 hover:bg-slate-900 hover:text-white'
                ]"
              >
                <i class="pi pi-server text-xs" :class="route.path.startsWith('/hosting') ? 'text-blue-400' : 'text-slate-500'"></i>
                <span class="truncate">Consulta de Hosting</span>
              </button>
              <button
                @click="handleNavigation('/pagos')"
                :class="[
                  'w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs transition-colors',
                  route.path.startsWith('/pago')
                    ? 'bg-blue-600/15 text-blue-400 font-bold border-l-2 border-blue-500'
                    : 'text-slate-400 hover:bg-slate-900 hover:text-white'
                ]"
              >
                <i class="pi pi-credit-card text-xs" :class="route.path.startsWith('/pago') ? 'text-blue-400' : 'text-slate-500'"></i>
                <span class="truncate">Consulta de Pagos</span>
              </button>
            </div>
          </div>

          <!-- 2. MÓDULO: REGISTROS -->
          <div class="space-y-1">
            <button
              @click="toggleModule('registros')"
              :class="[
                'w-full flex items-center rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group',
                isCollapsed ? 'justify-center p-3' : 'justify-between px-3 py-2'
              ]"
              :title="isCollapsed ? 'Registros' : undefined"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center shrink-0">
                  <i class="pi pi-plus text-xs"></i>
                </span>
                <span v-show="!isCollapsed" class="uppercase tracking-wider text-[10px] font-bold text-slate-400">Registros</span>
              </div>
              <i
                v-show="!isCollapsed"
                class="pi pi-chevron-down text-[10px] transition-transform duration-150 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.registros }"
              ></i>
            </button>

            <div v-show="openModules.registros && !isCollapsed" class="space-y-0.5 pl-3 pt-0.5 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/clientes')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-user-plus text-slate-500 text-xs"></i>
                <span class="truncate">Nuevo Cliente</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-link text-slate-500 text-xs"></i>
                <span class="truncate">Asignar Dominio</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-database text-slate-500 text-xs"></i>
                <span class="truncate">Asignar Hosting</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-dollar text-slate-500 text-xs"></i>
                <span class="truncate">Registrar Pago</span>
              </button>
            </div>
          </div>

          <!-- 3. MÓDULO: COMUNICACIÓN & CRM -->
          <div class="space-y-1">
            <button
              @click="toggleModule('comunicacion')"
              :class="[
                'w-full flex items-center rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group',
                isCollapsed ? 'justify-center p-3' : 'justify-between px-3 py-2'
              ]"
              :title="isCollapsed ? 'Comunicación' : undefined"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center shrink-0">
                  <i class="pi pi-send text-xs"></i>
                </span>
                <span v-show="!isCollapsed" class="uppercase tracking-wider text-[10px] font-bold text-slate-400">Comunicación</span>
              </div>
              <i
                v-show="!isCollapsed"
                class="pi pi-chevron-down text-[10px] transition-transform duration-150 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.comunicacion }"
              ></i>
            </button>

            <div v-show="openModules.comunicacion && !isCollapsed" class="space-y-0.5 pl-3 pt-0.5 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-envelope text-slate-500 text-xs"></i>
                <span class="truncate">Recordatorios Correo</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-whatsapp text-slate-500 text-xs"></i>
                <span class="truncate">WhatsApp CRM Web</span>
              </button>
            </div>
          </div>

          <!-- 4. MÓDULO: ANALÍTICA & REPORTES -->
          <div class="space-y-1">
            <button
              @click="toggleModule('analytics')"
              :class="[
                'w-full flex items-center rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group',
                isCollapsed ? 'justify-center p-3' : 'justify-between px-3 py-2'
              ]"
              :title="isCollapsed ? 'Analítica' : undefined"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center shrink-0">
                  <i class="pi pi-chart-pie text-xs"></i>
                </span>
                <span v-show="!isCollapsed" class="uppercase tracking-wider text-[10px] font-bold text-slate-400">Analítica</span>
              </div>
              <i
                v-show="!isCollapsed"
                class="pi pi-chevron-down text-[10px] transition-transform duration-150 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.analytics }"
              ></i>
            </button>

            <div v-show="openModules.analytics && !isCollapsed" class="space-y-0.5 pl-3 pt-0.5 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-chart-line text-slate-500 text-xs"></i>
                <span class="truncate">Reporte de Ingresos</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-900 hover:text-white transition-colors"
              >
                <i class="pi pi-calendar-times text-slate-500 text-xs"></i>
                <span class="truncate">Vencimientos Próximos</span>
              </button>
            </div>
          </div>
        </template>
      </div>

      <!-- Footer: Usuario Conectado & Logout -->
      <div class="p-3 border-t border-slate-800/60 bg-[#0A0F1D]/80 shrink-0">
        <div
          :class="[
            'p-2 rounded-xl bg-slate-900/60 border border-slate-800/70 flex items-center overflow-hidden',
            isCollapsed ? 'justify-center flex-col space-y-2' : 'justify-between space-x-2'
          ]"
        >
          <div class="flex items-center space-x-2.5 overflow-hidden">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-md">
              {{ (user?.nombre || user?.usuario || 'U').charAt(0).toUpperCase() }}
            </div>
            <div v-show="!isCollapsed" class="overflow-hidden">
              <div class="text-xs font-semibold text-white truncate max-w-[110px]">{{ user?.nombre || user?.usuario }}</div>
              <div class="text-[10px] text-emerald-400 font-medium flex items-center space-x-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>En línea</span>
              </div>
            </div>
          </div>

          <button
            @click="handleLogout"
            class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/15 flex items-center justify-center transition-all duration-150 shrink-0 group"
            :title="'Cerrar sesión'"
          >
            <i class="pi pi-sign-out text-xs group-hover:scale-110 transition-transform"></i>
          </button>
        </div>
      </div>
    </aside>
  </div>
</template>
