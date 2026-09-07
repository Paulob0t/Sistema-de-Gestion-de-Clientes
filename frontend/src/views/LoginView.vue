<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Password from 'primevue/password'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const credentials = reactive({
  usuario: '',
  contrasena: ''
})

const rememberMe = ref(true)
const errorMessage = ref('')
const isSubmitting = ref(false)

async function handleLogin() {
  if (!credentials.usuario.trim()) {
    errorMessage.value = 'Por favor ingresa tu usuario o correo electrónico'
    return
  }
  if (!credentials.contrasena) {
    errorMessage.value = 'Por favor ingresa tu contraseña de acceso'
    return
  }

  errorMessage.value = ''
  isSubmitting.value = true

  try {
    const redirectUrl = await authStore.login({
      usuario: credentials.usuario.trim(),
      contrasena: credentials.contrasena
    })

    const returnTo = (route.query.redirect as string) || redirectUrl || '/dashboard'
    router.push(returnTo)
  } catch (err: any) {
    errorMessage.value = err.message || 'Usuario o contraseña incorrectos'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen w-full flex flex-col lg:flex-row bg-[#0B0F19] text-slate-100 font-sans selection:bg-blue-600 selection:text-white relative overflow-hidden">
    <!-- Luces ambientales de fondo -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600/20 rounded-full blur-[140px] pointer-events-none"></div>
    <div class="absolute bottom-0 right-0 w-[500px] h-[500px] bg-indigo-600/15 rounded-full blur-[160px] pointer-events-none"></div>
    <div class="absolute top-1/2 left-1/3 w-80 h-80 bg-cyan-500/10 rounded-full blur-[120px] pointer-events-none"></div>

    <!-- SECCIÓN IZQUIERDA: HERO EMPRESARIAL (Desktop) -->
    <div class="hidden lg:flex lg:w-7/12 flex-col justify-between p-12 xl:p-16 relative z-10 border-r border-slate-800/60 bg-gradient-to-br from-slate-950/80 via-slate-900/40 to-slate-950/80 backdrop-blur-xl">
      <!-- Marca y Badge -->
      <div class="space-y-6">
        <div class="flex items-center space-x-3.5">
          <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 via-indigo-500 to-cyan-400 p-0.5 shadow-xl shadow-blue-500/20">
            <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
              <i class="pi pi-bolt text-2xl text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-cyan-300"></i>
            </div>
          </div>
          <div>
            <span class="text-2xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-100 to-slate-400">
              NEXUS<span class="text-blue-500">CORE</span>
            </span>
            <span class="block text-[11px] font-semibold tracking-widest text-slate-400 uppercase">
              Enterprise Cloud & CRM Suite
            </span>
          </div>
        </div>

        <div class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-300 text-xs font-medium">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>Sistema Operativo v2.0 • Migración de Alto Rendimiento</span>
        </div>
      </div>

      <!-- Título Central y Propuesta de Valor -->
      <div class="space-y-8 my-auto py-12 max-w-xl">
        <div class="space-y-4">
          <h1 class="text-4xl xl:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Gestión inteligente de clientes, infraestructura y servicios.
          </h1>
          <p class="text-slate-400 text-base xl:text-lg leading-relaxed font-normal">
            Control centralizado para dominios, hosting, facturación y tickets con tecnología reactiva y seguridad de grado empresarial.
          </p>
        </div>

        <!-- Tarjetas de Métricas en Vivo / Glassmorphism -->
        <div class="grid grid-cols-3 gap-4 pt-2">
          <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/[0.08] backdrop-blur-md">
            <div class="text-2xl font-bold text-white tracking-tight">99.98%</div>
            <div class="text-xs text-slate-400 font-medium mt-1">Uptime de Red</div>
          </div>
          <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/[0.08] backdrop-blur-md">
            <div class="text-2xl font-bold text-blue-400 tracking-tight">&lt; 15ms</div>
            <div class="text-xs text-slate-400 font-medium mt-1">FastAPI Core</div>
          </div>
          <div class="p-4 rounded-2xl bg-white/[0.03] border border-white/[0.08] backdrop-blur-md">
            <div class="text-2xl font-bold text-emerald-400 tracking-tight">SSL / JWT</div>
            <div class="text-xs text-slate-400 font-medium mt-1">Bcrypt Hash</div>
          </div>
        </div>

        <!-- Tarjeta Flotante Informativa -->
        <div class="p-5 rounded-2xl bg-gradient-to-r from-blue-950/40 to-slate-900/60 border border-blue-500/20 backdrop-blur-md flex items-center space-x-4 shadow-2xl">
          <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center text-blue-400 shrink-0">
            <i class="pi pi-database text-lg"></i>
          </div>
          <div class="text-xs space-y-0.5">
            <div class="font-semibold text-white">Base de Datos Conectada</div>
            <div class="text-slate-400">Sincronización en tiempo real con MariaDB / MySQL sin pérdida de datos.</div>
          </div>
        </div>
      </div>

      <!-- Footer Izquierdo -->
      <div class="flex items-center justify-between text-xs text-slate-500 pt-6 border-t border-slate-800/60">
        <span>© 2026 NexusCore Enterprise. Todos los derechos reservados.</span>
        <span class="flex items-center space-x-1">
          <i class="pi pi-shield text-emerald-400"></i>
          <span class="text-slate-400 font-medium">Seguridad Activa</span>
        </span>
      </div>
    </div>

    <!-- SECCIÓN DERECHA: FORMULARIO DE ACCESO -->
    <div class="w-full lg:w-5/12 flex items-center justify-center p-6 sm:p-10 lg:p-12 xl:p-16 relative z-10">
      <div class="w-full max-w-md space-y-8">
        <!-- Logo en Móvil -->
        <div class="lg:hidden flex items-center space-x-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-cyan-400 p-0.5">
            <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
              <i class="pi pi-bolt text-xl text-cyan-400"></i>
            </div>
          </div>
          <div>
            <span class="text-xl font-bold text-white">NEXUS<span class="text-blue-500">CORE</span></span>
            <span class="block text-[10px] text-slate-400">Enterprise CRM</span>
          </div>
        </div>

        <!-- Encabezado del Formulario -->
        <div class="space-y-2">
          <div class="inline-flex items-center px-2.5 py-1 rounded-md bg-white/[0.06] border border-white/[0.1] text-slate-300 text-[11px] font-semibold uppercase tracking-wider">
            Portal Seguro de Acceso
          </div>
          <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
            Iniciar Sesión
          </h2>
          <p class="text-slate-400 text-sm">
            Ingresa tus credenciales para acceder al panel de control.
          </p>
        </div>

        <!-- Alerta de Error Elegante -->
        <transition name="fade">
          <div v-if="errorMessage" class="p-4 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 text-xs flex items-start space-x-3 shadow-lg shadow-red-950/40">
            <i class="pi pi-exclamation-triangle text-base text-red-400 shrink-0 mt-0.5"></i>
            <div class="flex-1 font-medium">{{ errorMessage }}</div>
            <button @click="errorMessage = ''" class="text-red-400 hover:text-red-200">
              <i class="pi pi-times text-xs"></i>
            </button>
          </div>
        </transition>

        <!-- Formulario -->
        <form @submit.prevent="handleLogin" class="space-y-5">
          <!-- Campo Usuario / Correo -->
          <div class="space-y-2">
            <label for="usuario" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
              Usuario o Correo Electrónico
            </label>
            <div class="relative rounded-xl shadow-sm">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="pi pi-user text-sm"></i>
              </span>
              <input
                id="usuario"
                v-model="credentials.usuario"
                type="text"
                placeholder="ej. admin o contacto@empresa.com"
                autocomplete="username"
                autofocus
                :disabled="isSubmitting"
                class="w-full pl-10 pr-4 py-3 bg-slate-900/90 text-white placeholder-slate-500 rounded-xl border border-slate-700/80 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 text-sm transition-all duration-200 outline-none hover:border-slate-600 disabled:opacity-50"
              />
            </div>
          </div>

          <!-- Campo Contraseña -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label for="contrasena" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                Contraseña
              </label>
            </div>
            <div class="relative rounded-xl shadow-sm">
              <Password
                id="contrasena"
                v-model="credentials.contrasena"
                placeholder="••••••••••••"
                :feedback="false"
                toggleMask
                class="w-full"
                inputClass="w-full pl-4 pr-11 py-3 bg-slate-900/90 text-white placeholder-slate-500 rounded-xl border border-slate-700/80 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 text-sm transition-all duration-200 outline-none hover:border-slate-600 disabled:opacity-50"
                :disabled="isSubmitting"
                autocomplete="current-password"
              />
            </div>
          </div>

          <!-- Opciones extras: Recordar y enlace -->
          <div class="flex items-center justify-between text-xs pt-1">
            <label class="flex items-center space-x-2 cursor-pointer select-none text-slate-400 hover:text-slate-300">
              <input
                type="checkbox"
                v-model="rememberMe"
                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-blue-600 focus:ring-blue-500/40 focus:ring-offset-slate-900"
              />
              <span>Mantener sesión activa</span>
            </label>
            <span class="text-slate-500 hover:text-blue-400 cursor-pointer transition-colors">
              ¿Olvidaste tu contraseña?
            </span>
          </div>

          <!-- Botón de Ingreso Principal -->
          <button
            type="submit"
            :disabled="isSubmitting"
            class="w-full py-3.5 px-6 rounded-xl font-semibold text-sm text-white bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 hover:from-blue-500 hover:via-indigo-500 hover:to-blue-600 focus:ring-4 focus:ring-blue-500/30 shadow-xl shadow-blue-600/25 transition-all duration-200 flex items-center justify-center space-x-2 disabled:opacity-60 disabled:cursor-not-allowed group active:scale-[0.99]"
          >
            <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-base"></i>
            <span v-else class="flex items-center space-x-2">
              <span>Acceder al Panel</span>
              <i class="pi pi-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
            </span>
          </button>
        </form>

        <!-- Pie de Página del Formulario -->
        <div class="pt-6 border-t border-slate-800/80 text-center space-y-2">
          <div class="flex items-center justify-center space-x-2 text-xs text-slate-500">
            <i class="pi pi-lock text-emerald-400 text-xs"></i>
            <span>Autenticación segura con cifrado JWT & Bcrypt</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
:deep(.p-password) {
  width: 100%;
}
:deep(.p-password-input) {
  width: 100%;
}
:deep(.p-password .pi) {
  color: #94a3b8;
}
:deep(.p-password .pi:hover) {
  color: #f8fafc;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
