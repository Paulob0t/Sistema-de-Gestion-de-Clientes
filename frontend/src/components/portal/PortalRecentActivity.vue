<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { PortalSitioItem, PortalTicketItem } from '@/api/portal'

defineProps<{
  sitios: PortalSitioItem[]
  tickets: PortalTicketItem[]
}>()

const emit = defineEmits<{
  (e: 'open-ticket'): void
}>()

const router = useRouter()

function getStatusBadge(estado: string) {
  if (estado === 'Finalizado') {
    return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/20'
  } else if (estado === 'En Proceso') {
    return 'bg-blue-500/15 text-blue-300 border-blue-500/20'
  }
  return 'bg-amber-500/15 text-amber-300 border-amber-500/20'
}

function getPriorityBadge(prioridad: string) {
  if (prioridad === 'Alta') {
    return 'text-rose-400 bg-rose-500/10'
  } else if (prioridad === 'Media') {
    return 'text-amber-400 bg-amber-500/10'
  }
  return 'text-emerald-400 bg-emerald-500/10'
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- PANEL IZQUIERDO: MIS SITIOS WEB -->
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center">
              <i class="pi pi-desktop text-sm"></i>
            </div>
            <div>
              <h3 class="text-sm font-bold text-white">Mis Sitios Web Activos</h3>
              <p class="text-[11px] text-slate-400">Accede rápidamente a tus páginas publicadas</p>
            </div>
          </div>
          <button
            @click="router.push('/dominios')"
            class="text-xs font-semibold text-blue-400 hover:text-blue-300 transition-colors inline-flex items-center gap-1"
          >
            <span>Ver todos</span>
            <i class="pi pi-arrow-right text-[10px]"></i>
          </button>
        </div>

        <!-- Lista de sitios -->
        <div v-if="sitios.length > 0" class="space-y-2.5">
          <div
            v-for="s in sitios"
            :key="s.id"
            class="group rounded-xl bg-slate-950/60 border border-slate-800/80 p-3.5 flex items-center justify-between hover:border-blue-500/40 hover:bg-slate-950 transition-all"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-8 h-8 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-400 group-hover:text-blue-400 group-hover:border-blue-500/30 transition-colors shrink-0">
                <i class="pi pi-globe text-xs"></i>
              </div>
              <div class="min-w-0">
                <a
                  :href="s.url_dominio.startsWith('http') ? s.url_dominio : 'https://' + s.url_dominio"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-xs font-bold text-slate-200 group-hover:text-blue-400 transition-colors truncate block"
                >
                  {{ s.url_dominio }}
                </a>
                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-500">
                  <span v-if="s.proveedor">{{ s.proveedor }}</span>
                  <span v-if="s.fecha_pago">· Vence: {{ s.fecha_pago }}</span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <a
                :href="s.url_dominio.startsWith('http') ? s.url_dominio : 'https://' + s.url_dominio"
                target="_blank"
                rel="noopener noreferrer"
                class="p-2 rounded-lg bg-slate-900 hover:bg-blue-600 hover:text-white text-slate-400 border border-slate-800 transition-colors text-xs"
                title="Visitar sitio web"
              >
                <i class="pi pi-external-link"></i>
              </a>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 border border-dashed border-slate-800 rounded-xl">
          <i class="pi pi-globe text-3xl text-slate-600 mb-2"></i>
          <p class="text-xs text-slate-400">No hay sitios registrados en este momento</p>
        </div>
      </div>
    </div>

    <!-- PANEL DERECHO: TICKETS RECIENTES -->
    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 p-6 flex flex-col justify-between">
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center">
              <i class="pi pi-headphones text-sm"></i>
            </div>
            <div>
              <h3 class="text-sm font-bold text-white">Tickets de Soporte</h3>
              <p class="text-[11px] text-slate-400">Estado de tus requerimientos técnicos</p>
            </div>
          </div>
          <button
            @click="emit('open-ticket')"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 border border-purple-500/30 text-xs font-semibold transition-colors"
          >
            <i class="pi pi-plus text-[10px]"></i>
            <span>Nuevo</span>
          </button>
        </div>

        <!-- Lista de tickets -->
        <div v-if="tickets.length > 0" class="space-y-2.5">
          <div
            v-for="t in tickets"
            :key="t.id"
            @click="router.push('/solicitudes')"
            class="group cursor-pointer rounded-xl bg-slate-950/60 border border-slate-800/80 p-3.5 flex items-center justify-between hover:border-purple-500/40 hover:bg-slate-950 transition-all"
          >
            <div class="min-w-0 pr-3">
              <div class="flex items-center gap-2 mb-1">
                <span
                  class="px-2 py-0.5 text-[10px] font-semibold rounded-full border"
                  :class="getStatusBadge(t.estado)"
                >
                  {{ t.estado }}
                </span>
                <span
                  class="px-1.5 py-0.5 text-[10px] font-medium rounded"
                  :class="getPriorityBadge(t.prioridad)"
                >
                  {{ t.prioridad }}
                </span>
                <span v-if="t.fecha_solicitud" class="text-[10px] text-slate-500">
                  {{ t.fecha_solicitud }}
                </span>
              </div>
              <h4 class="text-xs font-bold text-slate-200 group-hover:text-purple-300 transition-colors truncate">
                #{{ t.id }} - {{ t.titulo }}
              </h4>
            </div>

            <div class="flex items-center gap-2 text-slate-500 text-xs shrink-0">
              <span v-if="t.total_notas > 0" class="inline-flex items-center gap-1 text-[11px] text-slate-400">
                <i class="pi pi-comments text-[10px]"></i>
                {{ t.total_notas }}
              </span>
              <i class="pi pi-chevron-right text-xs group-hover:translate-x-0.5 group-hover:text-purple-400 transition-transform"></i>
            </div>
          </div>
        </div>

        <div v-else class="text-center py-8 border border-dashed border-slate-800 rounded-xl">
          <i class="pi pi-inbox text-3xl text-slate-600 mb-2"></i>
          <p class="text-xs text-slate-400">No tienes tickets abiertos actualmente</p>
          <button
            @click="emit('open-ticket')"
            class="mt-3 text-xs font-semibold text-purple-400 hover:underline inline-flex items-center gap-1"
          >
            <span>Crear primer ticket</span>
            <i class="pi pi-arrow-right text-[10px]"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
