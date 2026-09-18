from datetime import datetime, date
from typing import Optional, List, Any
from zoneinfo import ZoneInfo
from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from sqlalchemy import func, or_, and_, desc

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.models.cliente import Cliente
from app.models.dominio import Dominio
from app.models.hosting import Hosting
from app.models.pago import Pago
from app.models.solicitud import Solicitud
from app.models.solicitud_nota import SolicitudNota
from app.schemas.portal import (
    PortalInicioResponse,
    PortalSitioItem,
    PortalPagoItem,
    PortalTicketItem,
)

router = APIRouter()


def _format_date(val: Optional[Any], fmt: str = "%Y-%m-%d") -> Optional[str]:
    """Formatea de manera segura una fecha."""
    if not val:
        return None
    if isinstance(val, (datetime, date)):
        if getattr(val, "year", 2000) < 1900:
            return None
        return val.strftime(fmt)
    if isinstance(val, str):
        v = val.strip()
        if not v or v.startswith("0000-") or v.startswith("0001-") or v.lower() in ("none", "null"):
            return None
        if len(v) >= 10:
            return v[:10]
        return v
    return None


def _get_saludo() -> str:
    """Calcula el saludo según la hora actual en la zona de México."""
    try:
        tz = ZoneInfo("America/Mexico_City")
        now = datetime.now(tz)
    except Exception:
        now = datetime.now()
    hora = now.hour
    if 5 <= hora < 12:
        return "Buenos días"
    elif 12 <= hora < 19:
        return "Buenas tardes"
    else:
        return "Buenas noches"


@router.get("/inicio", response_model=PortalInicioResponse, summary="Obtener datos del inicio del portal de clientes")
def get_portal_inicio(
    cliente_id: Optional[int] = Query(None, description="ID del cliente a previsualizar (solo administradores)"),
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Retorna toda la información requerida para la vista de Inicio del Portal de Clientes:
    - Saludo dinámico y datos de contacto/empresa
    - KPIs clave (Sitios web, Pagos pendientes, Tickets abiertos, Hosting y Dominios)
    - Alerta de pagos prioritarios
    - Accesos rápidos y actividad reciente (sitios, pagos, tickets)
    """
    # Determinar ID del cliente objetivo
    if current_user.id_tipo_usuario == 0:
        uid = current_user.id
    elif current_user.id_tipo_usuario in (1, 2, 3, 4, 5) and cliente_id:
        uid = cliente_id
    else:
        # Si un admin entra sin especificar cliente, buscamos el primer cliente activo como muestra
        first_cli = db.query(Cliente).filter(Cliente.eliminado == 0).order_by(Cliente.id.asc()).first()
        uid = first_cli.id if first_cli else current_user.id

    cliente = db.query(Cliente).filter(Cliente.id == uid).first()
    cliente_nombre = cliente.nombre_contacto if (cliente and cliente.nombre_contacto) else current_user.nombre or "Cliente"
    cliente_empresa = cliente.empresa if cliente else None
    cliente_correo = cliente.correo if cliente else current_user.correo

    saludo = _get_saludo()

    # Total sitios web activos con URL
    total_sitios = (
        db.query(func.count(Dominio.id_dominio))
        .filter(
            Dominio.cliente_id == uid,
            Dominio.eliminado == 0,
            Dominio.url_dominio.isnot(None),
            Dominio.url_dominio != "",
        )
        .scalar()
        or 0
    )

    # Pagos pendientes
    pagos_pendientes_query = (
        db.query(Pago)
        .filter(
            Pago.id_clie == uid,
            Pago.estatus != 1,
            Pago.Registro == 0,
        )
    )
    total_pendientes = pagos_pendientes_query.count()
    monto_total_pendiente = sum(float(p.monto) for p in pagos_pendientes_query.all())

    # Tickets de soporte abiertos (no finalizados)
    total_tickets_abiertos = (
        db.query(func.count(Solicitud.id))
        .filter(
            Solicitud.id_cliente == uid,
            or_(Solicitud.estado.is_(None), Solicitud.estado != "Finalizado"),
        )
        .scalar()
        or 0
    )

    # Servicios de hosting y dominios totales
    total_hostings = (
        db.query(func.count(Hosting.id_orden))
        .filter(Hosting.cliente_id == uid, Hosting.eliminado == 0)
        .scalar()
        or 0
    )
    total_dominios = (
        db.query(func.count(Dominio.id_dominio))
        .filter(Dominio.cliente_id == uid, Dominio.eliminado == 0)
        .scalar()
        or 0
    )

    # Sitios recientes
    sitios_db = (
        db.query(Dominio)
        .filter(
            Dominio.cliente_id == uid,
            Dominio.eliminado == 0,
            Dominio.url_dominio.isnot(None),
            Dominio.url_dominio != "",
        )
        .order_by(Dominio.id_dominio.desc())
        .limit(6)
        .all()
    )
    sitios_recientes = [
        PortalSitioItem(
            id=s.id_dominio,
            url_dominio=s.url_dominio.strip(),
            proveedor=s.proveedor,
            fecha_pago=_format_date(s.fecha_pago),
            estatus_pago=int(s.estatus_pago or 0),
            url_cpanel=s.url_cpanel,
        )
        for s in sitios_db
    ]

    # Pagos pendientes recientes
    pagos_recientes_db = (
        pagos_pendientes_query
        .order_by(Pago.fecha.desc())
        .limit(5)
        .all()
    )
    pagos_pendientes_recientes = [
        PortalPagoItem(
            id=p.id,
            concepto=p.concepto,
            monto=float(p.monto),
            currency=p.currency or "MXN",
            fecha=_format_date(p.fecha),
            fecha_limite_pago=_format_date(p.fecha_limite_pago),
            estatus=int(p.estatus or 0),
        )
        for p in pagos_recientes_db
    ]

    # Tickets recientes
    tickets_db = (
        db.query(Solicitud)
        .filter(Solicitud.id_cliente == uid)
        .order_by(Solicitud.id.desc())
        .limit(5)
        .all()
    )
    tickets_recientes = []
    for t in tickets_db:
        n_count = db.query(func.count(SolicitudNota.id)).filter(SolicitudNota.solicitud_id == t.id).scalar() or 0
        tickets_recientes.append(
            PortalTicketItem(
                id=t.id,
                titulo=t.titulo,
                estado=t.estado or "Pendiente",
                prioridad=t.prioridad or "Media",
                fecha_solicitud=_format_date(t.fecha_solicitud),
                total_notas=int(n_count),
            )
        )

    return PortalInicioResponse(
        cliente_id=uid,
        cliente_nombre=cliente_nombre,
        cliente_empresa=cliente_empresa,
        cliente_correo=cliente_correo,
        saludo=saludo,
        total_sitios=total_sitios,
        total_pendientes=total_pendientes,
        monto_total_pendiente=round(monto_total_pendiente, 2),
        total_tickets_abiertos=total_tickets_abiertos,
        total_hostings=total_hostings,
        total_dominios=total_dominios,
        doc_url="https://conlineweb.com",
        sitios_recientes=sitios_recientes,
        pagos_pendientes_recientes=pagos_pendientes_recientes,
        tickets_recientes=tickets_recientes,
    )
