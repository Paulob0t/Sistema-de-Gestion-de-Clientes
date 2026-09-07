<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Button from 'primevue/button'

const router = useRouter()
const authStore = useAuthStore()

const user = computed(() => authStore.user)

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <!-- Barra Superior -->
    <header class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-10">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold shadow-md shadow-blue-500/20">
            CW
          </div>
          <div>
            <h1 class="text-lg font-bold text-slate-900 leading-tight">CONLINEWEB CRM</h1>
            <p class="text-xs text-slate-500">Gestión de Clientes & Servicios</p>
          </div>
        </div>

        <div class="flex items-center space-x-4">
          <div class="hidden sm:flex flex-col text-right">
            <span class="text-sm font-semibold text-slate-800">{{ user?.nombre || user?.usuario }}</span>
            <span class="text-xs text-blue-600 font-medium">{{ user?.rol }}</span>
          </div>
          <Button
            label="Cerrar Sesión"
            icon="pi pi-sign-out"
            severity="secondary"
            outlined
            size="small"
            @click="handleLogout"
            class="text-xs font-medium"
          />
        </div>
      </div>
    </header>

    <!-- Contenido Principal -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      <!-- Tarjeta de Bienvenida -->
      <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 rounded-2xl p-6 sm:p-8 text-white shadow-xl">
        <div class="max-w-3xl space-y-3">
          <div class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/15 text-blue-100 backdrop-blur-sm border border-white/20">
            <i class="pi pi-check-circle mr-1.5 text-emerald-400"></i>
            Migración de Autenticación Completada
          </div>
          <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">
            ¡Bienvenido(a), {{ user?.nombre || user?.usuario }}!
          </h2>
          <p class="text-blue-100 text-sm sm:text-base leading-relaxed">
            Has iniciado sesión correctamente con el nuevo stack <strong>FastAPI + SQLAlchemy + Vue 3 + Pinia</strong>. Tus credenciales de la base de datos MariaDB/MySQL han sido validadas exitosamente.
          </p>
        </div>
      </div>

      <!-- Resumen del Usuario Autenticado -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Tarjeta Perfil -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
          <div class="flex items-center space-x-3 text-slate-800">
            <div class="p-2.5 rounded-xl bg-blue-50 text-blue-600">
              <i class="pi pi-user text-xl"></i>
            </div>
            <h3 class="font-semibold text-base">Perfil de Cuenta</h3>
          </div>
          <div class="space-y-2.5 text-sm">
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">ID Usuario:</span>
              <span class="font-semibold text-slate-800">#{{ user?.id }}</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Usuario:</span>
              <span class="font-semibold text-slate-800">{{ user?.usuario }}</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Rol Asignado:</span>
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">
                {{ user?.rol }}
              </span>
            </div>
            <div v-if="user?.empresa" class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Empresa:</span>
              <span class="font-semibold text-slate-800">{{ user?.empresa }}</span>
            </div>
            <div v-if="user?.correo" class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Correo:</span>
              <span class="font-semibold text-slate-800">{{ user?.correo }}</span>
            </div>
          </div>
        </div>

        <!-- Tarjeta de Estado de Sesión -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
          <div class="flex items-center space-x-3 text-slate-800">
            <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600">
              <i class="pi pi-shield text-xl"></i>
            </div>
            <h3 class="font-semibold text-base">Seguridad & JWT</h3>
          </div>
          <div class="space-y-2.5 text-sm">
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Estado de Token:</span>
              <span class="text-emerald-600 font-semibold flex items-center">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                Activo (24 horas)
              </span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Algoritmo:</span>
              <span class="font-mono text-xs font-medium text-slate-700">HS256</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Almacenamiento:</span>
              <span class="font-medium text-slate-700">Pinia + LocalStorage</span>
            </div>
            <div class="flex justify-between py-1.5 border-b border-slate-100">
              <span class="text-slate-500">Compatibilidad:</span>
              <span class="text-xs font-semibold text-indigo-700">Bcrypt / MD5 Legacy</span>
            </div>
          </div>
        </div>

        <!-- Tarjeta Próximos Módulos -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm space-y-4">
          <div class="flex items-center space-x-3 text-slate-800">
            <div class="p-2.5 rounded-xl bg-indigo-50 text-indigo-600">
              <i class="pi pi-list text-xl"></i>
            </div>
            <h3 class="font-semibold text-base">Próximos Módulos</h3>
          </div>
          <p class="text-xs text-slate-500 leading-relaxed">
            Módulos listos para continuar migrando desde el monolito de PHP a FastAPI + PrimeVue:
          </p>
          <ul class="text-xs space-y-2 text-slate-600">
            <li class="flex items-center space-x-2">
              <i class="pi pi-users text-blue-500"></i>
              <span>Clientes & Contactos</span>
            </li>
            <li class="flex items-center space-x-2">
              <i class="pi pi-globe text-emerald-500"></i>
              <span>Dominios & Vencimientos</span>
            </li>
            <li class="flex items-center space-x-2">
              <i class="pi pi-server text-indigo-500"></i>
              <span>Hosting & Planes</span>
            </li>
            <li class="flex items-center space-x-2">
              <i class="pi pi-credit-card text-amber-500"></i>
              <span>Pagos, Renovaciones & Stripe</span>
            </li>
          </ul>
        </div>
      </div>
    </main>
  </div>
</template>
