import { ref } from 'vue'
import { pagosApi, type PagoListItem, type PagoStats } from '@/api/pagos'
import { useToast } from '@/composables/useToast'

export function usePagos() {
  const { showToast } = useToast()

  const isLoading = ref(false)
  const isSaving = ref(false)
  const pagos = ref<PagoListItem[]>([])
  const stats = ref<PagoStats>({
    total_registros: 0,
    total_pendientes: 0,
    total_pagados: 0,
    total_vencidos: 0,
    total_eliminados: 0,
    monto_cobrado_mxn: 0,
    monto_cobrado_usd: 0,
    monto_pendiente_mxn: 0,
    monto_pendiente_usd: 0,
  })

  const searchQuery = ref('')
  const activeFilter = ref('todos')
  const fechaDesde = ref('')
  const fechaHasta = ref('')
  const currentPage = ref(1)
  const totalItems = ref(0)
  const totalPages = ref(1)
  const limit = ref(20)
  const viewMode = ref<'table' | 'cards'>('table')

  let searchTimeout: any = null

  async function loadPagos() {
    isLoading.value = true
    try {
      const res = await pagosApi.getPagos({
        search: searchQuery.value || undefined,
        filtro: activeFilter.value,
        fecha_desde: fechaDesde.value || undefined,
        fecha_hasta: fechaHasta.value || undefined,
        page: currentPage.value,
        limit: limit.value,
      })
      pagos.value = res.items
      totalItems.value = res.total
      totalPages.value = res.total_pages
      stats.value = res.stats
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al cargar los pagos', 'error')
    } finally {
      isLoading.value = false
    }
  }

  function handleSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      currentPage.value = 1
      loadPagos()
    }, 300)
  }

  function clearSearch() {
    searchQuery.value = ''
    fechaDesde.value = ''
    fechaHasta.value = ''
    currentPage.value = 1
    loadPagos()
  }

  function setFilter(filterKey: string) {
    activeFilter.value = filterKey
    currentPage.value = 1
    loadPagos()
  }

  function changePage(page: number) {
    if (page < 1 || page > totalPages.value) return
    currentPage.value = page
    loadPagos()
  }

  async function togglePaymentStatus(pago: PagoListItem) {
    const nuevoEstado = pago.estatus === 1 ? 0 : 1
    const accion = nuevoEstado === 1 ? 'acreditar' : 'marcar como pendiente'
    if (!confirm(`¿Deseas ${accion} el pago #${pago.id} por ${formatCurrency(pago.monto, pago.currency)}?`)) {
      return
    }
    try {
      await pagosApi.toggleStatus(pago.id, nuevoEstado)
      showToast(`Pago #${pago.id} ${nuevoEstado === 1 ? 'acreditado exitosamente' : 'marcado como pendiente'}`)
      loadPagos()
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al cambiar estatus del pago', 'error')
    }
  }

  async function removePago(id: number, concepto: string) {
    if (!confirm(`¿Estás seguro de mover a la papelera el cobro #${id} ("${concepto}")?`)) {
      return
    }
    try {
      const res = await pagosApi.deletePago(id, false)
      showToast(res.message || 'Pago actualizado')
      loadPagos()
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al eliminar pago', 'error')
    }
  }

  function formatWhatsAppPaymentLink(pago: PagoListItem): string {
    const phone = pago.cliente_telefono?.replace(/[^0-9]/g, '') || ''
    const montoFmt = formatCurrency(pago.monto, pago.currency)
    const texto = `Hola ${pago.cliente_nombre}, le contactamos de NexusBot CRM. Le compartimos la información de su cobro #${pago.id} (${pago.concepto}) por un monto de ${montoFmt}. Si ya realizó su pago, por favor compártanos su comprobante por este medio. ¡Muchas gracias!`
    return `https://wa.me/${phone}?text=${encodeURIComponent(texto)}`
  }

  function formatCurrency(amount: number, moneda = 'MXN'): string {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: (moneda || 'MXN').toUpperCase(),
    }).format(amount)
  }

  return {
    isLoading,
    isSaving,
    pagos,
    stats,
    searchQuery,
    activeFilter,
    fechaDesde,
    fechaHasta,
    currentPage,
    totalItems,
    totalPages,
    limit,
    viewMode,
    loadPagos,
    handleSearchInput,
    clearSearch,
    setFilter,
    changePage,
    togglePaymentStatus,
    removePago,
    formatWhatsAppPaymentLink,
    formatCurrency,
  }
}
