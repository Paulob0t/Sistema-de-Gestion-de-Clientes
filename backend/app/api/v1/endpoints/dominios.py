from datetime import datetime, date, timedelta
from typing import Optional, List
from fastapi import APIRouter, Depends, Query, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import func, or_, and_, desc, asc, cast, String

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.schemas.dominio import (
    DominiosListResponse,
    DominioListItem,
    DominioStats,
    DominioDetail,
    DominioCreate,
    DominioUpdate,
)
from app.models.dominio import Dominio
from app.models.cliente import Cliente

router = APIRouter()


def calcular_vencimiento_dominio(fecha_pago_val, hoy: date):
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
        elif dias <= 30:
            return {"estado": "prox30", "dias": dias, "str": fp_str}
        else:
            return {"estado": "ok", "dias": dias, "str": fp_str}
    except Exception:
        return {"estado": "sin_fecha", "dias": None, "str": str(fecha_pago_val)}


@router.get("", response_model=DominiosListResponse, summary="Listar y consultar dominios con métricas")
def list_dominios(
    search: Optional[str] = Query(None, description="Búsqueda por dominio, empresa, contacto o proveedor"),
    filtro: str = Query("activos", description="Filtro: todos, activos, por_vencer, vencidos, pendientes_pago, pagados, externos, eliminados"),
    sistema: str = Query("conlineweb", description="Sistema: conlineweb o hostingpro"),
    page: int = Query(1, ge=1, description="Número de página"),
    limit: int = Query(20, ge=1, le=100, description="Registros por página"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    hoy = date.today()
    limite_30_dias = hoy + timedelta(days=30)

    # Base query con Join a Clientes
    base_query = (
        db.query(
            Dominio,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.empresa.label("cliente_empresa"),
            Cliente.correo.label("cliente_correo"),
            Cliente.telefono.label("cliente_telefono"),
        )
        .outerjoin(Cliente, Dominio.cliente_id == Cliente.id)
    )

    # Si es cliente, solo sus dominios
    if current_user.id_tipo_usuario == 0:
        base_query = base_query.filter(Dominio.cliente_id == current_user.id)

    # Búsqueda
    if search and search.strip():
        term = f"%{search.strip()}%"
        base_query = base_query.filter(
            or_(
                Dominio.url_dominio.ilike(term),
                Dominio.proveedor.ilike(term),
                Cliente.empresa.ilike(term),
                Cliente.nombre_contacto.ilike(term),
                Cliente.correo.ilike(term),
                cast(Dominio.id_dominio, String).ilike(term),
            )
        )

    # Filtros
    if filtro == "activos":
        base_query = base_query.filter(
            Dominio.eliminado == 0,
            Dominio.estado_dominio == 1
        )
    elif filtro == "por_vencer":
        base_query = base_query.filter(
            Dominio.eliminado == 0,
            Dominio.fecha_pago >= hoy,
            Dominio.fecha_pago <= limite_30_dias
        )
    elif filtro == "vencidos":
        base_query = base_query.filter(
            Dominio.eliminado == 0,
            Dominio.fecha_pago < hoy,
            Dominio.fecha_pago.isnot(None)
        )
    elif filtro == "pendientes_pago":
        base_query = base_query.filter(
            Dominio.eliminado == 0,
            Dominio.estatus_pago == 0
        )
    elif filtro == "pagados":
        base_query = base_query.filter(
            Dominio.eliminado == 0,
            Dominio.estatus_pago == 1
        )
    elif filtro == "externos":
        base_query = base_query.filter(
            Dominio.eliminado == 0,
            Dominio.registrado == 0
        )
    elif filtro == "eliminados":
        base_query = base_query.filter(Dominio.eliminado == 1)

    # Conteo total
    total_records = base_query.count()

    # Ordenamiento por defecto: por fecha de vencimiento más próxima primero, o id desc
    base_query = base_query.order_by(
        desc(Dominio.fecha_pago >= hoy),
        asc(Dominio.fecha_pago),
        desc(Dominio.id_dominio)
    )

    offset = (page - 1) * limit
    results = base_query.offset(offset).limit(limit).all()

    items: List[DominioListItem] = []
    for dom, cli_nombre, cli_empresa, cli_correo, cli_telefono in results:
        v_info = calcular_vencimiento_dominio(dom.fecha_pago, hoy)
        items.append(
            DominioListItem(
                id_dominio=dom.id_dominio,
                url_dominio=dom.url_dominio or "Sin dominio",
                cliente_id=dom.cliente_id,
                cliente_nombre=cli_nombre or "Sin Asignar",
                cliente_empresa=cli_empresa or "Sin Empresa",
                cliente_correo=cli_correo,
                cliente_telefono=cli_telefono,
                proveedor=dom.proveedor or "NexusBot",
                costo_dominio=float(dom.costo_dominio or 0.0),
                fecha_contratacion=str(dom.fecha_contratacion) if dom.fecha_contratacion else None,
                fecha_pago=v_info["str"],
                dias_restantes=v_info["dias"],
                estado_vencimiento=v_info["estado"],
                estado_dominio=dom.estado_dominio or 1,
                estatus_pago=dom.estatus_pago or 0,
                registrado=dom.registrado or 0,
                eliminado=dom.eliminado or 0,
            )
        )

    # Calcular estadísticas globales
    total_activos = db.query(func.count(Dominio.id_dominio)).filter(Dominio.eliminado == 0, Dominio.estado_dominio == 1).scalar() or 0
    total_por_vencer = db.query(func.count(Dominio.id_dominio)).filter(
        Dominio.eliminado == 0,
        Dominio.fecha_pago >= hoy,
        Dominio.fecha_pago <= limite_30_dias
    ).scalar() or 0
    total_vencidos = db.query(func.count(Dominio.id_dominio)).filter(
        Dominio.eliminado == 0,
        Dominio.fecha_pago < hoy,
        Dominio.fecha_pago.isnot(None)
    ).scalar() or 0
    total_pagados = db.query(func.count(Dominio.id_dominio)).filter(Dominio.eliminado == 0, Dominio.estatus_pago == 1).scalar() or 0
    total_pendientes_pago = db.query(func.count(Dominio.id_dominio)).filter(Dominio.eliminado == 0, Dominio.estatus_pago == 0).scalar() or 0
    total_all = db.query(func.count(Dominio.id_dominio)).scalar() or 0

    stats = DominioStats(
        total=total_all,
        activos=total_activos,
        por_vencer_30d=total_por_vencer,
        vencidos=total_vencidos,
        pagados=total_pagados,
        pendientes_pago=total_pendientes_pago,
    )

    total_pages = (total_records + limit - 1) // limit if limit > 0 else 1

    return DominiosListResponse(
        items=items,
        total=total_records,
        page=page,
        limit=limit,
        total_pages=total_pages,
        stats=stats,
    )


@router.get("/{dominio_id}", response_model=DominioDetail, summary="Obtener detalle completo de un dominio")
def get_dominio_detail(
    dominio_id: int,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    dom = db.query(Dominio).filter(Dominio.id_dominio == dominio_id).first()
    if not dom:
        raise HTTPException(status_code=404, detail="Dominio no encontrado")

    if current_user.id_tipo_usuario == 0 and current_user.id != dom.cliente_id:
        raise HTTPException(status_code=403, detail="No tienes permiso para ver este dominio")

    cliente = db.query(Cliente).filter(Cliente.id == dom.cliente_id).first()
    hoy = date.today()
    v_info = calcular_vencimiento_dominio(dom.fecha_pago, hoy)

    return DominioDetail(
        id_dominio=dom.id_dominio,
        cliente_id=dom.cliente_id,
        cliente_nombre=cliente.nombre_contacto if cliente else "Sin contacto",
        cliente_empresa=cliente.empresa if cliente else "Sin empresa",
        cliente_correo=cliente.correo if cliente else None,
        cliente_telefono=cliente.telefono if cliente else None,
        url_dominio=dom.url_dominio or "Sin dominio",
        proveedor=dom.proveedor or "NexusBot",
        url_pago=dom.url_pago,
        url_admin=dom.url_admin,
        usuario=dom.usuario,
        contrasena_normal=dom.contrasena_normal or dom.contrasena,
        url_cpanel=dom.url_cpanel,
        ns1=dom.ns1,
        ns2=dom.ns2,
        ns3=dom.ns3,
        ns4=dom.ns4,
        costo_dominio=float(dom.costo_dominio or 0.0),
        fecha_contratacion=str(dom.fecha_contratacion) if dom.fecha_contratacion else None,
        fecha_pago=v_info["str"],
        dias_restantes=v_info["dias"],
        estado_vencimiento=v_info["estado"],
        estado_dominio=dom.estado_dominio or 1,
        registrado=dom.registrado or 0,
        eliminado=dom.eliminado or 0,
        estatus_pago=dom.estatus_pago or 0,
    )


@router.post("", response_model=DominioDetail, status_code=status.HTTP_201_CREATED, summary="Registrar nuevo dominio")
def create_dominio(
    payload: DominioCreate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    fecha_c = None
    if payload.fecha_contratacion:
        try:
            fecha_c = datetime.strptime(payload.fecha_contratacion, "%Y-%m-%d").date()
        except Exception:
            pass

    fecha_p = None
    if payload.fecha_pago:
        try:
            fecha_p = datetime.strptime(payload.fecha_pago, "%Y-%m-%d").date()
        except Exception:
            pass

    nuevo = Dominio(
        cliente_id=payload.cliente_id,
        url_dominio=payload.url_dominio,
        proveedor=payload.proveedor or "NexusBot",
        url_pago=payload.url_pago,
        url_admin=payload.url_admin,
        usuario=payload.usuario,
        contrasena_normal=payload.contrasena_normal or payload.contrasena,
        contrasena=payload.contrasena,
        url_cpanel=payload.url_cpanel,
        ns1=payload.ns1,
        ns2=payload.ns2,
        ns3=payload.ns3,
        ns4=payload.ns4,
        costo_dominio=payload.costo_dominio or 0.0,
        fecha_contratacion=fecha_c or date.today(),
        fecha_pago=fecha_p,
        estado_dominio=payload.estado_dominio if payload.estado_dominio is not None else 1,
        registrado=payload.registrado if payload.registrado is not None else 1,
        estatus_pago=payload.estatus_pago or 0,
        eliminado=0,
    )
    db.add(nuevo)
    db.commit()
    db.refresh(nuevo)

    return get_dominio_detail(nuevo.id_dominio, current_user, db)


@router.put("/{dominio_id}", response_model=DominioDetail, summary="Actualizar información del dominio")
def update_dominio(
    dominio_id: int,
    payload: DominioUpdate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    dom = db.query(Dominio).filter(Dominio.id_dominio == dominio_id).first()
    if not dom:
        raise HTTPException(status_code=404, detail="Dominio no encontrado")

    update_data = payload.model_dump(exclude_unset=True)

    if "fecha_contratacion" in update_data and update_data["fecha_contratacion"]:
        try:
            update_data["fecha_contratacion"] = datetime.strptime(update_data["fecha_contratacion"], "%Y-%m-%d").date()
        except Exception:
            pass

    if "fecha_pago" in update_data and update_data["fecha_pago"]:
        try:
            update_data["fecha_pago"] = datetime.strptime(update_data["fecha_pago"], "%Y-%m-%d").date()
        except Exception:
            pass

    for field, value in update_data.items():
        if hasattr(dom, field) and value is not None:
            setattr(dom, field, value)

    db.commit()
    db.refresh(dom)

    return get_dominio_detail(dom.id_dominio, current_user, db)


@router.delete("/{dominio_id}", summary="Eliminar (soft delete) dominio")
def delete_dominio(
    dominio_id: int,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    dom = db.query(Dominio).filter(Dominio.id_dominio == dominio_id).first()
    if not dom:
        raise HTTPException(status_code=404, detail="Dominio no encontrado")

    dom.eliminado = 1
    db.commit()

    return {"status": "ok", "message": f"Dominio #{dominio_id} marcado como eliminado correctamente"}
