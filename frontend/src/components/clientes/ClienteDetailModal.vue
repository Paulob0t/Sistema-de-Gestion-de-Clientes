<script setup lang="ts">
import { ref } from 'vue'
import type { ClienteDetail } from '@/api/clientes'

defineProps<{
  isOpen: boolean
  isLoading: boolean
  cliente: ClienteDetail | null
  isSuperAdmin: boolean
  getInitials: (name: string) => string
  formatWhatsAppLink: (phone: string | null) => string
  formatCurrency: (amount: number, currency?: string) => string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'edit', cliente: ClienteDetail): void
}>()

const activeTab = ref<'info' | 'dominios' | 'hosting' | 'pagos'>('info')
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md"
  >
    <div class="w-full max-w-4xl bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Header Modal -->
      <div class="p-6 bg-slate-950/60 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-3">
          <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center font-bold text-white text-base shadow-lg shadow-blue-500/20">
            {{ getInitials(cliente?.empresa || '') }}
          </div>
          <div>
            <h3 class="text-lg font-bold text-white">{{ cliente?.empresa || 'Cargando...' }}</h3>
            <p class="text-xs text-slate-400">{{ cliente?.nombre_contacto }} • ID: #{{ cliente?.id }}</p>
          </div>
        </div>

        <div class="flex items-center space-x-2">
          <button
            v-if="cliente && isSuperAdmin"
            @click="emit('edit', cliente)"
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

      <!-- Pestañas del Detalle -->
      <div class="flex items-center px-6 pt-3 border-b border-slate-800 bg-slate-950/30 gap-4 text-xs font-semibold shrink-0">
        <button
          @click="activeTab = 'info'"
          :class="[
            'pb-3 transition-colors border-b-2',
            activeTab === 'info' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
          ]"
        >
          Información General & Fiscal
        </button>
        <button
          @click="activeTab = 'dominios'"
          :class="[
            'pb-3 transition-colors border-b-2 flex items-center space-x-1.5',
            activeTab === 'dominios' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
          ]"
        >
          <span>Dominios</span>
          <span class="px-1.5 py-0.2 rounded-full bg-blue-950 text-[10px] text-blue-300 font-bold border border-blue-500/30">
            {{ cliente?.total_dominios ?? 0 }}
          </span>
        </button>
        <button
          @click="activeTab = 'hosting'"
          :class="[
            'pb-3 transition-colors border-b-2 flex items-center space-x-1.5',
            activeTab === 'hosting' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
          ]"
        >
          <span>Hosting</span>
          <span class="px-1.5 py-0.2 rounded-full bg-emerald-950 text-[10px] text-emerald-300 font-bold border border-emerald-500/30">
            {{ cliente?.total_hostings ?? 0 }}
          </span>
        </button>
        <button
          @click="activeTab = 'pagos'"
          :class="[
            'pb-3 transition-colors border-b-2 flex items-center space-x-1.5',
            activeTab === 'pagos' ? 'border-blue-500 text-blue-400' : 'border-transparent text-slate-400 hover:text-white'
          ]"
        >
          <span>Historial Pagos</span>
          <span v-if="(cliente?.total_pagos_pendientes ?? 0) > 0" class="px-1.5 py-0.2 rounded-full bg-amber-950 text-[10px] text-amber-300 font-bold border border-amber-500/30">
            {{ cliente?.total_pagos_pendientes }}
          </span>
        </button>
      </div>

      <!-- Cuerpo del Modal -->
      <div class="p-6 overflow-y-auto flex-1 space-y-6">
        <div v-if="isLoading" class="py-12 text-center">
          <i class="pi pi-spin pi-spinner text-3xl text-blue-500"></i>
        </div>

        <template v-else-if="cliente">
          <!-- TAB 1: INFORMACIÓN GENERAL -->
          <div v-if="activeTab === 'info'" class="space-y-6 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-3">
                <h4 class="font-bold text-white text-sm">Datos de Contacto</h4>
                <div class="space-y-2 text-slate-300">
                  <div>
                    <span class="text-slate-500 block text-[11px]">Nombre de Contacto</span>
                    <strong class="text-white">{{ cliente.nombre_contacto || 'N/D' }}</strong>
                  </div>
                  <div>
                    <span class="text-slate-500 block text-[11px]">Correo Electrónico</span>
                    <a :href="`mailto:${cliente.correo}`" class="text-blue-400 hover:underline">
                      {{ cliente.correo || 'N/D' }}
                    </a>
                  </div>
                  <div>
                    <span class="text-slate-500 block text-[11px]">Teléfono / WhatsApp</span>
                    <div class="flex items-center space-x-2 mt-0.5">
                      <span class="text-white">{{ cliente.telefono || 'N/D' }}</span>
                      <a
                        v-if="cliente.telefono"
                        :href="formatWhatsAppLink(cliente.telefono)"
                        target="_blank"
                        class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold hover:bg-emerald-900"
                      >
                        Chat WhatsApp
                      </a>
                    </div>
                  </div>
                </div>
              </div>

              <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-3">
                <h4 class="font-bold text-white text-sm">Datos Fiscales & Facturación</h4>
                <div class="space-y-2 text-slate-300">
                  <div>
                    <span class="text-slate-500 block text-[11px]">Razón Social</span>
                    <strong class="text-white">{{ cliente.rsocial || 'N/D' }}</strong>
                  </div>
                  <div>
                    <span class="text-slate-500 block text-[11px]">RFC</span>
                    <span class="px-2 py-0.5 rounded bg-slate-900 text-blue-300 border border-blue-500/30 font-mono font-bold">
                      {{ cliente.rfc || 'XAXX010101000' }}
                    </span>
                  </div>
                  <div>
                    <span class="text-slate-500 block text-[11px]">Dirección Registrada</span>
                    <span class="text-slate-300">
                      {{ [cliente.calle, cliente.next, cliente.col, cliente.ciudad, cliente.estado].filter(Boolean).join(', ') || 'Sin dirección fiscal registrada' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Especificaciones / Notas -->
            <div v-if="cliente.especificacion" class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 space-y-2">
              <h4 class="font-bold text-white text-xs">Especificaciones / Requerimientos del Cliente</h4>
              <p class="text-slate-300 leading-relaxed whitespace-pre-wrap">{{ cliente.especificacion }}</p>
            </div>
          </div>

          <!-- TAB 2: DOMINIOS -->
          <div v-else-if="activeTab === 'dominios'" class="space-y-4 text-xs">
            <div v-if="cliente.dominios.length === 0" class="py-8 text-center text-slate-500">
              <i class="pi pi-globe text-3xl mb-2"></i>
              <p>No hay dominios web asociados a este cliente.</p>
            </div>

            <div v-else class="space-y-2.5">
              <div
                v-for="dom in cliente.dominios"
                :key="dom.id"
                class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
              >
                <div class="flex items-center space-x-3">
                  <div class="w-8 h-8 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center">
                    <i class="pi pi-globe"></i>
                  </div>
                  <div>
                    <a :href="`https://${dom.dominio}`" target="_blank" class="font-bold text-white hover:text-blue-400 text-sm">
                      {{ dom.dominio }}
                    </a>
                    <div class="text-[11px] text-slate-400">
                      Vencimiento: <strong>{{ dom.fecha_vencimiento || 'Sin fecha' }}</strong>
                    </div>
                  </div>
                </div>

                <div class="text-right">
                  <span
                    v-if="dom.dias_restantes !== null && dom.dias_restantes < 0"
                    class="px-2 py-0.5 rounded text-[10px] bg-red-950 text-red-300 border border-red-500/30 font-bold"
                  >
                    Venció hace {{ Math.abs(dom.dias_restantes) }} días
                  </span>
                  <span
                    v-else-if="dom.dias_restantes !== null && dom.dias_restantes <= 30"
                    class="px-2 py-0.5 rounded text-[10px] bg-amber-950 text-amber-300 border border-amber-500/30 font-bold"
                  >
                    Vence en {{ dom.dias_restantes }} días
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded text-[10px] bg-emerald-950 text-emerald-300 border border-emerald-500/30 font-bold"
                  >
                    Activo
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 3: HOSTING -->
          <div v-else-if="activeTab === 'hosting'" class="space-y-4 text-xs">
            <div v-if="cliente.hostings.length === 0" class="py-8 text-center text-slate-500">
              <i class="pi pi-server text-3xl mb-2"></i>
              <p>No hay planes de hosting asignados a este cliente.</p>
            </div>

            <div v-else class="space-y-2.5">
              <div
                v-for="h in cliente.hostings"
                :key="h.id"
                class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
              >
                <div class="flex items-center space-x-3">
                  <div class="w-8 h-8 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center">
                    <i class="pi pi-server"></i>
                  </div>
                  <div>
                    <h5 class="font-bold text-white text-sm">{{ h.nombre_plan }}</h5>
                    <p class="text-[11px] text-slate-400">{{ h.dominio }}</p>
                  </div>
                </div>

                <div class="text-right">
                  <span class="text-xs font-bold text-white block">{{ formatCurrency(h.precio) }}</span>
                  <span class="text-[10px] text-slate-400">Renovación: {{ h.fecha_vencimiento || 'N/D' }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- TAB 4: HISTORIAL DE PAGOS -->
          <div v-else-if="activeTab === 'pagos'" class="space-y-4 text-xs">
            <div v-if="cliente.pagos.length === 0" class="py-8 text-center text-slate-500">
              <i class="pi pi-credit-card text-3xl mb-2"></i>
              <p>No hay registros de pago para este cliente.</p>
            </div>

            <div v-else class="space-y-2.5">
              <div
                v-for="pago in cliente.pagos"
                :key="pago.id"
                class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between"
              >
                <div>
                  <h5 class="font-bold text-white">{{ pago.concepto }}</h5>
                  <p class="text-[11px] text-slate-400">Vencimiento: {{ pago.fecha_vencimiento || 'N/D' }}</p>
                </div>

                <div class="text-right space-y-1">
                  <div class="font-bold text-white">{{ formatCurrency(pago.monto, pago.moneda) }}</div>
                  <span
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-bold',
                      pago.estatus === 1 ? 'bg-emerald-950 text-emerald-300 border border-emerald-500/30' : 'bg-amber-950 text-amber-300 border border-amber-500/30'
                    ]"
                  >
                    {{ pago.estatus_texto }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
