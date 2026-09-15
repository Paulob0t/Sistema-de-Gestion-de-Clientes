import apiClient from './client'

export interface AgenteSimple {
  id: number
  nombre: string
  correo?: string | null
}

export interface SolicitudNotaItem {
  id: number
  solicitud_id: number
  autor: string
  nota_texto: string
  imagenes: string[]
  fecha_creacion?: string | null
}

export interface SolicitudItem {
  id: number
  titulo: string
  descripcion_texto: string
  imagenes: string[]
  archivos: string[]
  fecha_solicitud?: string | null
  estado: 'Pendiente' | 'En Proceso' | 'Finalizado'
  usuario_asignado?: string | null
  agentes: AgenteSimple[]
  prioridad: 'Alta' | 'Media' | 'Baja'
  id_cliente?: number | null
  cliente_nombre?: string | null
  cliente_empresa?: string | null
  fecha_lim?: string | null
  fecha_termina?: string | null
  dias_restantes?: number | null
  total_notas: number
}

export interface SolicitudDetail extends SolicitudItem {
  notas: SolicitudNotaItem[]
}

export interface SolicitudCreatePayload {
  titulo: string
  descripcion?: string
  id_cliente?: number | null
  agentes_ids?: number[]
  prioridad?: string
  fecha_lim?: string | null
  repetir?: number
}

export interface SolicitudUpdatePayload {
  titulo?: string
  descripcion?: string
  id_cliente?: number | null
  agentes_ids?: number[]
  prioridad?: string
  fecha_lim?: string | null
  estado?: string
}

export interface SolicitudesKpis {
  total: number
  pendientes: number
  en_proceso: number
  finalizadas: number
  asignadas_a_mi: number
  alta_prioridad: number
}

export interface SolicitudesListResponse {
  items: SolicitudItem[]
  total: number
  page: number
  limit: number
  kpis: SolicitudesKpis
}

export const solicitudesApi = {
  list: (params?: {
    search?: string
    estado?: string
    prioridad?: string
    agente_id?: number
    solo_mias?: boolean
    cliente_id?: number
    page?: number
    limit?: number
  }) => apiClient.get<SolicitudesListResponse>('/solicitudes', { params }),

  getById: (id: number) => apiClient.get<SolicitudDetail>(`/solicitudes/${id}`),

  create: (payload: SolicitudCreatePayload) =>
    apiClient.post<SolicitudDetail>('/solicitudes', payload),

  update: (id: number, payload: SolicitudUpdatePayload) =>
    apiClient.put<SolicitudDetail>(`/solicitudes/${id}`, payload),

  updateStatus: (id: number, estado: string) =>
    apiClient.patch<SolicitudDetail>(`/solicitudes/${id}/estado`, { estado }),

  assignAgents: (id: number, agentes_ids: number[]) =>
    apiClient.patch<SolicitudDetail>(`/solicitudes/${id}/asignar`, { agentes_ids }),

  delete: (id: number) => apiClient.delete<{ status: string; message: string }>(`/solicitudes/${id}`),

  getKpis: () => apiClient.get<SolicitudesKpis>('/solicitudes/kpis'),

  getAgentes: () => apiClient.get<AgenteSimple[]>('/solicitudes/agentes'),

  addNota: (id: number, payload: { nota: string; imagenes?: string[] }) =>
    apiClient.post<SolicitudNotaItem>(`/solicitudes/${id}/notas`, payload),
}
