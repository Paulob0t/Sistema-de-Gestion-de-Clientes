from pydantic import BaseModel, Field
from typing import Optional, List, Dict, Any
from datetime import datetime


class AgenteSimple(BaseModel):
    id: int
    nombre: str
    correo: Optional[str] = None


class SolicitudNotaItem(BaseModel):
    id: int
    solicitud_id: int
    autor: str
    nota_texto: str
    imagenes: List[str] = []
    fecha_creacion: Optional[str] = None


class SolicitudNotaCreate(BaseModel):
    nota: str = Field(..., min_length=1, description="Contenido de la nota o comentario")
    imagenes: Optional[List[str]] = []


class SolicitudItem(BaseModel):
    id: int
    titulo: str
    descripcion_texto: str
    imagenes: List[str] = []
    archivos: List[str] = []
    fecha_solicitud: Optional[str] = None
    estado: str  # 'Pendiente', 'En Proceso', 'Finalizado'
    usuario_asignado: Optional[str] = None
    agentes: List[AgenteSimple] = []
    prioridad: str  # 'Alta', 'Media', 'Baja'
    id_cliente: Optional[int] = None
    cliente_nombre: Optional[str] = None
    cliente_empresa: Optional[str] = None
    fecha_lim: Optional[str] = None
    fecha_termina: Optional[str] = None
    dias_restantes: Optional[int] = None
    total_notas: int = 0


class SolicitudDetail(SolicitudItem):
    notas: List[SolicitudNotaItem] = []


class SolicitudCreate(BaseModel):
    titulo: str = Field(..., min_length=3, description="Título de la solicitud o requerimiento")
    descripcion: Optional[str] = Field("", description="Descripción detallada o texto")
    id_cliente: Optional[int] = Field(None, description="ID del cliente asociado")
    agentes_ids: Optional[List[int]] = Field(default=[], description="Lista de IDs de agentes asignados")
    prioridad: Optional[str] = Field("Media", description="Prioridad: Alta, Media, Baja")
    fecha_lim: Optional[str] = Field(None, description="Fecha límite de entrega (YYYY-MM-DD)")
    repetir: Optional[int] = Field(0, description="0=No repetir, 1=Diario, 2=Semanal, 3=Mensual")


class SolicitudUpdate(BaseModel):
    titulo: Optional[str] = None
    descripcion: Optional[str] = None
    id_cliente: Optional[int] = None
    agentes_ids: Optional[List[int]] = None
    prioridad: Optional[str] = None
    fecha_lim: Optional[str] = None
    estado: Optional[str] = None


class SolicitudStatusUpdate(BaseModel):
    estado: str = Field(..., description="Nuevo estado: 'Pendiente', 'En Proceso', 'Finalizado'")


class SolicitudAssignUpdate(BaseModel):
    agentes_ids: List[int] = Field(..., description="Lista de IDs de agentes a asignar")


class SolicitudesKpis(BaseModel):
    total: int = 0
    pendientes: int = 0
    en_proceso: int = 0
    finalizadas: int = 0
    asignadas_a_mi: int = 0
    alta_prioridad: int = 0


class SolicitudesListResponse(BaseModel):
    items: List[SolicitudItem]
    total: int
    page: int
    limit: int
    kpis: SolicitudesKpis
