<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { HostingDetail } from '@/api/hostings'

const router = useRouter()

const props = defineProps<{
  isOpen: boolean
  hosting: HostingDetail | null
  rawPassword?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'reset-form'): void
}>()

function sendHostingWhatsApp() {
  if (!props.hosting) return
  const phone = props.hosting.cliente_telefono?.replace(/[^0-9]/g, '') || ''
  const pass = props.rawPassword || props.hosting.contrasena_normal || '••••••••'
  const nsList = [props.hosting.ns1, props.hosting.ns2].filter(Boolean).join(', ')

  let texto = `¡Hola ${props.hosting.cliente_nombre}! Tu servicio de Hosting ha quedado aprovisionado con éxito en NexusBot:\n\n`
  texto += `🖥️ Servidor / Host: ${props.hosting.nom_host}\n`
  if (props.hosting.dominio) {
    texto += `🌐 Dominio Principal: https://${props.hosting.dominio}\n`
  }
  if (props.hosting.url_acceso) {
    texto += `🔑 Panel cPanel: ${props.hosting.url_acceso}\n`
  }
  texto += `👤 Usuario: ${props.hosting.usuario || 'admin'}\n`
  texto += `🔒 Contraseña: ${pass}\n`
  if (nsList) {
    texto += `📡 Nameservers DNS: ${nsList}\n`
  }
  if (props.hosting.fecha_pago) {
    texto += `📅 Próximo vencimiento: ${props.hosting.fecha_pago}\n`
  }
  texto += `\n¡Quedamos a tu servicio!`

  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(texto)}`, '_blank')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen && hosting"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-lg bg-[#0B101D] border border-amber-500/40 rounded-3xl shadow-2xl p-6 space-y-5 animate-fadeIn relative">
        <button
          @click="emit('close')"
          class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg bg-slate-800/60 hover:bg-slate-700 transition-colors"
          title="Cerrar"
        >
          <i class="pi pi-times text-xs"></i>
        </button>

        <div class="flex items-center space-x-3.5">
          <div class="w-12 h-12 rounded-2xl bg-amber-500/20 text-amber-400 border border-amber-500/30 flex items-center justify-center text-xl font-bold">
            <i class="pi pi-server"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-white">¡Hosting Registrado con Éxito!</h3>
            <p class="text-xs text-amber-400 font-mono">{{ hosting.nom_host }} (#{{ hosting.id_orden }})</p>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2 text-xs">
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Cliente:</span>
            <span class="font-semibold text-white">{{ hosting.cliente_empresa }} ({{ hosting.cliente_nombre }})</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Plan / Servicio:</span>
            <span class="text-slate-200">{{ hosting.tipo_producto || 'Hosting Compartido' }}</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Tarifa:</span>
            <span class="text-emerald-400 font-bold">${{ Number(hosting.costo_producto).toFixed(2) }} {{ hosting.moneda }} ({{ hosting.frecuencia_label }})</span>
          </div>
          <div v-if="hosting.fecha_pago" class="flex justify-between text-slate-300">
            <span class="text-slate-500">Vigencia / Renovación:</span>
            <span class="text-amber-400 font-semibold">{{ hosting.fecha_pago }}</span>
          </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="space-y-2">
          <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">¿Qué deseas hacer ahora?</span>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <button
              @click="sendHostingWhatsApp"
              class="p-3 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-whatsapp text-sm"></i>
              <span>Enviar WhatsApp</span>
            </button>
            <button
              @click="router.push(`/pagos?cliente_id=${hosting.cliente_id}`)"
              class="p-3 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-credit-card text-sm"></i>
              <span>Generar Cobro</span>
            </button>
            <button
              @click="router.push(`/dominios/nuevo?cliente_id=${hosting.cliente_id}`)"
              class="p-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-globe text-sm"></i>
              <span>Asignar Dominio</span>
            </button>
            <button
              @click="emit('reset-form')"
              class="p-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-plus text-sm"></i>
              <span>Registrar Otro Hosting</span>
            </button>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-800 flex justify-end">
          <button
            @click="router.push('/hostings')"
            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-colors"
          >
            Ir a Directorio de Hostings
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
