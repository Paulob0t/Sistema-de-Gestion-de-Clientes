from pydantic import BaseModel, EmailStr
from typing import Optional, List, Dict, Any


class RecordatorioItem(BaseModel):
    id: str
    tipo_servicio: str  # 'pago', 'dominio', 'hosting'
    raw_id: int
    cliente_id: int
    cliente_nombre: str
    cliente_correo: Optional[str] = None
    cliente_telefono: Optional[str] = None
    concepto: str
    monto: float = 0.0
    currency: str = "MXN"
    fecha_vencimiento: Optional[str] = None
    dias_restantes: int = 0
    estado_vencimiento: str  # 'vencido', 'hoy', 'critico_3d', 'proximo_7d', 'proximo_15d', 'proximo_30d', 'futuro'
    estatus_pago: Optional[int] = 0
    detalles_servicio: Optional[str] = None


class RecordatoriosKpis(BaseModel):
    total_pendientes: int = 0
    vencidos: int = 0
    proximos_7_dias: int = 0
    con_correo_valido: int = 0
    monto_total_pendiente_mxn: float = 0.0
    monto_total_pendiente_usd: float = 0.0


class RecordatoriosListResponse(BaseModel):
    items: List[RecordatorioItem]
    total: int
    kpis: RecordatoriosKpis


class RecordatorioPreviewRequest(BaseModel):
    tipo: str  # 'pago', 'dominio', 'hosting', 'custom'
    id: Optional[int] = None
    cliente_id: Optional[int] = None
    custom_asunto: Optional[str] = None
    custom_cuerpo: Optional[str] = None
    custom_despedida: Optional[str] = None


class RecordatorioPreviewResponse(BaseModel):
    asunto: str
    html: str
    destinatario_correo: str
    destinatario_nombre: str


class RecordatorioSendRequest(BaseModel):
    tipo: str  # 'pago', 'dominio', 'hosting', 'custom'
    id: Optional[int] = None
    cliente_id: Optional[int] = None
    destinatario_correo: Optional[str] = None
    asunto: Optional[str] = None
    cuerpo: Optional[str] = None
    despedida: Optional[str] = None


class BatchItem(BaseModel):
    tipo: str
    id: int


class RecordatorioBatchSendRequest(BaseModel):
    items: List[BatchItem]


class RecordatorioSendResponse(BaseModel):
    success: bool
    message: str
    total: int = 1
    enviados: int = 0
    fallidos: int = 0
    detalles: Optional[List[Dict[str, Any]]] = None
