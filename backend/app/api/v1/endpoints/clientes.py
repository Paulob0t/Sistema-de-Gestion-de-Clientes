from datetime import datetime, date, timedelta
from typing import Optional, List
from fastapi import APIRouter, Depends, Query, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import func, or_, and_, desc, asc, cast, String

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.schemas.cliente import (
    ClientesListResponse,
    ClienteListItem,
    ClienteStats,
    ClienteDetailOut,
    DominioSimple,
    HostingSimple,
    PagoSimple,
    ClienteCreate,
    ClienteUpdate,
)
from app.models.cliente import Cliente
from app.models.dominio import Dominio
from app.models.hosting import Hosting
from app.models.pago import Pago
from app.models.login import Login
import hashlib

router = APIRouter()


@router.get("", response_model=ClientesListResponse, summary="Listar y consultar clientes con métricas")
def list_clientes(
    search: Optional[str] = Query(None, description="Búsqueda por empresa, contacto, correo, teléfono o RFC"),
    filtro: str = Query("activos", description="Filtro: todos, activos, pendientes, transferidos, eliminados, sin_servicios"),
    sistema: str = Query("conlineweb", description="Sistema: conlineweb o hostingpro"),
    page: int = Query(1, ge=1, description="Número de página"),
    limit: int = Query(20, ge=1, le=100, description="Registros por página"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    # Subqueries para conteos agregados
    sub_dom = (
        db.query(
            Dominio.cliente_id,
            func.count(Dominio.id_dominio).label("total_dominios")
        )
        .filter(or_(Dominio.eliminado == 0, Dominio.eliminado.is_(None)))
        .group_by(Dominio.cliente_id)
        .subquery()
    )

    sub_host = (
        db.query(
            Hosting.cliente_id,
            func.count(Hosting.id_orden).label("total_hostings")
        )
        .filter(or_(Hosting.eliminado == 0, Hosting.eliminado.is_(None)))
        .group_by(Hosting.cliente_id)
        .subquery()
    )

    sub_pagos = (
        db.query(
            Pago.id_clie.label("cliente_id"),
            func.count(Pago.id).label("total_pagos_pend")
        )
        .filter(Pago.estatus == 0, Pago.Registro == 0)
        .group_by(Pago.id_clie)
        .subquery()
    )

    # Consulta base con OUTER JOIN a subconsultas
    base_query = (
        db.query(
            Cliente,
            func.coalesce(sub_dom.c.total_dominios, 0).label("total_dominios"),
            func.coalesce(sub_host.c.total_hostings, 0).label("total_hostings"),
            func.coalesce(sub_pagos.c.total_pagos_pend, 0).label("total_pagos_pend"),
        )
        .outerjoin(sub_dom, Cliente.id == sub_dom.c.cliente_id)
        .outerjoin(sub_host, Cliente.id == sub_host.c.cliente_id)
        .outerjoin(sub_pagos, Cliente.id == sub_pagos.c.cliente_id)
    )

    # Si es usuario tipo 5 (ventas/leads), solo clientes que él registró
    if current_user.id_tipo_usuario == 5:
        base_query = base_query.filter(Cliente.usuario_registro == current_user.id)
    elif current_user.id_tipo_usuario == 0:
        # Si es cliente, solo su propio perfil
        base_query = base_query.filter(Cliente.id == current_user.id)

    # Búsqueda por texto
    if search and search.strip():
        term = f"%{search.strip()}%"
        base_query = base_query.filter(
            or_(
                Cliente.empresa.ilike(term),
                Cliente.nombre_contacto.ilike(term),
                Cliente.correo.ilike(term),
                Cliente.telefono.ilike(term),
                Cliente.rfc.ilike(term),
                cast(Cliente.id, String).ilike(term),
            )
        )

    # Filtros de estado
    if filtro == "activos":
        base_query = base_query.filter(Cliente.eliminado == 0, Cliente.transferido == 0)
    elif filtro == "pendientes":
        base_query = base_query.filter(
            Cliente.eliminado == 0,
            func.coalesce(sub_pagos.c.total_pagos_pend, 0) > 0
        )
    elif filtro == "transferidos":
        base_query = base_query.filter(Cliente.transferido == 1)
    elif filtro == "eliminados":
        base_query = base_query.filter(Cliente.eliminado == 1)
    elif filtro == "sin_servicios":
        base_query = base_query.filter(
            Cliente.eliminado == 0,
            func.coalesce(sub_dom.c.total_dominios, 0) == 0,
            func.coalesce(sub_host.c.total_hostings, 0) == 0
        )
    # "todos" no aplica filtro extra

    # Conteo total para paginación
    total_records = base_query.count()

    # Ordenamiento: por defecto ID descendente
    base_query = base_query.order_by(desc(Cliente.id))

    # Paginación
    offset = (page - 1) * limit
    results = base_query.offset(offset).limit(limit).all()

    # Transformar a items
    items: List[ClienteListItem] = []
    for cliente, dom_count, host_count, pagos_count in results:
        estado_pago = "sin_servicios"
        if dom_count > 0 or host_count > 0:
            estado_pago = "pendiente" if pagos_count > 0 else "al_dia"

        items.append(
            ClienteListItem(
                id=cliente.id,
                empresa=cliente.empresa or "Sin Empresa",
                nombre_contacto=cliente.nombre_contacto or "Sin Contacto",
                correo=cliente.correo,
                telefono=cliente.telefono,
                rfc=cliente.rfc,
                ciudad=cliente.ciudad,
                estado=cliente.estado,
                eliminado=cliente.eliminado or 0,
                transferido=cliente.transferido or 0,
                total_dominios=dom_count,
                total_hostings=host_count,
                total_pagos_pendientes=pagos_count,
                estado_pago=estado_pago,
                facturacion=cliente.facturacion or 0,
            )
        )

    # Calcular estadísticas globales rápidas
    total_activos = db.query(func.count(Cliente.id)).filter(Cliente.eliminado == 0, Cliente.transferido == 0).scalar() or 0
    total_transferidos = db.query(func.count(Cliente.id)).filter(Cliente.transferido == 1).scalar() or 0
    total_eliminados = db.query(func.count(Cliente.id)).filter(Cliente.eliminado == 1).scalar() or 0
    total_todos = db.query(func.count(Cliente.id)).scalar() or 0
    
    # Clientes con pagos pendientes
    clientes_con_pendientes = (
        db.query(func.count(func.distinct(Pago.id_clie)))
        .filter(Pago.estatus == 0, Pago.Registro == 0)
        .scalar() or 0
    )

    stats = ClienteStats(
        total=total_todos,
        activos=total_activos,
        con_pagos_pendientes=clientes_con_pendientes,
        transferidos=total_transferidos,
        eliminados=total_eliminados,
    )

    total_pages = (total_records + limit - 1) // limit if limit > 0 else 1

    return ClientesListResponse(
        items=items,
        total=total_records,
        page=page,
        limit=limit,
        total_pages=total_pages,
        stats=stats,
    )


@router.get("/{cliente_id}", response_model=ClienteDetailOut, summary="Obtener detalle completo de un cliente")
def get_cliente_detail(
    cliente_id: int,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0 and current_user.id != cliente_id:
        raise HTTPException(status_code=403, detail="No tienes permiso para ver este cliente")

    cliente = db.query(Cliente).filter(Cliente.id == cliente_id).first()
    if not cliente:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")

    hoy = date.today()

    # Dominios activos
    dominios_raw = (
        db.query(Dominio)
        .filter(Dominio.cliente_id == cliente_id, or_(Dominio.eliminado == 0, Dominio.eliminado.is_(None)))
        .order_by(Dominio.id_dominio.desc())
        .all()
    )
    dominios_list: List[DominioSimple] = []
    for d in dominios_raw:
        dias = None
        if d.fecha_pago and str(d.fecha_pago) != "0000-00-00":
            try:
                if isinstance(d.fecha_pago, (date, datetime)):
                    fv = d.fecha_pago if isinstance(d.fecha_pago, date) else d.fecha_pago.date()
                    dias = (fv - hoy).days
            except Exception:
                pass
        
        dominios_list.append(
            DominioSimple(
                id=d.id_dominio,
                dominio=d.url_dominio or "Sin Dominio",
                fecha_registro=str(d.fecha_contratacion) if d.fecha_contratacion else None,
                fecha_vencimiento=str(d.fecha_pago) if d.fecha_pago else None,
                dias_restantes=dias,
                precio=float(d.costo_dominio) if d.costo_dominio else 0.0,
            )
        )

    # Hostings activos
    hostings_raw = (
        db.query(Hosting)
        .filter(Hosting.cliente_id == cliente_id, or_(Hosting.eliminado == 0, Hosting.eliminado.is_(None)))
        .order_by(Hosting.id_orden.desc())
        .all()
    )
    hostings_list: List[HostingSimple] = []
    for h in hostings_raw:
        dias = None
        if h.fecha_pago and str(h.fecha_pago) != "0000-00-00":
            try:
                if isinstance(h.fecha_pago, (date, datetime)):
                    fv = h.fecha_pago if isinstance(h.fecha_pago, date) else h.fecha_pago.date()
                    dias = (fv - hoy).days
            except Exception:
                pass

        hostings_list.append(
            HostingSimple(
                id=h.id_orden,
                nombre_plan=h.nom_host or "Plan Hosting",
                dominio=h.dominio or "",
                fecha_vencimiento=str(h.fecha_pago) if h.fecha_pago else None,
                dias_restantes=dias,
                precio=float(h.costo_producto) if h.costo_producto else 0.0,
            )
        )

    # Pagos recientes
    pagos_raw = (
        db.query(Pago)
        .filter(Pago.id_clie == cliente_id)
        .order_by(Pago.id.desc())
        .limit(20)
        .all()
    )
    pagos_list: List[PagoSimple] = []
    for p in pagos_raw:
        pagos_list.append(
            PagoSimple(
                id=p.id,
                concepto=p.concepto or "Servicio Web",
                monto=float(p.monto) if p.monto else 0.0,
                moneda=p.moneda or "MXN",
                fecha_vencimiento=str(p.fecha_limite) if p.fecha_limite else None,
                estatus=p.estatus or 0,
                estatus_texto="Pagado" if p.estatus == 1 else "Pendiente",
            )
        )

    pendientes_count = sum(1 for p in pagos_list if p.estatus == 0)

    return ClienteDetailOut(
        id=cliente.id,
        empresa=cliente.empresa or "Sin Empresa",
        nombre_contacto=cliente.nombre_contacto or "Sin Contacto",
        correo=cliente.correo,
        telefono=cliente.telefono,
        rfc=cliente.rfc,
        rsocial=cliente.rsocial,
        calle=cliente.calle,
        next=cliente.next,
        nint=cliente.nint,
        col=cliente.col,
        cp=cliente.cp,
        pais=cliente.pais or "México",
        estado=cliente.estado,
        ciudad=cliente.ciudad,
        especificacion=cliente.especificacion,
        eliminado=cliente.eliminado or 0,
        transferido=cliente.transferido or 0,
        dominios=dominios_list,
        hostings=hostings_list,
        pagos=pagos_list,
        total_dominios=len(dominios_list),
        total_hostings=len(hostings_list),
        total_pagos_pendientes=pendientes_count,
    )


@router.post("", response_model=ClienteDetailOut, status_code=status.HTTP_201_CREATED, summary="Registrar nuevo cliente")
def create_cliente(
    payload: ClienteCreate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden registrar nuevos clientes",
        )

    # Validar correo único si se proporciona
    if payload.correo and payload.correo.strip():
        correo_clean = payload.correo.strip().lower()
        existente = db.query(Login).filter(Login.usuario == correo_clean).first()
        if existente:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=f"El correo '{correo_clean}' ya se encuentra registrado como usuario en el sistema",
            )
    else:
        correo_clean = None

    nuevo_cliente = Cliente(
        empresa=payload.empresa.strip(),
        nombre_contacto=payload.nombre_contacto.strip(),
        correo=correo_clean,
        telefono=payload.telefono.strip() if payload.telefono else None,
        rfc=payload.rfc.strip().upper() if payload.rfc else None,
        rsocial=payload.rsocial.strip() if payload.rsocial else None,
        calle=payload.calle.strip() if payload.calle else None,
        next=payload.next.strip() if payload.next else None,
        nint=payload.nint.strip() if payload.nint else None,
        col=payload.col.strip() if payload.col else None,
        cp=payload.cp.strip() if payload.cp else None,
        pais=payload.pais or "México",
        estado=payload.estado.strip() if payload.estado else None,
        ciudad=payload.ciudad.strip() if payload.ciudad else None,
        especificacion=payload.especificacion.strip() if payload.especificacion else None,
        facturacion=payload.facturacion or (1 if payload.rfc or payload.rsocial else 0),
        constancia_situacion_fiscal=payload.constancia_situacion_fiscal,
        eliminado=0,
        transferido=0,
        usuario_registro=current_user.id,
    )
    db.add(nuevo_cliente)
    db.commit()
    db.refresh(nuevo_cliente)

    # Crear acceso a portal en tabla login si tiene correo
    if correo_clean:
        raw_pass = (payload.contrasena or "").strip()
        if not raw_pass:
            raw_pass = f"Nexus{nuevo_cliente.id}*"
        md5_pass = hashlib.md5(raw_pass.encode()).hexdigest()

        login_record = Login(
            id=nuevo_cliente.id,
            usuario=correo_clean,
            contrasena=md5_pass,
            contrasena_normal=raw_pass,
            id_tipo_usuario=0,
            cambio_contrasena=0,
        )
        db.add(login_record)
        db.commit()

    return get_cliente_detail(nuevo_cliente.id, current_user, db)


@router.put("/{cliente_id}", response_model=ClienteDetailOut, summary="Actualizar información del cliente")
def update_cliente(
    cliente_id: int,
    payload: ClienteUpdate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    cliente = db.query(Cliente).filter(Cliente.id == cliente_id).first()
    if not cliente:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")

    update_data = payload.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        if hasattr(cliente, field) and value is not None:
            setattr(cliente, field, value)

    db.commit()
    db.refresh(cliente)

    return get_cliente_detail(cliente.id, current_user, db)


@router.delete("/{cliente_id}", summary="Eliminar (soft delete) cliente")
def delete_cliente(
    cliente_id: int,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    cliente = db.query(Cliente).filter(Cliente.id == cliente_id).first()
    if not cliente:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")

    cliente.eliminado = 1
    db.commit()

    return {"status": "ok", "message": f"Cliente #{cliente_id} marcado como eliminado correctamente"}
