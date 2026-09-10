from datetime import datetime, date, time, timedelta
from typing import Optional, List
from fastapi import APIRouter, Depends, Query, HTTPException, status
from sqlalchemy.orm import Session
from sqlalchemy import func, or_, and_, desc, asc, cast, String

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.schemas.pago import (
    PagosListResponse,
    PagoListItem,
    PagoStats,
    PagoDetail,
    PagoCreate,
    PagoUpdate,
    PagoStatusToggle,
)
from app.models.pago import Pago
from app.models.cliente import Cliente
from app.models.hosting import Hosting
from app.models.dominio import Dominio

router = APIRouter()


def get_forma_pago_label(forma: int) -> str:
    labels = {
        1: "Transferencia / Depósito",
        2: "Efectivo / OXXO",
        3: "Tarjeta / Stripe",
        4: "PayPal",
    }
    return labels.get(forma, "Transferencia")


def get_tipo_servicio_label(tipo: int) -> str:
    labels = {
        1: "Hosting",
        2: "Dominio",
        0: "Manual / Otro",
        3: "Desarrollo / Diseño",
    }
    return labels.get(tipo, "Manual / Otro")


def calcular_vencimiento_pago(fecha_limite, estatus: int, hoy: date):
    if estatus == 1:
        return {"estado": "pagado", "dias": None, "str": str(fecha_limite) if fecha_limite else None}
    
    if not fecha_limite or str(fecha_limite).startswith("0000-00-00"):
        return {"estado": "sin_fecha", "dias": None, "str": None}
    
    try:
        if isinstance(fecha_limite, (date, datetime)):
            fl = fecha_limite if isinstance(fecha_limite, date) else fecha_limite.date()
        else:
            fl = datetime.strptime(str(fecha_limite), "%Y-%m-%d").date()
        
        dias = (fl - hoy).days
        fl_str = fl.strftime("%Y-%m-%d")

        if dias < 0:
            return {"estado": "vencido", "dias": dias, "str": fl_str}
        elif dias <= 7:
            return {"estado": "prox7", "dias": dias, "str": fl_str}
        elif dias <= 15:
            return {"estado": "prox15", "dias": dias, "str": fl_str}
        elif dias <= 30:
            return {"estado": "prox30", "dias": dias, "str": fl_str}
        else:
            return {"estado": "ok", "dias": dias, "str": fl_str}
    except Exception:
        return {"estado": "sin_fecha", "dias": None, "str": str(fecha_limite)}


@router.get("", response_model=PagosListResponse, summary="Listar pagos y cobros con métricas financieras")
def list_pagos(
    search: Optional[str] = Query(None, description="Búsqueda por concepto, cliente, folio o monto"),
    filtro: str = Query("todos", description="Filtro: todos, pendientes, pagados, vencidos, hosting, dominios, manuales, eliminados"),
    fecha_desde: Optional[str] = Query(None, description="Fecha inicio YYYY-MM-DD"),
    fecha_hasta: Optional[str] = Query(None, description="Fecha fin YYYY-MM-DD"),
    page: int = Query(1, ge=1, description="Número de página"),
    limit: int = Query(20, ge=1, le=100, description="Registros por página"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    hoy = date.today()

    # Query principal con JOINs
    base_query = (
        db.query(
            Pago,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.empresa.label("cliente_empresa"),
            Cliente.correo.label("cliente_correo"),
            Cliente.telefono.label("cliente_telefono"),
            Hosting.nom_host.label("hosting_nombre"),
            Dominio.url_dominio.label("dominio_nombre"),
        )
        .outerjoin(Cliente, Pago.id_clie == Cliente.id)
        .outerjoin(Hosting, and_(Pago.tipo_servicio == 1, Pago.id_servicio == Hosting.id_orden))
        .outerjoin(Dominio, and_(Pago.tipo_servicio == 2, Pago.id_servicio == Dominio.id_dominio))
    )

    # Si es cliente normal, solo sus pagos
    if current_user.id_tipo_usuario == 0:
        base_query = base_query.filter(Pago.id_clie == current_user.id)

    # Búsqueda por término
    if search and search.strip():
        term = f"%{search.strip()}%"
        base_query = base_query.filter(
            or_(
                Pago.concepto.ilike(term),
                Pago.id_pago.ilike(term),
                Cliente.empresa.ilike(term),
                Cliente.nombre_contacto.ilike(term),
                Cliente.correo.ilike(term),
                Hosting.nom_host.ilike(term),
                Dominio.url_dominio.ilike(term),
                cast(Pago.id, String).ilike(term),
                cast(Pago.monto, String).ilike(term),
            )
        )

    # Rango de fechas
    if fecha_desde:
        try:
            fd = datetime.strptime(fecha_desde, "%Y-%m-%d").date()
            base_query = base_query.filter(Pago.fecha >= fd)
        except ValueError:
            pass

    if fecha_hasta:
        try:
            fh = datetime.strptime(fecha_hasta, "%Y-%m-%d").date()
            base_query = base_query.filter(Pago.fecha <= fh)
        except ValueError:
            pass

    # Filtros
    if filtro == "pendientes":
        base_query = base_query.filter(Pago.Registro == 0, Pago.estatus == 0)
    elif filtro == "pagados":
        base_query = base_query.filter(Pago.Registro == 0, Pago.estatus == 1)
    elif filtro == "vencidos":
        base_query = base_query.filter(
            Pago.Registro == 0,
            Pago.estatus == 0,
            Pago.fecha_limite_pago < hoy,
            Pago.fecha_limite_pago.isnot(None),
        )
    elif filtro == "hosting":
        base_query = base_query.filter(Pago.Registro == 0, Pago.tipo_servicio == 1)
    elif filtro == "dominios":
        base_query = base_query.filter(Pago.Registro == 0, Pago.tipo_servicio == 2)
    elif filtro == "manuales":
        base_query = base_query.filter(Pago.Registro == 0, or_(Pago.manual == 1, Pago.tipo_servicio == 0))
    elif filtro == "eliminados":
        base_query = base_query.filter(Pago.Registro == 1)
    elif filtro == "todos":
        base_query = base_query.filter(Pago.Registro == 0)

    total_records = base_query.count()

    # Ordenamiento por fecha desc, id desc
    base_query = base_query.order_by(
        desc(Pago.estatus == 0),
        desc(Pago.fecha),
        desc(Pago.id)
    )

    offset = (page - 1) * limit
    results = base_query.offset(offset).limit(limit).all()

    items: List[PagoListItem] = []
    for pago, cli_nom, cli_emp, cli_mail, cli_tel, host_nom, dom_nom in results:
        venc = calcular_vencimiento_pago(pago.fecha_limite_pago, pago.estatus, hoy)
        
        # Nombre de servicio asociado
        nombre_serv = None
        if pago.tipo_servicio == 1:
            nombre_serv = host_nom
        elif pago.tipo_servicio == 2:
            nombre_serv = dom_nom

        items.append(
            PagoListItem(
                id=pago.id,
                id_clie=pago.id_clie,
                cliente_nombre=cli_nom or "Cliente Desconocido",
                cliente_empresa=cli_emp or "Sin Empresa",
                cliente_correo=cli_mail,
                cliente_telefono=cli_tel,
                id_servicio=pago.id_servicio or 0,
                nombre_servicio=nombre_serv,
                monto=float(pago.monto or 0.0),
                currency=pago.currency or "MXN",
                concepto=pago.concepto or "",
                forma_pago=pago.forma_pago or 1,
                forma_pago_label=get_forma_pago_label(pago.forma_pago),
                estatus=pago.estatus,
                estatus_label="Acreditado" if pago.estatus == 1 else "Pendiente",
                tipo_servicio=pago.tipo_servicio or 0,
                tipo_servicio_label=get_tipo_servicio_label(pago.tipo_servicio),
                fecha=str(pago.fecha) if pago.fecha else "",
                fecha_pago=str(pago.fecha_pago) if pago.fecha_pago else None,
                fecha_limite_pago=venc["str"],
                dias_restantes=venc["dias"],
                estado_vencimiento=venc["estado"],
                id_pago=pago.id_pago or "",
                manual=pago.manual or 0,
                Registro=pago.Registro or 0,
            )
        )

    # Cálculo de métricas financieras globales
    stats_query = db.query(Pago)
    if current_user.id_tipo_usuario == 0:
        stats_query = stats_query.filter(Pago.id_clie == current_user.id)

    all_pagos = stats_query.all()
    stats = PagoStats()
    stats.total_registros = len(all_pagos)

    for p in all_pagos:
        if p.Registro == 1:
            stats.total_eliminados += 1
            continue

        monto_float = float(p.monto or 0.0)
        curr = (p.currency or "MXN").upper()

        if p.estatus == 1:
            stats.total_pagados += 1
            if curr == "USD":
                stats.monto_cobrado_usd += monto_float
            else:
                stats.monto_cobrado_mxn += monto_float
        else:
            stats.total_pendientes += 1
            if curr == "USD":
                stats.monto_pendiente_usd += monto_float
            else:
                stats.monto_pendiente_mxn += monto_float

            if p.fecha_limite_pago and not str(p.fecha_limite_pago).startswith("0000-00-00"):
                fl = p.fecha_limite_pago if isinstance(p.fecha_limite_pago, date) else datetime.strptime(str(p.fecha_limite_pago), "%Y-%m-%d").date()
                if fl < hoy:
                    stats.total_vencidos += 1

    total_pages = (total_records + limit - 1) // limit if total_records > 0 else 1

    return PagosListResponse(
        items=items,
        total=total_records,
        page=page,
        total_pages=total_pages,
        stats=stats
    )


@router.get("/{id_pago}", response_model=PagoDetail, summary="Detalle completo de un pago")
def get_pago_detail(
    id_pago: int,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    result = (
        db.query(
            Pago,
            Cliente.nombre_contacto.label("cliente_nombre"),
            Cliente.empresa.label("cliente_empresa"),
            Cliente.correo.label("cliente_correo"),
            Cliente.telefono.label("cliente_telefono"),
            Hosting.nom_host.label("hosting_nombre"),
            Dominio.url_dominio.label("dominio_nombre"),
        )
        .outerjoin(Cliente, Pago.id_clie == Cliente.id)
        .outerjoin(Hosting, and_(Pago.tipo_servicio == 1, Pago.id_servicio == Hosting.id_orden))
        .outerjoin(Dominio, and_(Pago.tipo_servicio == 2, Pago.id_servicio == Dominio.id_dominio))
        .filter(Pago.id == id_pago)
        .first()
    )

    if not result:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Pago #{id_pago} no encontrado",
        )

    pago, cli_nom, cli_emp, cli_mail, cli_tel, host_nom, dom_nom = result

    # Restricción si es cliente
    if current_user.id_tipo_usuario == 0 and pago.id_clie != current_user.id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="No tienes permiso para consultar este pago",
        )

    hoy = date.today()
    venc = calcular_vencimiento_pago(pago.fecha_limite_pago, pago.estatus, hoy)

    nombre_serv = None
    if pago.tipo_servicio == 1:
        nombre_serv = host_nom
    elif pago.tipo_servicio == 2:
        nombre_serv = dom_nom

    return PagoDetail(
        id=pago.id,
        id_clie=pago.id_clie,
        cliente_nombre=cli_nom or "Cliente Desconocido",
        cliente_empresa=cli_emp or "Sin Empresa",
        cliente_correo=cli_mail,
        cliente_telefono=cli_tel,
        id_servicio=pago.id_servicio or 0,
        nombre_servicio=nombre_serv,
        monto=float(pago.monto or 0.0),
        currency=pago.currency or "MXN",
        concepto=pago.concepto or "",
        forma_pago=pago.forma_pago or 1,
        forma_pago_label=get_forma_pago_label(pago.forma_pago),
        estatus=pago.estatus,
        estatus_label="Acreditado" if pago.estatus == 1 else "Pendiente",
        tipo_servicio=pago.tipo_servicio or 0,
        tipo_servicio_label=get_tipo_servicio_label(pago.tipo_servicio),
        fecha=str(pago.fecha) if pago.fecha else "",
        hora=str(pago.hora) if pago.hora else None,
        fecha_pago=str(pago.fecha_pago) if pago.fecha_pago else None,
        hora_pago=str(pago.hora_pago) if pago.hora_pago else None,
        fecha_limite_pago=venc["str"],
        dias_restantes=venc["dias"],
        estado_vencimiento=venc["estado"],
        id_pago=pago.id_pago or "",
        session_id=pago.session_id,
        pago_grupal_id=pago.pago_grupal_id,
        manual=pago.manual or 0,
        frecuencia_pago=pago.frecuencia_pago or 0,
        pago_recurrente=pago.pago_recurrente or 0,
        sistema=pago.sistema or "nexus",
        Registro=pago.Registro or 0,
    )


@router.post("", response_model=PagoDetail, status_code=status.HTTP_201_CREATED, summary="Registrar nuevo cobro o pago")
def create_pago(
    payload: PagoCreate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden crear registros de cobro o pago",
        )

    # Validar cliente
    cliente = db.query(Cliente).filter(Cliente.id == payload.id_clie).first()
    if not cliente:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Cliente #{payload.id_clie} no encontrado",
        )

    hoy = date.today()
    ahora_hora = datetime.now().time()

    f_emision = hoy
    if payload.fecha:
        try:
            f_emision = datetime.strptime(payload.fecha, "%Y-%m-%d").date()
        except ValueError:
            pass

    f_pago = None
    if payload.estatus == 1:
        f_pago = hoy
        if payload.fecha_pago:
            try:
                f_pago = datetime.strptime(payload.fecha_pago, "%Y-%m-%d").date()
            except ValueError:
                pass

    f_limite = None
    if payload.fecha_limite_pago:
        try:
            f_limite = datetime.strptime(payload.fecha_limite_pago, "%Y-%m-%d").date()
        except ValueError:
            pass

    nuevo_pago = Pago(
        id_clie=payload.id_clie,
        id_servicio=payload.id_servicio or 0,
        fecha=f_emision,
        hora=ahora_hora,
        fecha_pago=f_pago,
        hora_pago=ahora_hora if f_pago else None,
        monto=payload.monto,
        currency=(payload.currency or "MXN").upper(),
        concepto=payload.concepto.strip(),
        forma_pago=payload.forma_pago or 1,
        estatus=payload.estatus or 0,
        id_pago=payload.id_pago or f"NEX-{int(datetime.now().timestamp())}",
        tipo_servicio=payload.tipo_servicio or 0,
        fecha_limite_pago=f_limite,
        manual=payload.manual or 1,
        frecuencia_pago=payload.frecuencia_pago or 0,
        pago_recurrente=payload.pago_recurrente or 0,
        sistema="nexus",
        Registro=0,
    )

    db.add(nuevo_pago)
    db.commit()
    db.refresh(nuevo_pago)

    return get_pago_detail(nuevo_pago.id, current_user, db)


@router.put("/{id_pago}", response_model=PagoDetail, summary="Actualizar registro de pago")
def update_pago(
    id_pago: int,
    payload: PagoUpdate,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden modificar pagos",
        )

    pago = db.query(Pago).filter(Pago.id == id_pago).first()
    if not pago:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Pago #{id_pago} no encontrado",
        )

    if payload.id_clie is not None:
        cliente = db.query(Cliente).filter(Cliente.id == payload.id_clie).first()
        if not cliente:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Cliente #{payload.id_clie} no existe",
            )
        pago.id_clie = payload.id_clie

    if payload.monto is not None:
        pago.monto = payload.monto
    if payload.currency is not None:
        pago.currency = payload.currency.upper()
    if payload.concepto is not None:
        pago.concepto = payload.concepto.strip()
    if payload.forma_pago is not None:
        pago.forma_pago = payload.forma_pago
    if payload.tipo_servicio is not None:
        pago.tipo_servicio = payload.tipo_servicio
    if payload.id_servicio is not None:
        pago.id_servicio = payload.id_servicio
    if payload.id_pago is not None:
        pago.id_pago = payload.id_pago
    if payload.manual is not None:
        pago.manual = payload.manual
    if payload.frecuencia_pago is not None:
        pago.frecuencia_pago = payload.frecuencia_pago
    if payload.pago_recurrente is not None:
        pago.pago_recurrente = payload.pago_recurrente
    if payload.Registro is not None:
        pago.Registro = payload.Registro

    if payload.estatus is not None:
        pago.estatus = payload.estatus
        if payload.estatus == 1 and not pago.fecha_pago:
            pago.fecha_pago = date.today()
            pago.hora_pago = datetime.now().time()
        elif payload.estatus == 0:
            pago.fecha_pago = None
            pago.hora_pago = None

    if payload.fecha is not None:
        if payload.fecha == "":
            pago.fecha = date.today()
        else:
            try:
                pago.fecha = datetime.strptime(payload.fecha, "%Y-%m-%d").date()
            except ValueError:
                pass

    if payload.fecha_pago is not None:
        if payload.fecha_pago == "":
            pago.fecha_pago = None
        else:
            try:
                pago.fecha_pago = datetime.strptime(payload.fecha_pago, "%Y-%m-%d").date()
            except ValueError:
                pass

    if payload.fecha_limite_pago is not None:
        if payload.fecha_limite_pago == "":
            pago.fecha_limite_pago = None
        else:
            try:
                pago.fecha_limite_pago = datetime.strptime(payload.fecha_limite_pago, "%Y-%m-%d").date()
            except ValueError:
                pass

    db.commit()
    db.refresh(pago)

    return get_pago_detail(pago.id, current_user, db)


@router.patch("/{id_pago}/toggle-status", response_model=PagoDetail, summary="Acreditar o marcar pendiente un pago rápidamente")
def toggle_pago_status(
    id_pago: int,
    payload: PagoStatusToggle,
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden acreditar pagos",
        )

    pago = db.query(Pago).filter(Pago.id == id_pago).first()
    if not pago:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Pago #{id_pago} no encontrado",
        )

    pago.estatus = payload.estatus
    if payload.estatus == 1:
        if payload.fecha_pago:
            try:
                pago.fecha_pago = datetime.strptime(payload.fecha_pago, "%Y-%m-%d").date()
            except ValueError:
                pago.fecha_pago = date.today()
        else:
            pago.fecha_pago = date.today()
        pago.hora_pago = datetime.now().time()
    else:
        pago.fecha_pago = None
        pago.hora_pago = None

    db.commit()
    db.refresh(pago)

    return get_pago_detail(pago.id, current_user, db)


@router.delete("/{id_pago}", summary="Mover pago a papelera o eliminar definitivamente")
def delete_pago(
    id_pago: int,
    permanent: bool = Query(False, description="Eliminar permanentemente de la BD"),
    current_user: UserProfile = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    if current_user.id_tipo_usuario == 0:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo administradores pueden eliminar pagos",
        )

    pago = db.query(Pago).filter(Pago.id == id_pago).first()
    if not pago:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Pago #{id_pago} no encontrado",
        )

    if permanent:
        db.delete(pago)
        db.commit()
        return {"status": "success", "message": f"Pago #{id_pago} eliminado permanentemente"}
    else:
        nuevo_registro = 0 if pago.Registro == 1 else 1
        pago.Registro = nuevo_registro
        db.commit()
        accion = "restaurado" if nuevo_registro == 0 else "movido a papelera"
        return {"status": "success", "message": f"Pago #{id_pago} {accion}", "Registro": nuevo_registro}
