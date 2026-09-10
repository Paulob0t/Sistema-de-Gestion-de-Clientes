from datetime import datetime, date, timedelta
from typing import Optional, List
from fastapi import APIRouter, Depends, Query, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import func, or_, and_, desc, asc, cast, String

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.schemas.hosting import (
    HostingsListResponse,
    HostingListItem,
    HostingStats,
    HostingDetail,
    HostingCreate,
    HostingUpdate,
)
from app.models.hosting import Hosting
from app.models.cliente import Cliente

router = APIRouter()


def calcular_vencimiento_hosting(fecha_pago_val, hoy: date):
    if not fecha_pago_val or str(fecha_pago_val).startswith("0000-00-00"):
        return {"estado": "sin_fecha", "dias": None, "str": None}
    
    try:
        if isinstance(fecha_pago_val, (date, datetime)):
            fp = fecha_pago_val if isinstance(fecha_pago_val, date) else fecha_pago_val.date()
        else:
            fp = datetime.strptime(str(fecha_pago_val), "%Y-%m-%d").date()
        
        dias = (fp - hoy).days
        fp_str = fp.strftime("%Y-%m-%d")

        if dias < 0:
            return {"estado": "vencido", "dias": dias, "str": fp_str}
        elif dias <= 7:
            return {"estado": "prox7", "dias": dias, "str": fp_str}
        elif dias <= 15:
            return {"estado": "prox15", "dias": dias, "str": fp_str}
        elif dias <= 30:
            return {"estado": "prox30", "dias": dias, "str": fp_str}
        else:
            return {"estado": "ok", "dias": dias, "str": fp_str}
    except Exception:
        return {"estado": "sin_fecha", "dias": None, "str": str(fecha_pago_val)}


def detect_panel_type(nom_host: str, url_acceso: str) -> str:
    combined = f"{nom_host or ''} {url_acceso or ''}".lower()
    if ":2086" in combined or "whm" in combined:
        return "whm"
    return "cpanel"


def get_frecuencia_label(frecuencia: Optional[int]) -> str:
    mapping = {
        1: "Mensual",
        2: "Anual",
        3: "Trimestral",
        4: "Semestral",
    }
    return mapping.get(frecuencia or 2, "Anual")


@router.get("", response_model=HostingsListResponse, summary="Listar hostings con filtros y estadísticas")
def list_hostings(
    search: Optional[str] = Query(None, description="Búsqueda por host, dominio, cliente o usuario"),
    filtro: str = Query("activos", description="Filtro: todos, activos, inactivos, por_vencer_30, por_vencer_15, por_vencer_7, vencidos, eliminados"),
    page: int = Query(1, ge=1, description="Número de página"),
    limit: int = Query(20, ge=1, le=100, description="Registros por página"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    hoy = date.today()
    limite_7_dias = hoy + timedelta(days=7)
    limite_15_dias = hoy + timedelta(days=15)
    limite_30_dias = hoy + timedelta(days=30)

    # Base query con Join a Clientes
    base_query = (
        db.query(
            Hosting,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.empresa.label("cliente_empresa"),
            Cliente.correo.label("cliente_correo"),
            Cliente.telefono.label("cliente_telefono"),
        )
        .outerjoin(Cliente, Hosting.cliente_id == Cliente.id)
    )

    # Si es cliente normal, aislar solo sus hostings
    if current_user.id_tipo_usuario == 0:
        base_query = base_query.filter(Hosting.cliente_id == current_user.id)

    # Búsqueda por término
    if search and search.strip():
        term = f"%{search.strip()}%"
        base_query = base_query.filter(
            or_(
                Hosting.nom_host.ilike(term),
                Hosting.dominio.ilike(term),
                Hosting.usuario.ilike(term),
                Cliente.empresa.ilike(term),
                Cliente.nombre_contacto.ilike(term),
                Cliente.correo.ilike(term),
                cast(Hosting.id_orden, String).ilike(term),
            )
        )

    # Filtros
    if filtro == "activos":
        base_query = base_query.filter(
            Hosting.eliminado == 0,
            Hosting.estado_producto == 1
        )
    elif filtro == "inactivos":
        base_query = base_query.filter(
            Hosting.eliminado == 0,
            Hosting.estado_producto == 0
        )
    elif filtro == "por_vencer_30":
        base_query = base_query.filter(
            Hosting.eliminado == 0,
            Hosting.fecha_pago >= hoy,
            Hosting.fecha_pago <= limite_30_dias
        )
    elif filtro == "por_vencer_15":
        base_query = base_query.filter(
            Hosting.eliminado == 0,
            Hosting.fecha_pago >= hoy,
            Hosting.fecha_pago <= limite_15_dias
        )
    elif filtro == "por_vencer_7":
        base_query = base_query.filter(
            Hosting.eliminado == 0,
            Hosting.fecha_pago >= hoy,
            Hosting.fecha_pago <= limite_7_dias
        )
    elif filtro == "vencidos":
        base_query = base_query.filter(
            Hosting.eliminado == 0,
            Hosting.fecha_pago < hoy,
            Hosting.fecha_pago.isnot(None)
        )
    elif filtro == "eliminados":
        base_query = base_query.filter(Hosting.eliminado == 1)

    total_records = base_query.count()

    # Ordenamiento por fecha de vencimiento más próxima primero
    base_query = base_query.order_by(
        desc(Hosting.fecha_pago >= hoy),
        asc(Hosting.fecha_pago),
        desc(Hosting.id_orden)
    )

    offset = (page - 1) * limit
    results = base_query.offset(offset).limit(limit).all()

    items: List[HostingListItem] = []
    for host, cli_nom, cli_emp, cli_mail, cli_tel in results:
        venc = calcular_vencimiento_hosting(host.fecha_pago, hoy)
        items.append(
            HostingListItem(
                id_orden=host.id_orden,
                cliente_id=host.cliente_id,
                cliente_nombre=cli_nom or "Cliente Desconocido",
                cliente_empresa=cli_emp or "Sin Empresa",
                cliente_correo=cli_mail,
                cliente_telefono=cli_tel,
                nom_host=host.nom_host or "",
                dominio=host.dominio or "",
                usuario=host.usuario or "",
                tipo_producto=host.tipo_producto or "Servicio de alojamiento",
                producto=host.producto or 1,
                costo_producto=float(host.costo_producto or 0.0),
                id_forma_pago=host.id_forma_pago or 1,
                moneda="USD" if host.id_forma_pago == 2 else "MXN",
                url_acceso=host.url_acceso or "",
                panel_type=detect_panel_type(host.nom_host, host.url_acceso),
                fecha_contratacion=str(host.fecha_contratacion) if host.fecha_contratacion else None,
                fecha_pago=venc["str"],
                dias_restantes=venc["dias"],
                estado_vencimiento=venc["estado"],
                estado_producto=host.estado_producto if host.estado_producto is not None else 1,
                frecuencia_pago=host.frecuencia_pago or 2,
                frecuencia_label=get_frecuencia_label(host.frecuencia_pago),
                eliminado=host.eliminado,
            )
        )

    # Cálculo de métricas KPIs globales
    stats_query = db.query(Hosting)
    if current_user.id_tipo_usuario == 0:
        stats_query = stats_query.filter(Hosting.cliente_id == current_user.id)

    all_hosts = stats_query.all()
    stats = HostingStats()
    stats.total = len(all_hosts)

    for h in all_hosts:
        if h.eliminado == 1:
            stats.eliminados += 1
            continue
        
        if h.estado_producto == 1:
            stats.activos += 1
        else:
            stats.inactivos += 1

        if h.fecha_pago and not str(h.fecha_pago).startswith("0000-00-00"):
            fp = h.fecha_pago if isinstance(h.fecha_pago, date) else datetime.strptime(str(h.fecha_pago), "%Y-%m-%d").date()
            dias = (fp - hoy).days
            if dias < 0:
                stats.vencidos += 1
            else:
                if dias <= 30:
                    stats.por_vencer_30d += 1
                if dias <= 15:
                    stats.por_vencer_15d += 1
                if dias <= 7:
                    stats.por_vencer_7d += 1

    total_pages = (total_records + limit - 1) // limit if total_records > 0 else 1

    return HostingsListResponse(
        items=items,
        total=total_records,
        page=page,
        total_pages=total_pages,
        stats=stats
    )


@router.get("/{id_orden}", response_model=HostingDetail, summary="Detalle completo de un servicio de hosting")
def get_hosting_detail(
    id_orden: int,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    result = (
        db.query(
            Hosting,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.empresa.label("cliente_empresa"),
            Cliente.correo.label("cliente_correo"),
            Cliente.telefono.label("cliente_telefono"),
        )
        .outerjoin(Cliente, Hosting.cliente_id == Cliente.id)
        .filter(Hosting.id_orden == id_orden)
        .first()
    )

    if not result:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Servicio de hosting #{id_orden} no encontrado",
        )

    host, cli_nom, cli_emp, cli_mail, cli_tel = result

    # Restricción cliente
    if current_user.id_tipo_usuario == 0 and host.cliente_id != current_user.id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="No tienes permiso para consultar este servicio de hosting",
        )

    hoy = date.today()
    venc = calcular_vencimiento_hosting(host.fecha_pago, hoy)

    # Solo mostrar contraseñas a SuperAdmins o administradores
    pass_clear = host.contrasena_normal if current_user.id_tipo_usuario >= 1 else None

    return HostingDetail(
        id_orden=host.id_orden,
        cliente_id=host.cliente_id,
        cliente_nombre=cli_nom or "Cliente Desconocido",
        cliente_empresa=cli_emp or "Sin Empresa",
        cliente_correo=cli_mail,
        cliente_telefono=cli_tel,
        nom_host=host.nom_host or "",
        dominio=host.dominio or "",
        usuario=host.usuario or "",
        contrasena_normal=pass_clear,
        tipo_producto=host.tipo_producto or "Servicio de alojamiento",
        producto=host.producto or 1,
        costo_producto=float(host.costo_producto or 0.0),
        id_forma_pago=host.id_forma_pago or 1,
        moneda="USD" if host.id_forma_pago == 2 else "MXN",
        dns=host.dns or "",
        url_pago=host.url_pago or "",
        url_acceso=host.url_acceso or "",
        panel_type=detect_panel_type(host.nom_host, host.url_acceso),
        ns1=host.ns1 or "",
        ns2=host.ns2 or "",
        ns3=host.ns3 or "",
        ns4=host.ns4 or "",
        ns5=host.ns5 or "",
        ns6=host.ns6 or "",
        fecha_contratacion=str(host.fecha_contratacion) if host.fecha_contratacion else None,
        fecha_pago=venc["str"],
        dias_restantes=venc["dias"],
        estado_vencimiento=venc["estado"],
        estado_producto=host.estado_producto if host.estado_producto is not None else 1,
        IVA=host.IVA if host.IVA is not None else 1,
        frecuencia_pago=host.frecuencia_pago or 2,
        frecuencia_label=get_frecuencia_label(host.frecuencia_pago),
        eliminado=host.eliminado,
    )


@router.post("", response_model=HostingDetail, status_code=status.HTTP_201_CREATED, summary="Registrar nuevo servicio de hosting")
def create_hosting(
    payload: HostingCreate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden crear servicios de hosting",
        )

    # Validar cliente existente
    cliente = db.query(Cliente).filter(Cliente.id == payload.cliente_id).first()
    if not cliente:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Cliente #{payload.cliente_id} no existe",
        )

    # Parse fechas
    f_contratacion = None
    if payload.fecha_contratacion:
        try:
            f_contratacion = datetime.strptime(payload.fecha_contratacion, "%Y-%m-%d").date()
        except ValueError:
            pass

    f_pago = None
    if payload.fecha_pago:
        try:
            f_pago = datetime.strptime(payload.fecha_pago, "%Y-%m-%d").date()
        except ValueError:
            pass

    nuevo_host = Hosting(
        cliente_id=payload.cliente_id,
        nom_host=payload.nom_host.strip(),
        dominio=payload.dominio.strip() if payload.dominio else "",
        usuario=payload.usuario.strip() if payload.usuario else "",
        contrasena_normal=payload.contrasena_normal or "",
        contrasena=payload.contrasena_normal or "",
        tipo_producto=payload.tipo_producto or "Servicio de alojamiento",
        producto=payload.producto or 1,
        costo_producto=payload.costo_producto or 0.0,
        id_forma_pago=payload.id_forma_pago or 1,
        dns=payload.dns or "",
        url_pago=payload.url_pago or "",
        url_acceso=payload.url_acceso or "",
        ns1=payload.ns1 or "",
        ns2=payload.ns2 or "",
        ns3=payload.ns3 or "",
        ns4=payload.ns4 or "",
        ns5=payload.ns5 or "",
        ns6=payload.ns6 or "",
        fecha_contratacion=f_contratacion,
        fecha_pago=f_pago,
        estado_producto=payload.estado_producto if payload.estado_producto is not None else 1,
        IVA=payload.IVA if payload.IVA is not None else 1,
        frecuencia_pago=payload.frecuencia_pago or 2,
        eliminado=0,
    )

    db.add(nuevo_host)
    db.commit()
    db.refresh(nuevo_host)

    return get_hosting_detail(nuevo_host.id_orden, current_user, db)


@router.put("/{id_orden}", response_model=HostingDetail, summary="Actualizar servicio de hosting existente")
def update_hosting(
    id_orden: int,
    payload: HostingUpdate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden editar servicios de hosting",
        )

    host = db.query(Hosting).filter(Hosting.id_orden == id_orden).first()
    if not host:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Hosting #{id_orden} no encontrado",
        )

    if payload.cliente_id is not None:
        cliente = db.query(Cliente).filter(Cliente.id == payload.cliente_id).first()
        if not cliente:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Cliente #{payload.cliente_id} no existe",
            )
        host.cliente_id = payload.cliente_id

    if payload.nom_host is not None:
        host.nom_host = payload.nom_host.strip()
    if payload.dominio is not None:
        host.dominio = payload.dominio.strip()
    if payload.usuario is not None:
        host.usuario = payload.usuario.strip()
    if payload.contrasena_normal is not None:
        host.contrasena_normal = payload.contrasena_normal
        host.contrasena = payload.contrasena_normal
    if payload.tipo_producto is not None:
        host.tipo_producto = payload.tipo_producto
    if payload.producto is not None:
        host.producto = payload.producto
    if payload.costo_producto is not None:
        host.costo_producto = payload.costo_producto
    if payload.id_forma_pago is not None:
        host.id_forma_pago = payload.id_forma_pago
    if payload.dns is not None:
        host.dns = payload.dns
    if payload.url_pago is not None:
        host.url_pago = payload.url_pago
    if payload.url_acceso is not None:
        host.url_acceso = payload.url_acceso
    if payload.ns1 is not None:
        host.ns1 = payload.ns1
    if payload.ns2 is not None:
        host.ns2 = payload.ns2
    if payload.ns3 is not None:
        host.ns3 = payload.ns3
    if payload.ns4 is not None:
        host.ns4 = payload.ns4
    if payload.ns5 is not None:
        host.ns5 = payload.ns5
    if payload.ns6 is not None:
        host.ns6 = payload.ns6
    if payload.estado_producto is not None:
        host.estado_producto = payload.estado_producto
    if payload.IVA is not None:
        host.IVA = payload.IVA
    if payload.frecuencia_pago is not None:
        host.frecuencia_pago = payload.frecuencia_pago
    if payload.eliminado is not None:
        host.eliminado = payload.eliminado

    if payload.fecha_contratacion is not None:
        if payload.fecha_contratacion == "":
            host.fecha_contratacion = None
        else:
            try:
                host.fecha_contratacion = datetime.strptime(payload.fecha_contratacion, "%Y-%m-%d").date()
            except ValueError:
                pass

    if payload.fecha_pago is not None:
        if payload.fecha_pago == "":
            host.fecha_pago = None
        else:
            try:
                host.fecha_pago = datetime.strptime(payload.fecha_pago, "%Y-%m-%d").date()
            except ValueError:
                pass

    db.commit()
    db.refresh(host)

    return get_hosting_detail(host.id_orden, current_user, db)


@router.delete("/{id_orden}", summary="Eliminar o restaurar servicio de hosting")
def delete_hosting(
    id_orden: int,
    permanent: bool = Query(False, description="Eliminar permanentemente de la BD"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden eliminar servicios de hosting",
        )

    host = db.query(Hosting).filter(Hosting.id_orden == id_orden).first()
    if not host:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Hosting #{id_orden} no encontrado",
        )

    if permanent:
        db.delete(host)
        db.commit()
        return {"status": "success", "message": f"Hosting #{id_orden} eliminado permanentemente"}
    else:
        # Toggle eliminado
        nuevo_estado = 0 if host.eliminado == 1 else 1
        host.eliminado = nuevo_estado
        db.commit()
        accion = "restaurado" if nuevo_estado == 0 else "enviado a papelera"
        return {"status": "success", "message": f"Hosting #{id_orden} {accion}", "eliminado": nuevo_estado}
