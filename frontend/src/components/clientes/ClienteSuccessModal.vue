<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { ClienteDetail } from '@/api/clientes'

const router = useRouter()

const props = defineProps<{
  isOpen: boolean
  client: ClienteDetail | null
  rawPassword?: string
}>()

const emit = defineEmits<{
  (e: 'close'): void
}>()

function sendWelcomeWhatsApp() {
  if (!props.client) return
  const phone = props.client.telefono?.replace(/[^0-9]/g, '') || ''
  const pass = props.rawPassword || `Nexus${props.client.id}*`
  const texto = `¡Hola ${props.client.nombre_contacto}! Te damos la bienvenida a NexusBot CRM. Tus credenciales de acceso al portal de clientes son:\n\n👤 Usuario: ${props.client.correo || 'Tu correo'}\n🔑 Contraseña: ${pass}\n🌐 Acceso: ${window.location.origin}/login\n\n¡Quedamos a tu servicio!`
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(texto)}`, '_blank')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen && client"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md"
      @click.self="emit('close')"
    >
      <div class="w-full max-w-lg bg-[#0B101D] border border-emerald-500/40 rounded-3xl shadow-2xl p-6 space-y-5 animate-fadeIn relative">
        <button
          @click="emit('close')"
          class="absolute top-5 right-5 text-slate-400 hover:text-white p-1 rounded-lg bg-slate-800/60 hover:bg-slate-700 transition-colors"
          title="Cerrar"
        >
          <i class="pi pi-times text-xs"></i>
        </button>
        <div class="flex items-center space-x-3.5">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-xl font-bold">
            <i class="pi pi-check-circle"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-white">¡Cliente Registrado con Éxito!</h3>
            <p class="text-xs text-slate-400">{{ client.empresa }} (#{{ client.id }})</p>
          </div>
        </div>

        <div class="p-4 rounded-2xl bg-slate-900/80 border border-slate-800 space-y-2 text-xs">
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Contacto:</span>
            <span class="font-semibold text-white">{{ client.nombre_contacto }}</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Correo:</span>
            <span class="font-mono text-cyan-400">{{ client.correo || 'Sin correo' }}</span>
          </div>
          <div class="flex justify-between text-slate-300">
            <span class="text-slate-500">Teléfono:</span>
            <span class="text-slate-200">{{ client.telefono || 'Sin teléfono' }}</span>
          </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="space-y-2">
          <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">¿Qué deseas hacer ahora?</span>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
            <button
              @click="sendWelcomeWhatsApp"
              class="p-3 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-whatsapp text-sm"></i>
              <span>Enviar WhatsApp</span>
            </button>
            <button
              @click="router.push(`/dominios/nuevo?cliente_id=${client.id}`)"
              class="p-3 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 text-cyan-400 border border-cyan-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-globe text-sm"></i>
              <span>Asignar Dominio</span>
            </button>
            <button
              @click="router.push(`/hostings/nuevo?cliente_id=${client.id}`)"
              class="p-3 rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-server text-sm"></i>
              <span>Asignar Hosting</span>
            </button>
            <button
              @click="router.push('/pagos')"
              class="p-3 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 border border-indigo-500/20 font-bold flex items-center space-x-2 transition-colors"
            >
              <i class="pi pi-credit-card text-sm"></i>
              <span>Generar Cobro</span>
            </button>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-800 flex justify-end">
          <button
            @click="router.push('/clientes')"
            class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition-colors"
          >
            Ir a Directorio de Clientes
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
