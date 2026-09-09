<script setup lang="ts">
import type { DominioDetail } from '@/api/dominios'

defineProps<{
  isOpen: boolean
  isLoading: boolean
  dominio: DominioDetail | null
  isSuperAdmin: boolean
  formatCurrency: (amount: number, currency?: string) => string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'edit', dom: DominioDetail): void
}>()
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md"
  >
    <div class="w-full max-w-2xl bg-[#0D121F] border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header -->
      <div class="p-5 bg-slate-950/80 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-2xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center text-cyan-400 font-bold">
            <i class="pi pi-globe text-base"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-white">{{ dominio?.url_dominio || 'Detalle del Dominio' }}</h3>
            <p class="text-xs text-slate-400">{{ dominio?.cliente_empresa }} • ID: #{{ dominio?.id_dominio }}</p>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <button
            v-if="dominio && isSuperAdmin"
            @click="emit('edit', dominio)"
            class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 flex items-center space-x-1.5 transition-colors"
          >
            <i class="pi pi-pencil text-xs"></i>
            <span>Editar</span>
          </button>
          <button
            @click="emit('close')"
            class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
          >
            <i class="pi pi-times text-sm"></i>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6 overflow-y-auto flex-1 space-y-5 text-xs">
        <div v-if="isLoading" class="py-12 text-center">
          <i class="pi pi-spin pi-spinner text-3xl text-cyan-500"></i>
        </div>

        <template v-else-if="dominio">
          <!-- Datos Generales -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-2.5">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Infraestructura & Registro</span>
              <div>
                <span class="text-slate-400 block text-[11px]">Registrador / Proveedor</span>
                <strong class="text-white">{{ dominio.proveedor || 'NexusBot' }}</strong>
              </div>
              <div>
                <span class="text-slate-400 block text-[11px]">Costo de Renovación</span>
                <strong class="text-emerald-400 font-mono">{{ formatCurrency(dominio.costo_dominio) }}</strong>
              </div>
              <div>
                <span class="text-slate-400 block text-[11px]">Fecha de Vencimiento</span>
                <span
                  :class="[
                    'font-bold',
                    dominio.estado_vencimiento === 'vencido' ? 'text-rose-400' : 'text-slate-200'
                  ]"
                >
                  {{ dominio.fecha_pago || 'No especificada' }}
                </span>
              </div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-2.5">
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Titular / Cliente</span>
              <div>
                <span class="text-slate-400 block text-[11px]">Empresa</span>
                <strong class="text-white">{{ dominio.cliente_empresa }}</strong>
              </div>
              <div>
                <span class="text-slate-400 block text-[11px]">Contacto</span>
                <span class="text-slate-300">{{ dominio.cliente_nombre }}</span>
              </div>
              <div v-if="dominio.cliente_correo">
                <span class="text-slate-400 block text-[11px]">Correo</span>
                <a :href="`mailto:${dominio.cliente_correo}`" class="text-blue-400 hover:underline">
                  {{ dominio.cliente_correo }}
                </a>
              </div>
            </div>
          </div>

          <!-- Servidores DNS (Nameservers) -->
          <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-3">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Servidores DNS Configurados</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono">
              <div class="p-2 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">NS1:</span>
                <span class="text-cyan-300">{{ dominio.ns1 || 'ns1.nexusbot.io' }}</span>
              </div>
              <div class="p-2 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">NS2:</span>
                <span class="text-cyan-300">{{ dominio.ns2 || 'ns2.nexusbot.io' }}</span>
              </div>
              <div v-if="dominio.ns3" class="p-2 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">NS3:</span>
                <span class="text-cyan-300">{{ dominio.ns3 }}</span>
              </div>
              <div v-if="dominio.ns4" class="p-2 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-between">
                <span class="text-slate-500 text-[10px]">NS4:</span>
                <span class="text-cyan-300">{{ dominio.ns4 }}</span>
              </div>
            </div>
          </div>

          <!-- Accesos / Credenciales (Admin) -->
          <div v-if="isSuperAdmin && (dominio.url_admin || dominio.usuario || dominio.contrasena_normal)" class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-3">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Panel de Administración & Credenciales</span>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
              <div v-if="dominio.url_admin">
                <span class="text-slate-500 block text-[10px]">URL Admin</span>
                <a :href="dominio.url_admin" target="_blank" class="text-blue-400 hover:underline truncate block">
                  {{ dominio.url_admin }}
                </a>
              </div>
              <div v-if="dominio.usuario">
                <span class="text-slate-500 block text-[10px]">Usuario</span>
                <span class="text-white font-mono font-bold">{{ dominio.usuario }}</span>
              </div>
              <div v-if="dominio.contrasena_normal">
                <span class="text-slate-500 block text-[10px]">Contraseña</span>
                <span class="text-emerald-400 font-mono font-bold">{{ dominio.contrasena_normal }}</span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
