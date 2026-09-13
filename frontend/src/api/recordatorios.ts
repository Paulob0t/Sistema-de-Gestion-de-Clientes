import apiClient from './client'

export interface RecordatorioItem {
  id: string
  tipo_servicio: 'pago' | 'dominio' | 'hosting'
  raw_id: number
  cliente_id: number
  cliente_nombre: string
  cliente_correo?: string | null
  cliente_telefono?: string | null
  concepto: string
  monto: number
  currency: string
  fecha_vencimiento?: string | null
  dias_restantes: number
  estado_vencimiento: 'vencido' | 'hoy' | 'critico_3d' | 'proximo_7d' | 'proximo_15d' | 'proximo_30d' | 'futuro'
  estatus_pago?: number
  detalles_servicio?: string | null
}

export interface RecordatoriosKpis {
  total_pendientes: number
  vencidos: number
  proximos_7_dias: number
  con_correo_valido: number
  monto_total_pendiente_mxn: number
  monto_total_pendiente_usd: number
}

export interface RecordatoriosListResponse {
  items: RecordatorioItem[]
  total: number
  kpis: RecordatoriosKpis
}

export interface RecordatorioPreviewRequest {
  tipo: string
  id?: number
  cliente_id?: number
  custom_asunto?: string
  custom_cuerpo?: string
  custom_despedida?: string
}

export interface RecordatorioPreviewResponse {
  asunto: string
  html: string
  destinatario_correo: string
  destinatario_nombre: string
}

export interface RecordatorioSendRequest {
  tipo: string
  id?: number
  cliente_id?: number
  destinatario_correo?: string
  asunto?: string
  cuerpo?: string
  despedida?: string
}

export interface BatchItem {
  tipo: string
  id: number
}

export interface RecordatorioSendResponse {
  success: boolean
  message: string
  total: number
  enviados: number
  fallidos: number
  detalles?: Array<{
    id?: number
    tipo?: string
    email?: string
    status: string
    reason?: string
  }>
}

export interface SmtpTestResponse {
  success: boolean
  message: string
  host?: string
  port?: number
  user?: string
}

export const recordatoriosApi = {
  getPendientes: (params?: { search?: string; tipo?: string; estado?: string }) =>
    apiClient.get<RecordatoriosListResponse>('/recordatorios/pendientes', { params }),

  getPreview: (payload: RecordatorioPreviewRequest) =>
    apiClient.post<RecordatorioPreviewResponse>('/recordatorios/preview', payload),

  enviarIndividual: (payload: RecordatorioSendRequest) =>
    apiClient.post<RecordatorioSendResponse>('/recordatorios/enviar-individual', payload),

  enviarMasivo: (items: BatchItem[]) =>
    apiClient.post<RecordatorioSendResponse>('/recordatorios/enviar-masivo', { items }),

  testSmtp: () =>
    apiClient.post<SmtpTestResponse>('/recordatorios/test-smtp'),
}
