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
  openModules.value[modKey] = !openModules.value[modKey]
}

function handleNavigation(path: string) {
  emit('close-mobile')
  if (path === '/dashboard') {
    router.push('/dashboard')
  } else {
    router.push(path)
  }
}
</script>

<template>
  <div>
    <!-- Overlay móvil -->
    <div
      v-if="isMobileOpen"
      @click="emit('close-mobile')"
      class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm lg:hidden transition-opacity"
    ></div>

    <!-- Menú Lateral -->
    <aside
      :class="[
        'fixed top-0 bottom-0 left-0 z-50 w-72 bg-[#0B0F19] text-slate-300 border-r border-slate-800/80 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:z-auto shrink-0 select-none shadow-2xl',
        isMobileOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <!-- Cabecera / Marca -->
      <div class="h-16 px-5 border-b border-slate-800/80 flex items-center justify-between shrink-0 bg-slate-950/50">
        <div class="flex items-center space-x-3 cursor-pointer" @click="handleNavigation('/dashboard')">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-cyan-400 p-0.5 shadow-md shadow-blue-500/20">
            <div class="w-full h-full bg-slate-950 rounded-[9px] flex items-center justify-center">
              <i class="pi pi-bolt text-base text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300"></i>
            </div>
          </div>
          <div>
            <span class="text-base font-extrabold tracking-tight text-white">NEXUS<span class="text-blue-500">BOT</span></span>
            <span class="block text-[9px] font-semibold tracking-wider text-slate-400 uppercase">Enterprise CRM</span>
          </div>
        </div>

        <!-- Botón cerrar en móvil -->
        <button
          @click="emit('close-mobile')"
          class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
        >
          <i class="pi pi-times text-sm"></i>
        </button>
      </div>

      <!-- Área con Scroll de los Módulos -->
      <div class="flex-1 overflow-y-auto px-3.5 py-4 space-y-5 custom-scrollbar">
        <!-- Dashboard Principal (Acceso Directo) -->
        <div>
          <button
            @click="handleNavigation('/dashboard')"
            :class="[
              'w-full flex items-center space-x-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all duration-200 group relative',
              route.path === '/dashboard'
                ? 'bg-blue-600/15 text-blue-400 border border-blue-500/30 font-bold'
                : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'
            ]"
          >
            <div
              v-if="route.path === '/dashboard'"
              class="absolute left-0 top-2 bottom-2 w-1 bg-blue-500 rounded-r"
            ></div>
            <i class="pi pi-th-large text-sm text-blue-400 group-hover:scale-110 transition-transform"></i>
            <span>Dashboard Principal</span>
          </button>
        </div>

        <!-- SECCIÓN: CLIENTES / PORTAL (Solo si es Cliente) -->
        <div v-if="isClient" class="space-y-1">
          <div class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
            Mi Cuenta
          </div>
          <button
            @click="handleNavigation('/dashboard')"
            class="w-full flex items-center space-x-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-300 hover:bg-slate-800/60 hover:text-white"
          >
            <i class="pi pi-globe text-sm text-cyan-400"></i>
            <span>Mis Dominios & Hosting</span>
          </button>
          <button
            @click="handleNavigation('/dashboard')"
            class="w-full flex items-center space-x-3 px-3.5 py-2 rounded-xl text-xs font-medium text-slate-300 hover:bg-slate-800/60 hover:text-white"
          >
            <i class="pi pi-credit-card text-sm text-emerald-400"></i>
            <span>Mis Pagos & Facturas</span>
          </button>
        </div>

        <!-- SECCIONES PARA ADMINISTRADORES / STAFF -->
        <template v-if="isSuperAdmin">
          <!-- 1. MÓDULO: CONSULTAS (CRM Core) -->
          <div class="space-y-1">
            <button
              @click="toggleModule('consultas')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
                  <i class="pi pi-search text-xs"></i>
                </span>
                <span class="uppercase tracking-wider text-[11px] font-bold">Consultas</span>
              </div>
              <i
                class="pi pi-chevron-down text-[10px] transition-transform duration-200 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.consultas }"
              ></i>
            </button>

            <div v-show="openModules.consultas" class="space-y-0.5 pl-3 pt-1 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/clientes')"
                :class="[
                  'w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs transition-colors',
                  route.path === '/clientes'
                    ? 'bg-blue-600/20 text-blue-400 font-bold border border-blue-500/30'
                    : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'
                ]"
              >
                <i class="pi pi-building text-xs" :class="route.path === '/clientes' ? 'text-blue-400' : 'text-slate-400'"></i>
                <span>Consulta de Clientes</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-globe text-slate-400 text-xs"></i>
                <span>Consulta de Dominios</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-server text-slate-400 text-xs"></i>
                <span>Consulta de Hosting</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-credit-card text-slate-400 text-xs"></i>
                <span>Consulta de Pagos</span>
              </button>
            </div>
          </div>

          <!-- 2. MÓDULO: REGISTROS -->
          <div class="space-y-1">
            <button
              @click="toggleModule('registros')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                  <i class="pi pi-plus-circle text-xs"></i>
                </span>
                <span class="uppercase tracking-wider text-[11px] font-bold">Registros</span>
              </div>
              <i
                class="pi pi-chevron-down text-[10px] transition-transform duration-200 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.registros }"
              ></i>
            </button>

            <div v-show="openModules.registros" class="space-y-0.5 pl-3 pt-1 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-user-plus text-slate-400 text-xs"></i>
                <span>Registro de Cliente</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-globe text-slate-400 text-xs"></i>
                <span>Registro de Dominio</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-cloud-upload text-slate-400 text-xs"></i>
                <span>Registro de Hosting</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-folder-plus text-slate-400 text-xs"></i>
                <span>Formulario Proyectos</span>
              </button>
            </div>
          </div>

          <!-- 3. MÓDULO: COMUNICACIÓN & LEADS -->
          <div class="space-y-1">
            <button
              @click="toggleModule('comunicacion')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center">
                  <i class="pi pi-comments text-xs"></i>
                </span>
                <span class="uppercase tracking-wider text-[11px] font-bold">Comunicación</span>
              </div>
              <i
                class="pi pi-chevron-down text-[10px] transition-transform duration-200 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.comunicacion }"
              ></i>
            </button>

            <div v-show="openModules.comunicacion" class="space-y-0.5 pl-3 pt-1 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-inbox text-slate-400 text-xs"></i>
                <span>Bandeja CRM</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-table text-slate-400 text-xs"></i>
                <span>Pipeline Kanban</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-whatsapp text-emerald-400 text-xs"></i>
                <span>WhatsApp Chats</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-robot text-cyan-400 text-xs"></i>
                <span>Chatbot & FAQ</span>
              </button>
            </div>
          </div>

          <!-- 4. MÓDULO: SOPORTE & TICKETS -->
          <div class="space-y-1">
            <button
              @click="toggleModule('soporte')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-indigo-500/10 text-indigo-400 flex items-center justify-center">
                  <i class="pi pi-ticket text-xs"></i>
                </span>
                <span class="uppercase tracking-wider text-[11px] font-bold">Soporte</span>
              </div>
              <i
                class="pi pi-chevron-down text-[10px] transition-transform duration-200 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.soporte }"
              ></i>
            </button>

            <div v-show="openModules.soporte" class="space-y-0.5 pl-3 pt-1 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-list text-slate-400 text-xs"></i>
                <span>Tickets de Soporte</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-file-edit text-slate-400 text-xs"></i>
                <span>Cotizaciones</span>
              </button>
            </div>
          </div>

          <!-- 5. MÓDULO: ANALÍTICA & WEB -->
          <div class="space-y-1">
            <button
              @click="toggleModule('analytics')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                  <i class="pi pi-chart-line text-xs"></i>
                </span>
                <span class="uppercase tracking-wider text-[11px] font-bold">Analítica & Web</span>
              </div>
              <i
                class="pi pi-chevron-down text-[10px] transition-transform duration-200 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.analytics }"
              ></i>
            </button>

            <div v-show="openModules.analytics" class="space-y-0.5 pl-3 pt-1 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-chart-bar text-slate-400 text-xs"></i>
                <span>Dashboard Analítico</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-filter text-slate-400 text-xs"></i>
                <span>Funnel Comercial</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-list-check text-slate-400 text-xs"></i>
                <span>Auditoría SEO</span>
              </button>
            </div>
          </div>

          <!-- 6. MÓDULO: SEGURIDAD & SISTEMA -->
          <div class="space-y-1">
            <button
              @click="toggleModule('seguridad')"
              class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors group"
            >
              <div class="flex items-center space-x-2.5">
                <span class="w-6 h-6 rounded-lg bg-rose-500/10 text-rose-400 flex items-center justify-center">
                  <i class="pi pi-shield text-xs"></i>
                </span>
                <span class="uppercase tracking-wider text-[11px] font-bold">Seguridad</span>
              </div>
              <i
                class="pi pi-chevron-down text-[10px] transition-transform duration-200 text-slate-500 group-hover:text-slate-300"
                :class="{ '-rotate-90': !openModules.seguridad }"
              ></i>
            </button>

            <div v-show="openModules.seguridad" class="space-y-0.5 pl-3 pt-1 border-l border-slate-800/80 ml-4">
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-lock text-slate-400 text-xs"></i>
                <span>Control de Accesos</span>
              </button>
              <button
                @click="handleNavigation('/dashboard')"
                class="w-full flex items-center space-x-2.5 px-3 py-2 rounded-lg text-xs text-slate-300 hover:bg-slate-800/60 hover:text-white transition-colors"
              >
                <i class="pi pi-sliders-h text-slate-400 text-xs"></i>
                <span>Planes HostingPro</span>
              </button>
            </div>
          </div>
        </template>
      </div>

      <!-- Footer del Sidebar: Usuario Conectado -->
      <div class="p-3.5 border-t border-slate-800/80 bg-slate-950/70 shrink-0">
        <div class="p-2.5 rounded-xl bg-slate-900/90 border border-slate-800/80 flex items-center justify-between">
          <div class="flex items-center space-x-3 overflow-hidden">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold flex items-center justify-center text-xs shrink-0 shadow-md">
              {{ (user?.nombre || user?.usuario || 'U').charAt(0).toUpperCase() }}
            </div>
            <div class="overflow-hidden">
              <div class="text-xs font-semibold text-white truncate">{{ user?.nombre || user?.usuario }}</div>
              <div class="text-[10px] text-emerald-400 font-medium flex items-center space-x-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                <span>En línea</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #1e293b;
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #334155;
}
</style>
