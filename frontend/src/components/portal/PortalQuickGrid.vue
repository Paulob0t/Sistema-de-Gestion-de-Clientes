<script setup lang="ts">
import { useRouter } from 'vue-router'

defineProps<{
  totalSitios: number
  totalPendientes: number
  totalTicketsAbiertos: number
}>()

const router = useRouter()
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-base md:text-lg font-bold text-white tracking-tight">Accesos Rápidos</h2>
        <p class="text-xs text-slate-400">Gestiona tus productos y herramientas desde un solo lugar</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- 1. PAGOS -->
      <div
        @click="router.push('/pagos')"
        :class="[
          'group cursor-pointer rounded-2xl border p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl',
          totalPendientes > 0
            ? 'bg-gradient-to-br from-amber-950/20 via-slate-900 to-slate-900 border-amber-500/30 hover:border-amber-500/60 hover:shadow-amber-500/10'
            : 'bg-slate-900/80 hover:bg-slate-900 border-slate-800 hover:border-emerald-500/40 hover:shadow-emerald-500/10'
        ]"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
              <i class="pi pi-credit-card text-xl"></i>
            </div>
            <span
              v-if="totalPendientes > 0"
              class="px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 text-[11px] font-bold border border-amber-500/30"
            >
              {{ totalPendientes }} por pagar
            </span>
          </div>
          <h3 class="text-sm font-bold text-white group-hover:text-emerald-300 transition-colors">Pagos & Facturas</h3>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            Consulta historial de pagos realizados, recibos digitales y saldos pendientes.
          </p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-emerald-400">
          <span>Abrir módulo</span>
          <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
        </div>
      </div>

      <!-- 2. MIS SITIOS WEB -->
      <div
        @click="router.push('/dominios')"
        class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-blue-500/40 p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-500/10"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform">
              <i class="pi pi-desktop text-xl"></i>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-blue-500/15 text-blue-300 text-[11px] font-semibold border border-blue-500/20">
              {{ totalSitios }} sitios
            </span>
          </div>
          <h3 class="text-sm font-bold text-white group-hover:text-blue-300 transition-colors">Mis Sitios Web</h3>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            Acceso a paneles administrativos, cPanel/WHM y visualización en vivo de tus páginas.
          </p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-blue-400">
          <span>Explorar sitios</span>
          <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
        </div>
      </div>

      <!-- 3. GESTIÓN DE DOMINIOS -->
      <div
        @click="router.push('/dominios')"
        class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-cyan-500/40 p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-cyan-500/10"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
              <i class="pi pi-globe text-xl"></i>
            </div>
          </div>
          <h3 class="text-sm font-bold text-white group-hover:text-cyan-300 transition-colors">Gestión de Dominios</h3>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            Configuración de servidores DNS, transferencias, fechas de vencimiento y renovación.
          </p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-cyan-400">
          <span>Administrar dominios</span>
          <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
        </div>
      </div>

      <!-- 4. HOSTING & SERVIDORES -->
      <div
        @click="router.push('/hostings')"
        class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-indigo-500/40 p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-indigo-500/10"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center group-hover:scale-110 transition-transform">
              <i class="pi pi-server text-xl"></i>
            </div>
          </div>
          <h3 class="text-sm font-bold text-white group-hover:text-indigo-300 transition-colors">Servicios de Hosting</h3>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            Consulta detalles de tu plan de alojamiento web, credenciales y estado del servidor.
          </p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-indigo-400">
          <span>Ver hosting</span>
          <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
        </div>
      </div>

      <!-- 5. SOPORTE TÉCNICO & TICKETS -->
      <div
        @click="router.push('/solicitudes')"
        class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-purple-500/40 p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-purple-500/10"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform">
              <i class="pi pi-headphones text-xl"></i>
            </div>
            <span
              v-if="totalTicketsAbiertos > 0"
              class="px-2.5 py-1 rounded-full bg-purple-500/20 text-purple-300 text-[11px] font-semibold border border-purple-500/30"
            >
              {{ totalTicketsAbiertos }} activos
            </span>
          </div>
          <h3 class="text-sm font-bold text-white group-hover:text-purple-300 transition-colors">Soporte Técnico</h3>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            Crea solicitudes de cambios en tu web, soporte de correo y seguimiento en tiempo real.
          </p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-purple-400">
          <span>Mis tickets</span>
          <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
        </div>
      </div>

      <!-- 6. MI CUENTA & PERFIL -->
      <div
        @click="router.push('/clientes')"
        class="group cursor-pointer rounded-2xl bg-slate-900/80 hover:bg-slate-900 border border-slate-800 hover:border-rose-500/40 p-5 flex flex-col justify-between transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-rose-500/10"
      >
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center group-hover:scale-110 transition-transform">
              <i class="pi pi-user text-xl"></i>
            </div>
          </div>
          <h3 class="text-sm font-bold text-white group-hover:text-rose-300 transition-colors">Mi Cuenta</h3>
          <p class="text-xs text-slate-400 mt-1 leading-relaxed">
            Actualiza datos de contacto, facturación fiscal y preferencias de tu cuenta.
          </p>
        </div>
        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs font-semibold text-rose-400">
          <span>Ver mi perfil</span>
          <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
        </div>
      </div>
    </div>
  </div>
</template>
