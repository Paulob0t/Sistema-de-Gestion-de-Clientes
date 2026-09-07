<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const credentials = reactive({
  usuario: '',
  contrasena: ''
})

const errorMessage = ref('')
const isSubmitting = ref(false)

async function handleLogin() {
  if (!credentials.usuario.trim()) {
    errorMessage.value = 'Por favor ingresa tu usuario o correo'
    return
  }
  if (!credentials.contrasena) {
    errorMessage.value = 'Por favor ingresa tu contraseña'
    return
  }

  errorMessage.value = ''
  isSubmitting.value = true

  try {
    const redirectUrl = await authStore.login({
      usuario: credentials.usuario.trim(),
      contrasena: credentials.contrasena
    })

    // Redirigir a la URL deseada o al dashboard
    const returnTo = (route.query.redirect as string) || redirectUrl || '/dashboard'
    router.push(returnTo)
  } catch (err: any) {
    errorMessage.value = err.message || 'Credenciales inválidas'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 p-4 sm:p-6 lg:p-8">
    <!-- Contenedor Principal con Sombra y Borde Suave -->
    <div class="w-full max-w-md bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-slate-200/80 overflow-hidden">
      <!-- Cabecera / Marca -->
      <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-8 text-center text-white relative">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-white/10 backdrop-blur-sm mb-4 border border-white/20 shadow-inner">
          <i class="pi pi-shield text-3xl text-blue-200"></i>
        </div>
        <h1 class="text-2xl font-bold tracking-tight">CONLINEWEB</h1>
        <p class="text-blue-100 text-sm mt-1 font-medium">Gestión de Clientes & CRM</p>
      </div>

      <!-- Formulario de Login -->
      <div class="p-8 space-y-6">
        <div class="text-center">
          <h2 class="text-xl font-semibold text-slate-800">Iniciar Sesión</h2>
          <p class="text-sm text-slate-500 mt-1">Ingresa tus credenciales para acceder a tu panel</p>
        </div>

        <!-- Alerta de Error -->
        <Message v-if="errorMessage" severity="error" :closable="true" @close="errorMessage = ''" class="text-sm">
          {{ errorMessage }}
        </Message>

        <form @submit.prevent="handleLogin" class="space-y-5">
          <!-- Campo Usuario -->
          <div class="space-y-1.5">
            <label for="usuario" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
              Usuario o Correo
            </label>
            <div class="p-input-icon-left w-full">
              <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="pi pi-user text-base"></i>
              </span>
              <InputText
                id="usuario"
                v-model="credentials.usuario"
                placeholder="ej. contacto@empresa.com"
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 text-sm transition-all shadow-sm"
                :disabled="isSubmitting"
                autocomplete="username"
                autofocus
              />
            </div>
          </div>

          <!-- Campo Contraseña -->
          <div class="space-y-1.5">
            <label for="contrasena" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
              Contraseña
            </label>
            <div class="w-full">
              <Password
                id="contrasena"
                v-model="credentials.contrasena"
                placeholder="Tu contraseña"
                :feedback="false"
                toggleMask
                class="w-full"
                inputClass="w-full pl-4 pr-10 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-100 text-sm transition-all shadow-sm"
                :disabled="isSubmitting"
                autocomplete="current-password"
              />
            </div>
          </div>

          <!-- Botón Ingresar -->
          <Button
            type="submit"
            label="Ingresar al Sistema"
            icon="pi pi-sign-in"
            :loading="isSubmitting"
            class="w-full py-3 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl shadow-lg shadow-blue-500/25 transition-all duration-200 focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
          />
        </form>

        <!-- Información de compatibilidad de migración -->
        <div class="pt-4 border-t border-slate-100 text-center">
          <div class="flex items-center justify-center space-x-1.5 text-xs text-slate-400">
            <i class="pi pi-lock text-[10px]"></i>
            <span>Conexión segura SSL y cifrado compatible con PHP Monolito</span>
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
</style>
