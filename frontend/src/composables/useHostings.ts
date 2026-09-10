import { ref } from 'vue'
import { hostingsApi, type HostingListItem, type HostingStats } from '@/api/hostings'
import { useToast } from '@/composables/useToast'

export function useHostings() {
  const { showToast } = useToast()

  const isLoading = ref(false)
  const isSaving = ref(false)
  const hostings = ref<HostingListItem[]>([])
  const stats = ref<HostingStats>({
    total: 0,
    activos: 0,
    inactivos: 0,
    por_vencer_30d: 0,
    por_vencer_15d: 0,
    por_vencer_7d: 0,
    vencidos: 0,
    eliminados: 0,
  })

  const searchQuery = ref('')
  const activeFilter = ref('activos')
  const currentPage = ref(1)
  const totalItems = ref(0)
  const totalPages = ref(1)
  const limit = ref(20)
  const viewMode = ref<'table' | 'cards'>('table')

  let searchTimeout: any = null

  async function loadHostings() {
    isLoading.value = true
    try {
      const res = await hostingsApi.getHostings({
        search: searchQuery.value || undefined,
        filtro: activeFilter.value,
        page: currentPage.value,
        limit: limit.value,
      })
      hostings.value = res.items
      totalItems.value = res.total
      totalPages.value = res.total_pages
      stats.value = res.stats
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al cargar los servicios de hosting', 'error')
    } finally {
      isLoading.value = false
    }
  }

  function handleSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      currentPage.value = 1
      loadHostings()
    }, 300)
  }

  function clearSearch() {
    searchQuery.value = ''
    currentPage.value = 1
    loadHostings()
  }

  function setFilter(filterKey: string) {
    activeFilter.value = filterKey
    currentPage.value = 1
    loadHostings()
  }

  function changePage(page: number) {
    if (page < 1 || page > totalPages.value) return
    currentPage.value = page
    loadHostings()
  }

  async function removeHosting(id_orden: number, nom_host: string) {
    if (!confirm(`¿Estás seguro de mover el hosting "${nom_host}" a la papelera?`)) {
      return
    }
    try {
      const res = await hostingsApi.deleteHosting(id_orden, false)
      showToast(res.message || 'Hosting actualizado')
      loadHostings()
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al eliminar hosting', 'error')
    }
  }

  function formatWhatsAppRenewalLink(host: HostingListItem): string {
    const phone = host.cliente_telefono?.replace(/[^0-9]/g, '') || ''
    const vencimiento = host.fecha_pago || 'próximamente'
    const plan = host.tipo_producto || 'Alojamiento Web'
    const texto = `Hola ${host.cliente_nombre}, le escribimos de soporte para recordarle que su servicio de hosting (${host.nom_host} - Plan: ${plan}) tiene fecha de renovación el ${vencimiento}. ¿Desea que le apoyemos con el proceso de pago?`
    return `https://wa.me/${phone}?text=${encodeURIComponent(texto)}`
  }

  function formatCurrency(amount: number, moneda = 'MXN'): string {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: moneda,
    }).format(amount)
  }

  return {
    isLoading,
    isSaving,
    hostings,
    stats,
    searchQuery,
    activeFilter,
    currentPage,
    totalItems,
    totalPages,
    limit,
    viewMode,
    loadHostings,
    handleSearchInput,
    clearSearch,
    setFilter,
    changePage,
    removeHosting,
    formatWhatsAppRenewalLink,
    formatCurrency,
  }
}
