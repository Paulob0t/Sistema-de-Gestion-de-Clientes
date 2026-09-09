import { ref, watch } from 'vue'
import {
  clientesApi,
  type ClienteListItem,
  type ClienteStats,
} from '@/api/clientes'
import { useToast } from './useToast'

export function useClientes() {
  const { showToast } = useToast()

  const isLoading = ref(true)
  const isSaving = ref(false)
  const clientes = ref<ClienteListItem[]>([])
  const stats = ref<ClienteStats>({
    total: 0,
    activos: 0,
    con_pagos_pendientes: 0,
    transferidos: 0,
    eliminados: 0,
  })

  const searchQuery = ref('')
  const activeFilter = ref<string>('activos')
  const currentSistema = ref<'conlineweb' | 'hostingpro'>('conlineweb')
  const currentPage = ref(1)
  const itemsPerPage = ref(20)
  const totalItems = ref(0)
  const totalPages = ref(1)
  const viewMode = ref<'table' | 'cards'>('table')

  async function loadClientes() {
    isLoading.value = true
    try {
      const res = await clientesApi.getClientes({
        search: searchQuery.value || undefined,
        filtro: activeFilter.value,
        sistema: currentSistema.value,
        page: currentPage.value,
        limit: itemsPerPage.value,
      })
      clientes.value = res.items
      totalItems.value = res.total
      totalPages.value = res.total_pages
      stats.value = res.stats
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al cargar los clientes', 'error')
    } finally {
      isLoading.value = false
    }
  }

  let searchTimeout: any = null
  function handleSearchInput() {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
      currentPage.value = 1
      loadClientes()
    }, 350)
  }

  function clearSearch() {
    searchQuery.value = ''
    currentPage.value = 1
    loadClientes()
  }

  function setFilter(filtro: string) {
    activeFilter.value = filtro
    currentPage.value = 1
    loadClientes()
  }

  function changePage(page: number) {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
      loadClientes()
    }
  }

  async function removeCliente(client: ClienteListItem): Promise<boolean> {
    const confirmDelete = window.confirm(`¿Estás seguro de que deseas dar de baja al cliente "${client.empresa}"?`)
    if (!confirmDelete) return false

    try {
      await clientesApi.deleteCliente(client.id)
      showToast(`Cliente "${client.empresa}" marcado como eliminado`)
      await loadClientes()
      return true
    } catch (err: any) {
      showToast(err.response?.data?.detail || 'Error al eliminar cliente', 'error')
      return false
    }
  }

  function formatWhatsAppLink(phone: string | null): string {
    if (!phone) return '#'
    const cleanPhone = phone.replace(/[^0-9]/g, '')
    return `https://api.whatsapp.com/send?phone=${cleanPhone}&text=Hola,%20nos%20comunicamos%20de%20NexusBot`
  }

  function formatCurrency(amount: number, currency: string = 'MXN'): string {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: currency || 'MXN',
      minimumFractionDigits: 2
    }).format(amount)
  }

  function getInitials(name: string): string {
    if (!name) return 'NB'
    const parts = name.trim().split(/\s+/)
    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase()
    }
    return name.substring(0, 2).toUpperCase()
  }

  watch(currentSistema, () => {
    currentPage.value = 1
    loadClientes()
  })

  return {
    isLoading,
    isSaving,
    clientes,
    stats,
    searchQuery,
    activeFilter,
    currentSistema,
    currentPage,
    itemsPerPage,
    totalItems,
    totalPages,
    viewMode,
    loadClientes,
    handleSearchInput,
    clearSearch,
    setFilter,
    changePage,
    removeCliente,
    formatWhatsAppLink,
    formatCurrency,
    getInitials,
  }
}
