from typing import List, Optional, Dict
from pydantic import BaseModel


class DashboardKPIs(BaseModel):
    total_pendientes_count: int = 0
    total_pendientes_monto: float = 0.0
    total_pagados_mes_count: int = 0
    total_pagados_mes_monto: float = 0.0
    total_ingresos_proyectados: float = 0.0
    tasa_cumplimiento: float = 0.0
    total_clientes: int = 0
    total_dominios_activos: int = 0
    total_hosting_activos: int = 0
    vencidos_count: int = 0
    prox_7_dias_count: int = 0


class PagoItem(BaseModel):
    id: int
    id_clie: int
    cliente_nombre: str
    cliente_correo: Optional[str] = None
    cliente_telefono: Optional[str] = None
    concepto: str
    monto: float
    currency: str = "MXN"
    tipo_servicio: int
    tipo_servicio_label: str
    nombre_servicio: Optional[str] = None
    fecha_limite: Optional[str] = None
    dias_restantes: Optional[int] = None
    estado_vencimiento: str
    manual: int = 0
    estatus: int = 0


class MonthlyTrendItem(BaseModel):
    mes: str
    pendientes: float
    pagados: float


class ServiceDistribution(BaseModel):
    dominios: float = 0.0
    hosting: float = 0.0
    servicios: float = 0.0


class DashboardResponse(BaseModel):
    status: str = "ok"
    sistema: str = "conlineweb"
    kpis: DashboardKPIs
    pagos_pendientes: List[PagoItem]
    monthly_trend: List[MonthlyTrendItem]
    distribution: ServiceDistribution
