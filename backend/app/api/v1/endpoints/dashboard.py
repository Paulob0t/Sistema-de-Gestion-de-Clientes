from datetime import datetime, date, timedelta
from typing import Optional, List
from fastapi import APIRouter, Depends, Query
from sqlalchemy.orm import Session
from sqlalchemy import func, case, or_, and_

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.schemas.dashboard import (
    DashboardResponse,
    DashboardKPIs,
    PagoItem,
    MonthlyTrendItem,
    ServiceDistribution,
)
from app.models.pago import Pago
from app.models.cliente import Cliente
from app.models.dominio import Dominio
from app.models.hosting import Hosting

router = APIRouter()


def calcular_estado_vencimiento(fecha_limite: Optional[date], hoy: date):
    if not fecha_limite or str(fecha_limite).startswith("0000-00-00"):
        return {"estado": "sin_fecha", "dias": None, "str": None}
    
    dias = (fecha_limite - hoy).days
    fecha_str = fecha_limite.strftime("%Y-%m-%d")

    if dias < 0:
        return {"estado": "vencido", "dias": dias, "str": fecha_str}
    elif dias <= 7:
        return {"estado": "prox7", "dias": dias, "str": fecha_str}
    elif dias <= 30:
        return {"estado": "prox30", "dias": dias, "str": fecha_str}
    else:
        return {"estado": "ok", "dias": dias, "str": fecha_str}


@router.get("/stats", response_model=DashboardResponse, summary="Estadísticas y datos del Dashboard")
def get_dashboard_stats(
    sistema: str = Query("conlineweb", description="Sistema: conlineweb o hostingpro"),
    mes: Optional[int] = Query(None, description="Mes para KPIs (1-12)"),
    anio: Optional[int] = Query(None, description="Año para KPIs"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db)
):
    hoy = date.today()
    mes_kpi = mes or hoy.month
    anio_kpi = anio or hoy.year

    fecha_inicio_mes = date(anio_kpi, mes_kpi, 1)
    if mes_kpi == 12:
        fecha_fin_mes = date(anio_kpi + 1, 1, 1) - timedelta(days=1)
    else:
        fecha_fin_mes = date(anio_kpi, mes_kpi + 1, 1) - timedelta(days=1)

    is_client = current_user.id_tipo_usuario == 0
    client_id = current_user.id if is_client else None

    # Consulta base de pagos pendientes (estatus = 0)
    query_pend = (
        db.query(
            Pago,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.correo.label("cliente_correo"),
            Cliente.telefono.label("cliente_telefono"),
            Dominio.url_dominio.label("url_dominio"),
            Dominio.fecha_pago.label("fecha_pago_dominio"),
            Hosting.nom_host.label("nom_host"),
            Hosting.fecha_pago.label("fecha_pago_hosting"),
        )
        .outerjoin(Cliente, Pago.id_clie == Cliente.id)
        .outerjoin(Dominio, and_(Pago.tipo_servicio == 2, Pago.id_servicio == Dominio.id_dominio))
        .outerjoin(Hosting, and_(Pago.tipo_servicio == 1, Pago.id_servicio == Hosting.id_orden))
        .filter(Pago.estatus == 0, Pago.Registro == 0)
    )

    if not is_client:
        query_pend = query_pend.filter(
            or_(Pago.sistema == sistema, Pago.sistema == None, Pago.sistema == "")
        )
    else:
        query_pend = query_pend.filter(Pago.id_clie == client_id)

    raw_pendientes = query_pend.order_by(Pago.id.desc()).all()

    # Procesar pagos pendientes y calcular fechas efectivas
    pagos_items: List[PagoItem] = []
    distribucion_montos = {"dominios": 0.0, "hosting": 0.0, "servicios": 0.0}
    total_pend_monto = 0.0
    vencidos_count = 0
    prox7_count = 0

    for row in raw_pendientes:
        pago = row[0]
        cliente_nombre = row.cliente_nombre or f"Cliente #{pago.id_clie}"
        cliente_correo = row.cliente_correo
        cliente_telefono = row.cliente_telefono
        url_dominio = row.url_dominio
        fecha_pago_dom = row.fecha_pago_dominio
        nom_host = row.nom_host
        fecha_pago_host = row.fecha_pago_hosting

        # Determinar nombre del servicio
        if pago.tipo_servicio == 2:
            nombre_servicio = url_dominio or pago.concepto
            tipo_label = "Dominio"
            fecha_efectiva = pago.fecha_limite_pago or fecha_pago_dom
            distribucion_montos["dominios"] += float(pago.monto)
        elif pago.tipo_servicio == 1:
            nombre_servicio = nom_host or pago.concepto
            tipo_label = "Hosting"
            fecha_efectiva = pago.fecha_limite_pago or fecha_pago_host
            distribucion_montos["hosting"] += float(pago.monto)
        else:
            nombre_servicio = pago.concepto
            tipo_label = "Servicio Manual" if pago.manual == 1 else "Otro Servicio"
            fecha_efectiva = pago.fecha_limite_pago
            distribucion_montos["servicios"] += float(pago.monto)

        venc_info = calcular_estado_vencimiento(fecha_efectiva, hoy)

        if venc_info["estado"] == "vencido":
            vencidos_count += 1
        elif venc_info["estado"] == "prox7":
            prox7_count += 1

        monto_float = float(pago.monto)
        total_pend_monto += monto_float

        pagos_items.append(
            PagoItem(
                id=pago.id,
                id_clie=pago.id_clie,
                cliente_nombre=cliente_nombre,
                cliente_correo=cliente_correo,
                cliente_telefono=cliente_telefono,
                concepto=pago.concepto or nombre_servicio,
                monto=monto_float,
                currency=pago.currency or "MXN",
                tipo_servicio=pago.tipo_servicio,
                tipo_servicio_label=tipo_label,
                nombre_servicio=nombre_servicio,
                fecha_limite=venc_info["str"],
                dias_restantes=venc_info["dias"],
                estado_vencimiento=venc_info["estado"],
                manual=pago.manual or 0,
                estatus=pago.estatus
            )
        )

    # Ordenar por fecha límite (vencidos primero, luego próximos)
    pagos_items.sort(key=lambda x: (x.dias_restantes is None, x.dias_restantes if x.dias_restantes is not None else 9999))

    # Pagos cobrados en el mes
    query_pag_mes = db.query(func.count(Pago.id), func.sum(Pago.monto)).filter(
        Pago.estatus == 1,
        Pago.Registro == 0,
        Pago.fecha_pago >= fecha_inicio_mes,
        Pago.fecha_pago <= fecha_fin_mes,
    )
    if not is_client:
        query_pag_mes = query_pag_mes.filter(
            or_(Pago.sistema == sistema, Pago.sistema == None, Pago.sistema == "")
        )
    else:
        query_pag_mes = query_pag_mes.filter(Pago.id_clie == client_id)

    pag_mes_res = query_pag_mes.first()
    total_pag_mes_count = pag_mes_res[0] or 0
    total_pag_mes_monto = float(pag_mes_res[1] or 0.0)

    # Totales globales de clientes, dominios y hosting
    total_clientes = db.query(func.count(Cliente.id)).scalar() or 0
    total_dominios = db.query(func.count(Dominio.id_dominio)).filter(Dominio.eliminado == 0).scalar() or 0
    total_hosting = db.query(func.count(Hosting.id_orden)).filter(Hosting.eliminado == 0).scalar() or 0

    total_proyectado = total_pend_monto + total_pag_mes_monto
    tasa_cumplimiento = (
        round((total_pag_mes_monto / total_proyectado) * 100, 1)
        if total_proyectado > 0
        else 0.0
    )

    kpis = DashboardKPIs(
        total_pendientes_count=len(pagos_items),
        total_pendientes_monto=round(total_pend_monto, 2),
        total_pagados_mes_count=total_pag_mes_count,
        total_pagados_mes_monto=round(total_pag_mes_monto, 2),
        total_ingresos_proyectados=round(total_proyectado, 2),
        tasa_cumplimiento=tasa_cumplimiento,
        total_clientes=total_clientes,
        total_dominios_activos=total_dominios,
        total_hosting_activos=total_hosting,
        vencidos_count=vencidos_count,
        prox_7_dias_count=prox7_count
    )

    # Tendencia mensual de los últimos 6 meses
    monthly_trend: List[MonthlyTrendItem] = []
    meses_es = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"]

    for i in range(5, -1, -1):
        m_offset = (hoy.month - 1 - i) % 12 + 1
        y_offset = hoy.year + ((hoy.month - 1 - i) // 12)
        f_ini = date(y_offset, m_offset, 1)
        if m_offset == 12:
            f_fin = date(y_offset + 1, 1, 1) - timedelta(days=1)
        else:
            f_fin = date(y_offset, m_offset + 1, 1) - timedelta(days=1)

        # Cobrados en ese mes
        q_pag = db.query(func.sum(Pago.monto)).filter(
            Pago.estatus == 1,
            Pago.Registro == 0,
            Pago.fecha_pago >= f_ini,
            Pago.fecha_pago <= f_fin
        )
        if not is_client:
            q_pag = q_pag.filter(or_(Pago.sistema == sistema, Pago.sistema == None, Pago.sistema == ""))
        else:
            q_pag = q_pag.filter(Pago.id_clie == client_id)
        
        pag_val = float(q_pag.scalar() or 0.0)

        # Pendientes generados en ese mes
        q_pnd = db.query(func.sum(Pago.monto)).filter(
            Pago.estatus == 0,
            Pago.Registro == 0,
            Pago.fecha >= f_ini,
            Pago.fecha <= f_fin
        )
        if not is_client:
            q_pnd = q_pnd.filter(or_(Pago.sistema == sistema, Pago.sistema == None, Pago.sistema == ""))
        else:
            q_pnd = q_pnd.filter(Pago.id_clie == client_id)

        pnd_val = float(q_pnd.scalar() or 0.0)

        monthly_trend.append(
            MonthlyTrendItem(
                mes=f"{meses_es[m_offset - 1]} {y_offset}",
                pendientes=round(pnd_val, 2),
                pagados=round(pag_val, 2)
            )
        )

    distribution = ServiceDistribution(
        dominios=round(distribucion_montos["dominios"], 2),
        hosting=round(distribucion_montos["hosting"], 2),
        servicios=round(distribucion_montos["servicios"], 2)
    )

    return DashboardResponse(
        status="ok",
        sistema=sistema,
        kpis=kpis,
        pagos_pendientes=pagos_items,
        monthly_trend=monthly_trend,
        distribution=distribution
    )
