<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { DominioDetail } from '@/api/dominios'

const router = useRouter()

const props = defineProps<{
  isOpen: boolean
  domain: DominioDetail | null
  rawPassword?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'reset-form'): void
}>()

function sendDomainWhatsApp() {
  if (!props.domain) return
  const phone = props.domain.cliente_telefono?.replace(/[^0-9]/g, '') || ''
  const pass = props.rawPassword || props.domain.contrasena_normal || '••••••••'
  const nsList = [props.domain.ns1, props.domain.ns2].filter(Boolean).join(', ')

  let texto = `¡Hola ${props.domain.cliente_nombre}! Tu dominio ha quedado registrado y configurado con éxito:\n\n`
  texto += `🌐 Dominio: https://${props.domain.url_dominio}\n`
  if (props.domain.url_admin) {
    texto += `🔑 Panel de Administración: ${props.domain.url_admin}\n`
    texto += `👤 Usuario: ${props.domain.usuario || 'admin'}\n`
    texto += `🔒 Contraseña: ${pass}\n`
  }
  if (nsList) {
    texto += `📡 Nameservers DNS: ${nsList}\n`
  }
  if (props.domain.fecha_pago) {
    texto += `📅 Próximo vencimiento: ${props.domain.fecha_pago}\n`
  }
  texto += `\n¡Quedamos a tu servicio en NexusBot!`

  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(texto)}`, '_blank')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen && domain"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-lg bg-[#0B101D] border border-cyan-500/40 rounded-3xl shadow-2xl p-6 space-y-5 animate-fadeIn relative">
        <button
          @click="emit('close')"
          class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg bg-slate-800/60 hover:bg-slate-700 transition-colors"
          title="Cerrar"
        >
          <i class="pi pi-times text-xs"></i>
        </button>

        <div class="flex items-center space-x-3.5">
          <div class="w-12 h-12 rounded-2xl bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 flex items-center justify-center text-xl font-bold">
            <i class="pi pi-globe"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-white">¡Dominio Registrado con Éxito!</h3>
            <p class="text-xs text-cyan-400 font-mono">https://{{ domain.url_dominio }}</p>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2 text-xs">
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Cliente / Empresa:</span>
            <span class="font-semibold text-white">{{ domain.cliente_empresa }} ({{ domain.cliente_nombre }})</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Proveedor:</span>
            <span class="text-slate-200">{{ domain.proveedor || 'NexusBot' }}</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Costo Base:</span>
            <span class="text-emerald-400 font-bold">${{ Number(domain.costo_dominio).toFixed(2) }}</span>
          </div>
          <div v-if="domain.fecha_pago" class="flex justify-between text-slate-300">
            <span class="text-slate-500">Vigencia / Renovación:</span>
            <span class="text-amber-400 font-semibold">{{ domain.fecha_pago }}</span>
          </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="space-y-2">
          <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">¿Qué deseas hacer ahora?</span>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <button
              @click="sendDomainWhatsApp"
              class="p-3 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-whatsapp text-sm"></i>
              <span>Enviar WhatsApp</span>
            </button>
            <button
              @click="router.push(`/hostings/nuevo?cliente_id=${domain.cliente_id}&dominio=${encodeURIComponent(domain.url_dominio)}`)"
              class="p-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-server text-sm"></i>
              <span>Asignar Hosting</span>
            </button>
            <button
              @click="router.push(`/pagos/nuevo?cliente_id=${domain.cliente_id}&tipo_servicio=2&id_servicio=${domain.id_dominio}&monto=${domain.costo_dominio}&concepto=${encodeURIComponent('Renovación de Dominio: ' + domain.url_dominio)}`)"
              class="p-3 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-credit-card text-sm"></i>
              <span>Generar Cobro</span>
            </button>
            <button
              @click="emit('reset-form')"
              class="p-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-plus text-sm"></i>
              <span>Registrar Otro Dominio</span>
            </button>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-800 flex justify-end">
          <button
            @click="router.push('/dominios')"
            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-colors"
          >
            Ir a Directorio de Dominios
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
