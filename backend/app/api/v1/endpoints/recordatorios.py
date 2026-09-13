from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.orm import Session
from datetime import datetime, date, timedelta
from typing import Optional, List, Dict, Any
import logging

from app.core.database import get_db
from app.api.v1.endpoints.auth import get_current_user
from app.schemas.auth import UserProfile
from app.models.pago import Pago
from app.models.dominio import Dominio
from app.models.hosting import Hosting
from app.models.cliente import Cliente
from app.schemas.recordatorio import (
    RecordatorioItem,
    RecordatoriosKpis,
    RecordatoriosListResponse,
    RecordatorioPreviewRequest,
    RecordatorioPreviewResponse,
    RecordatorioSendRequest,
    RecordatorioBatchSendRequest,
    RecordatorioSendResponse,
)
from app.services.email_service import (
    send_email,
    test_smtp_connection,
    build_pago_email,
    build_dominio_email,
    build_hosting_email,
    build_custom_email,
)

logger = logging.getLogger(__name__)

router = APIRouter()


def _calculate_urgency(due_date_val: Optional[Any]) -> tuple[int, str, Optional[str]]:
    """
    Calcula los días restantes y la categoría de urgencia de vencimiento.
    """
    if not due_date_val:
        return 999, "futuro", None

    parsed_date: Optional[date] = None
    if isinstance(due_date_val, date) and not isinstance(due_date_val, datetime):
        parsed_date = due_date_val
    elif isinstance(due_date_val, datetime):
        parsed_date = due_date_val.date()
    elif isinstance(due_date_val, str):
        try:
            clean_str = due_date_val.strip()
            if clean_str in ("0000-00-00", "", "None"):
                return 999, "futuro", None
            parsed_date = datetime.strptime(clean_str[:10], "%Y-%m-%d").date()
        except Exception:
            return 999, "futuro", str(due_date_val)

    if not parsed_date:
        return 999, "futuro", None

    today = date.today()
    diff = (parsed_date - today).days
    date_str = parsed_date.strftime("%Y-%m-%d")

    if diff < 0:
        return diff, "vencido", date_str
    elif diff == 0:
        return 0, "hoy", date_str
    elif diff <= 3:
        return diff, "critico_3d", date_str
    elif diff <= 7:
        return diff, "proximo_7d", date_str
    elif diff <= 15:
        return diff, "proximo_15d", date_str
    elif diff <= 30:
        return diff, "proximo_30d", date_str
    else:
        return diff, "futuro", date_str


@router.get("/pendientes", response_model=RecordatoriosListResponse)
def get_pending_reminders(
    search: Optional[str] = Query(None, description="Búsqueda por cliente, concepto o correo"),
    tipo: Optional[str] = Query("todos", description="Tipo de recordatorio: todos, pagos, dominios, hostings"),
    estado: Optional[str] = Query("todos", description="Estado: todos, vencidos, criticos, proximos_7d, proximos_30d"),
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Lista todos los conceptos pendientes (Pagos no liquidados, Dominios y Hostings por vencer).
    """
    items: List[RecordatorioItem] = []

    # 1. Obtener Pagos Pendientes (estatus = 0)
    if tipo in ("todos", "pagos", "pago"):
        pagos_query = (
            db.query(Pago, Cliente)
            .outerjoin(Cliente, Pago.id_clie == Cliente.id)
            .filter(Pago.estatus == 0)
            .all()
        )

        for p, c in pagos_query:
            diff_days, urg_status, due_str = _calculate_urgency(p.fecha_limite_pago)
            nombre_cliente = (c.nombre_contacto if c and c.nombre_contacto else (c.empresa if c and c.empresa else f"Cliente #{p.id_clie}"))
            correo_cliente = c.correo.strip() if (c and c.correo) else None
            tel_cliente = c.telefono.strip() if (c and c.telefono) else None

            items.append(
                RecordatorioItem(
                    id=f"pago-{p.id}",
                    tipo_servicio="pago",
                    raw_id=p.id,
                    cliente_id=p.id_clie or 0,
                    cliente_nombre=nombre_cliente,
                    cliente_correo=correo_cliente,
                    cliente_telefono=tel_cliente,
                    concepto=p.concepto or f"Cobro Folio #{p.folio or p.id}",
                    monto=float(p.monto or 0.0),
                    currency=p.currency or "MXN",
                    fecha_vencimiento=due_str,
                    dias_restantes=diff_days,
                    estado_vencimiento=urg_status,
                    estatus_pago=p.estatus or 0,
                    detalles_servicio=f"Folio: {p.folio or 'S/F'}",
                )
            )

    # 2. Obtener Dominios Próximos a Vencer o Vencidos
    if tipo in ("todos", "dominios", "dominio"):
        dominios_query = (
            db.query(Dominio, Cliente)
            .outerjoin(Cliente, Dominio.cliente_id == Cliente.id)
            .all()
        )

        for d, c in dominios_query:
            # Consideramos fecha_pago como fecha de renovación
            diff_days, urg_status, due_str = _calculate_urgency(d.fecha_pago)
            # Solo incluir si está vencido o vence en los próximos 60 días
            if diff_days <= 60:
                nombre_cliente = (c.nombre_contacto if c and c.nombre_contacto else (c.empresa if c and c.empresa else f"Cliente #{d.cliente_id}"))
                correo_cliente = c.correo.strip() if (c and c.correo) else None
                tel_cliente = c.telefono.strip() if (c and c.telefono) else None

                items.append(
                    RecordatorioItem(
                        id=f"dominio-{d.id}",
                        tipo_servicio="dominio",
                        raw_id=d.id,
                        cliente_id=d.cliente_id or 0,
                        cliente_nombre=nombre_cliente,
                        cliente_correo=correo_cliente,
                        cliente_telefono=tel_cliente,
                        concepto=f"Renovación Dominio: {d.url_dominio}",
                        monto=float(d.costo_producto or 0.0),
                        currency="MXN",
                        fecha_vencimiento=due_str,
                        dias_restantes=diff_days,
                        estado_vencimiento=urg_status,
                        estatus_pago=0,
                        detalles_servicio=f"Proveedor: {d.proveedor_dominio or 'N/A'}",
                    )
                )

    # 3. Obtener Hostings Próximos a Vencer o Vencidos
    if tipo in ("todos", "hostings", "hosting"):
        hostings_query = (
            db.query(Hosting, Cliente)
            .outerjoin(Cliente, Hosting.cliente_id == Cliente.id)
            .all()
        )

        for h, c in hostings_query:
            diff_days, urg_status, due_str = _calculate_urgency(h.fecha_pago)
            if diff_days <= 60:
                nombre_cliente = (c.nombre_contacto if c and c.nombre_contacto else (c.empresa if c and c.empresa else f"Cliente #{h.cliente_id}"))
                correo_cliente = c.correo.strip() if (c and c.correo) else None
                tel_cliente = c.telefono.strip() if (c and c.telefono) else None

                items.append(
                    RecordatorioItem(
                        id=f"hosting-{h.id}",
                        tipo_servicio="hosting",
                        raw_id=h.id,
                        cliente_id=h.cliente_id or 0,
                        cliente_nombre=nombre_cliente,
                        cliente_correo=correo_cliente,
                        cliente_telefono=tel_cliente,
                        concepto=f"Renovación Hosting: {h.dominio or h.nom_host or 'Hosting'}",
                        monto=float(h.costo_producto or 0.0),
                        currency="MXN",
                        fecha_vencimiento=due_str,
                        dias_restantes=diff_days,
                        estado_vencimiento=urg_status,
                        estatus_pago=0,
                        detalles_servicio=f"Plan: {h.plan or 'Estándar'}",
                    )
                )

    # Filtrar por búsqueda de texto
    if search:
        s = search.lower().strip()
        items = [
            it for it in items
            if s in it.cliente_nombre.lower()
            or (it.cliente_correo and s in it.cliente_correo.lower())
            or s in it.concepto.lower()
            or (it.detalles_servicio and s in it.detalles_servicio.lower())
        ]

    # Filtrar por urgencia / estado
    if estado and estado != "todos":
        if estado == "vencidos":
            items = [it for it in items if it.estado_vencimiento == "vencido"]
        elif estado == "criticos":
            items = [it for it in items if it.estado_vencimiento in ("vencido", "hoy", "critico_3d")]
        elif estado == "proximos_7d":
            items = [it for it in items if it.estado_vencimiento in ("vencido", "hoy", "critico_3d", "proximo_7d")]
        elif estado == "proximos_30d":
            items = [it for it in items if it.estado_vencimiento in ("vencido", "hoy", "critico_3d", "proximo_7d", "proximo_15d", "proximo_30d")]

    # Ordenar por días restantes (primero los más vencidos y urgentes)
    items.sort(key=lambda x: x.dias_restantes)

    # Calcular KPIs
    total_pendientes = len(items)
    vencidos = sum(1 for it in items if it.estado_vencimiento == "vencido")
    proximos_7 = sum(1 for it in items if it.dias_restantes >= 0 and it.dias_restantes <= 7)
    con_correo = sum(1 for it in items if it.cliente_correo and "@" in it.cliente_correo)
    monto_mxn = sum(it.monto for it in items if it.currency.upper() == "MXN")
    monto_usd = sum(it.monto for it in items if it.currency.upper() == "USD")

    kpis = RecordatoriosKpis(
        total_pendientes=total_pendientes,
        vencidos=vencidos,
        proximos_7_dias=proximos_7,
        con_correo_valido=con_correo,
        monto_total_pendiente_mxn=round(monto_mxn, 2),
        monto_total_pendiente_usd=round(monto_usd, 2),
    )

    return RecordatoriosListResponse(
        items=items,
        total=total_pendientes,
        kpis=kpis,
    )


@router.post("/preview", response_model=RecordatorioPreviewResponse)
def preview_recordatorio(
    payload: RecordatorioPreviewRequest,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Genera la vista previa en HTML y el asunto de un recordatorio específico.
    """
    destinatario_correo = ""
    destinatario_nombre = "Cliente"

    if payload.tipo == "pago" and payload.id:
        pago = db.query(Pago).filter(Pago.id == payload.id).first()
        if not pago:
            raise HTTPException(status_code=404, detail="Cobro / Pago no encontrado")
        
        cliente = db.query(Cliente).filter(Cliente.id == pago.id_clie).first() if pago.id_clie else None
        if cliente:
            destinatario_nombre = cliente.nombre_contacto or cliente.empresa or "Cliente"
            destinatario_correo = cliente.correo.strip() if cliente.correo else ""

        asunto, html = build_pago_email(
            cliente_nombre=destinatario_nombre,
            concepto=pago.concepto or "Servicios Profesionales",
            monto=float(pago.monto or 0.0),
            currency=pago.currency or "MXN",
            fecha_limite=str(pago.fecha_limite_pago) if pago.fecha_limite_pago else None,
            folio=pago.folio,
        )

    elif payload.tipo == "dominio" and payload.id:
        dominio = db.query(Dominio).filter(Dominio.id == payload.id).first()
        if not dominio:
            raise HTTPException(status_code=404, detail="Dominio no encontrado")

        cliente = db.query(Cliente).filter(Cliente.id == dominio.cliente_id).first() if dominio.cliente_id else None
        if cliente:
            destinatario_nombre = cliente.nombre_contacto or cliente.empresa or "Cliente"
            destinatario_correo = cliente.correo.strip() if cliente.correo else ""

        asunto, html = build_dominio_email(
            cliente_nombre=destinatario_nombre,
            url_dominio=dominio.url_dominio,
            costo=float(dominio.costo_producto or 0.0),
            currency="MXN",
            fecha_vencimiento=str(dominio.fecha_pago) if dominio.fecha_pago else None,
            proveedor=dominio.proveedor_dominio,
        )

    elif payload.tipo == "hosting" and payload.id:
        hosting = db.query(Hosting).filter(Hosting.id == payload.id).first()
        if not hosting:
            raise HTTPException(status_code=404, detail="Hosting no encontrado")

        cliente = db.query(Cliente).filter(Cliente.id == hosting.cliente_id).first() if hosting.cliente_id else None
        if cliente:
            destinatario_nombre = cliente.nombre_contacto or cliente.empresa or "Cliente"
            destinatario_correo = cliente.correo.strip() if cliente.correo else ""

        asunto, html = build_hosting_email(
            cliente_nombre=destinatario_nombre,
            plan_nombre=hosting.plan or "Plan Web",
            dominio_asociado=hosting.dominio or hosting.nom_host or "Alojamiento Web",
            costo=float(hosting.costo_producto or 0.0),
            currency="MXN",
            fecha_renovacion=str(hosting.fecha_pago) if hosting.fecha_pago else None,
            servidor=hosting.servidor,
        )

    elif payload.tipo == "custom":
        if payload.cliente_id:
            cliente = db.query(Cliente).filter(Cliente.id == payload.cliente_id).first()
            if cliente:
                destinatario_nombre = cliente.nombre_contacto or cliente.empresa or "Cliente"
                destinatario_correo = cliente.correo.strip() if cliente.correo else ""

        asunto, html = build_custom_email(
            cliente_nombre=destinatario_nombre,
            asunto=payload.custom_asunto or "Aviso Importante",
            cuerpo=payload.custom_cuerpo or "Le informamos sobre el estatus de sus servicios.",
            despedida=payload.custom_despedida,
        )
    else:
        raise HTTPException(status_code=400, detail="Tipo de recordatorio inválido o parámetros insuficientes")

    return RecordatorioPreviewResponse(
        asunto=asunto,
        html=html,
        destinatario_correo=destinatario_correo,
        destinatario_nombre=destinatario_nombre,
    )


@router.post("/enviar-individual", response_model=RecordatorioSendResponse)
def send_individual_reminder(
    payload: RecordatorioSendRequest,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Envía un recordatorio por correo a un destinatario específico.
    """
    # 1. Obtener vista previa del correo y destinatario
    prev_req = RecordatorioPreviewRequest(
        tipo=payload.tipo,
        id=payload.id,
        cliente_id=payload.cliente_id,
        custom_asunto=payload.asunto,
        custom_cuerpo=payload.cuerpo,
        custom_despedida=payload.despedida,
    )
    preview = preview_recordatorio(prev_req, db, current_user)

    target_email = payload.destinatario_correo or preview.destinatario_correo
    target_subject = payload.asunto or preview.asunto

    if not target_email or "@" not in target_email:
        raise HTTPException(
            status_code=400,
            detail="El cliente no cuenta con una dirección de correo electrónico válida.",
        )

    # 2. Despachar vía SMTP
    success, message = send_email(
        to_email=target_email,
        subject=target_subject,
        html_content=preview.html,
    )

    if not success:
        raise HTTPException(
            status_code=500,
            detail=f"Fallo al enviar correo: {message}",
        )

    return RecordatorioSendResponse(
        success=True,
        message=f"Recordatorio enviado exitosamente a {target_email}",
        total=1,
        enviados=1,
        fallidos=0,
        detalles=[{"email": target_email, "asunto": target_subject, "status": "sent"}],
    )


@router.post("/enviar-masivo", response_model=RecordatorioSendResponse)
def send_batch_reminders(
    payload: RecordatorioBatchSendRequest,
    db: Session = Depends(get_db),
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Envía recordatorios masivos en lote a múltiples servicios/cobros seleccionados.
    """
    if not payload.items:
        raise HTTPException(status_code=400, detail="No se seleccionaron elementos para el envío masivo.")

    total = len(payload.items)
    enviados = 0
    fallidos = 0
    detalles: List[Dict[str, Any]] = []

    for item in payload.items:
        try:
            prev_req = RecordatorioPreviewRequest(tipo=item.tipo, id=item.id)
            preview = preview_recordatorio(prev_req, db, current_user)

            if not preview.destinatario_correo or "@" not in preview.destinatario_correo:
                fallidos += 1
                detalles.append({
                    "id": item.id,
                    "tipo": item.tipo,
                    "status": "skipped_no_email",
                    "reason": f"Cliente '{preview.destinatario_nombre}' sin correo",
                })
                continue

            success, msg = send_email(
                to_email=preview.destinatario_correo,
                subject=preview.asunto,
                html_content=preview.html,
            )

            if success:
                enviados += 1
                detalles.append({
                    "id": item.id,
                    "tipo": item.tipo,
                    "email": preview.destinatario_correo,
                    "status": "sent",
                })
            else:
                fallidos += 1
                detalles.append({
                    "id": item.id,
                    "tipo": item.tipo,
                    "email": preview.destinatario_correo,
                    "status": "error",
                    "reason": msg,
                })

        except Exception as e:
            fallidos += 1
            detalles.append({
                "id": item.id,
                "tipo": item.tipo,
                "status": "error",
                "reason": str(e),
            })

    return RecordatorioSendResponse(
        success=enviados > 0,
        message=f"Envío completado: {enviados} enviados, {fallidos} con error/omitidos de {total} totales.",
        total=total,
        enviados=enviados,
        fallidos=fallidos,
        detalles=detalles,
    )


@router.post("/test-smtp")
def test_smtp(
    current_user: UserProfile = Depends(get_current_user),
):
    """
    Prueba la conexión activa con el servidor SMTP configurado.
    """
    res = test_smtp_connection()
    return res
