import { ref, watch } from 'vue'
import {
  dominiosApi,
  type DominioListItem,
  type DominioStats,
} from '@/api/dominios'
import { useToast } from './useToast'

export function useDominios() {
  const { showToast } = useToast()

  const isLoading = ref(true)
  const isSaving = ref(false)
  const dominios = ref<DominioListItem[]>([])
  const stats = ref<DominioStats>({
    total: 0,
    activos: 0,
    por_vencer_30d: 0,
    vencidos: 0,
    pagados: 0,
    pendientes_pago: 0,
  })

  const searchQuery = ref('')
  const activeFilter = ref<string>('todos')
  const currentSistema = ref<'conlineweb' | 'hostingpro'>('conlineweb')
  const currentPage = ref(1)
  const itemsPerPage = ref(20)
  const totalItems = ref(0)
  const totalPages = ref(1)
  const viewMode = ref<'table' | 'cards'>('table')

  async function loadDominios() {
    isLoading.value = true
    try {
      const res = await dominiosApi.getDominios({
        search: searchQuery.value || undefined,
        filtro: activeFilter.value,
        sistema: currentSistema.value,
        page: currentPage.value,
        limit: itemsPerPage.value,
      })
      dominios.value = res.items
      totalItems.value = res.total
      totalPages.value = res.total_pages
      stats.value = res.stats
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al cargar los dominios', 'error')
    } finally {
      isLoading.value = false
    }
  }

  let searchTimeout: any = null
  function handleSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      currentPage.value = 1
      loadDominios()
    }, 350)
  }

  function clearSearch() {
    searchQuery.value = ''
    currentPage.value = 1
    loadDominios()
  }

  function setFilter(filtro: string) {
    activeFilter.value = filtro
    currentPage.value = 1
    loadDominios()
  }

  function changePage(page: number) {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
      loadDominios()
    }
  }

  async function removeDominio(dom: DominioListItem): Promise<boolean> {
    const confirmDelete = window.confirm(`¿Estás seguro de que deseas eliminar el dominio "${dom.url_dominio}"?`)
    if (!confirmDelete) return false

    try {
      await dominiosApi.deleteDominio(dom.id_dominio)
      showToast(`Dominio "${dom.url_dominio}" eliminado`)
      await loadDominios()
      return true
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al eliminar dominio', 'error')
      return false
    }
  }

  function formatWhatsAppRenewalLink(phone: string | null, cliente: string, dominio: string, vencimiento: string | null): string {
    if (!phone) return '#'
    const cleanPhone = phone.replace(/[^0-9]/g, '')
    const msg = encodeURIComponent(
      `Hola ${cliente}, le saludamos de NexusBot. Le notificamos que su dominio "${dominio}" tiene fecha de renovación para el ${vencimiento || 'próximo periodo'}. ¿Desea proceder con la renovación?`
    )
    return `https://api.whatsapp.com/send?phone=${cleanPhone}&text=${msg}`
  }

  function formatCurrency(amount: number, currency: string = 'MXN'): string {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: currency || 'MXN',
      minimumFractionDigits: 2
    }).format(amount)
  }

  watch(currentSistema, () => {
    currentPage.value = 1
    loadDominios()
  })

  return {
    isLoading,
    isSaving,
    dominios,
    stats,
    searchQuery,
    activeFilter,
    currentSistema,
    currentPage,
    itemsPerPage,
    totalItems,
    totalPages,
    viewMode,
    loadDominios,
    handleSearchInput,
    clearSearch,
    setFilter,
    changePage,
    removeDominio,
    formatWhatsAppRenewalLink,
    formatCurrency,
  }
}
