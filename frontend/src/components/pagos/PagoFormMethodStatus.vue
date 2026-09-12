<script setup lang="ts">
defineProps<{
  formData: {
    forma_pago?: number
    estatus?: number
    id_pago?: string
  }
}>()

const paymentMethods = [
  { id: 1, label: 'Transferencia / SPEI', icon: 'pi-building' },
  { id: 2, label: 'Efectivo / OXXO', icon: 'pi-wallet' },
  { id: 3, label: 'Tarjeta / Stripe', icon: 'pi-credit-card' },
  { id: 4, label: 'PayPal', icon: 'pi-paypal' },
]
</script>

<template>
  <div class="p-6 rounded-3xl bg-slate-900/70 border border-slate-800/80 shadow-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800/60">
      <div class="flex items-center space-x-3">
        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold">
          <i class="pi pi-credit-card text-sm"></i>
        </div>
        <div>
          <h2 class="text-sm font-bold text-white">Método de Pago & Estatus</h2>
          <p class="text-[11px] text-slate-400">Canal de recepción y acreditación del cobro</p>
        </div>
      </div>
    </div>

    <!-- Selección de Método de Pago -->
    <div>
      <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">
        Forma de Pago
      </label>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
        <button
          v-for="m in paymentMethods"
          :key="m.id"
          type="button"
          @click="formData.forma_pago = m.id"
          class="p-3 rounded-2xl border text-left transition-all flex items-center space-x-2.5"
          :class="formData.forma_pago === m.id
            ? 'bg-cyan-600/20 border-cyan-500/60 text-white shadow-lg shadow-cyan-500/10'
            : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white'"
        >
          <i class="pi text-sm" :class="[m.icon, formData.forma_pago === m.id ? 'text-cyan-400' : 'text-slate-500']"></i>
          <span class="text-xs font-bold">{{ m.label }}</span>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
      <!-- Estatus de Pago -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Estatus del Pago
        </label>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            @click="formData.estatus = 0"
            class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center space-x-2"
            :class="formData.estatus === 0
              ? 'bg-amber-500/20 border-amber-500/60 text-amber-300 shadow-lg shadow-amber-500/10'
              : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white'"
          >
            <i class="pi pi-clock text-xs"></i>
            <span>Pendiente</span>
          </button>
          <button
            type="button"
            @click="formData.estatus = 1"
            class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center space-x-2"
            :class="formData.estatus === 1
              ? 'bg-emerald-500/20 border-emerald-500/60 text-emerald-300 shadow-lg shadow-emerald-500/10'
              : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-white'"
          >
            <i class="pi pi-check-circle text-xs"></i>
            <span>Acreditado / Pagado</span>
          </button>
        </div>
      </div>

      <!-- Referencia / Folio de Transacción -->
      <div>
        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          Referencia / Folio Bancario
        </label>
        <input
          v-model="formData.id_pago"
          type="text"
          placeholder="ej. SPEI-849204 o Folio Stripe"
          class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-500 font-mono"
        />
      </div>
    </div>
  </div>
</template>
